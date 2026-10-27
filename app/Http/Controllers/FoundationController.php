<?php
namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Employee;
use App\Services\EmployeeAccess;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FoundationController extends Controller {
    public function loginForm() { $this->publicPermission('session.login'); return view('login'); }
    private function publicPermission(string $key): void { abort_unless(\App\Models\Permission::where('key', $key)->where('scope', 'public')->exists(), 403); }
    private function authorizePermission(Request $request, string $key, ?Organization $organization = null): void { abort_unless($request->user()->hasPermission($key, $organization), 403); }
    public function login(Request $request) {
        $this->publicPermission('session.login');
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($credentials)) throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        if ($request->user()->must_set_password) { Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); throw ValidationException::withMessages(['email'=>'Set up your password using your invitation first.']); }
        $request->session()->regenerate();
        $memberships = $request->user()->organizations()->get();
        if ($memberships->count() === 1 && ! \App\Models\Employee::where('organization_id', $memberships->first()->id)->where('user_id', $request->user()->id)->exists()) return redirect()->route('self-profile.show', $memberships->first());
        return redirect()->intended(route('home'));
    }
    public function logout(Request $request) { $this->authorizePermission($request, 'session.logout'); Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login'); }
    public function index(Request $request) {
        $this->authorizePermission($request, 'organizations.list');
        $profileOrganizations = $request->user()->organizations()->orderBy('name')->get();
        $profileEmployeeOrganizations = Employee::where('user_id',$request->user()->id)->pluck('organization_id')->all();
        $organizations = $request->user()->is_platform_admin ? Organization::orderBy('name')->get() : $request->user()->organizations()->orderBy('name')->get();
        $organizations = $organizations->filter(fn ($org) => $request->user()->hasPermission('organization.view', $org));
        return view('home', compact('organizations', 'profileOrganizations', 'profileEmployeeOrganizations'));
    }
    public function createOrganization(Request $request) {
        $this->authorizePermission($request, 'organizations.create');
        $data = $request->validate(['name' => 'required|string|max:255']);
        $organization = DB::transaction(function () use ($data) { $org = Organization::create($data); app(\App\Services\DefaultHrRole::class)->createIfMissing($org); return $org; }); return redirect()->route('organizations.show', $organization);
    }
    public function show(Request $request, Organization $organization) {
        $this->authorizePermission($request, 'organization.view', $organization);
        $user = $request->user();
        $units = $user->hasPermission('units.view', $organization) ? $organization->units()->orderBy('name')->get() : collect();
        $admins = $user->hasPermission('admins.view', $organization) ? $organization->users()->wherePivot('is_admin', true)->orderBy('name')->get() : collect();
        $roles = $user->hasPermission('roles.view', $organization) ? \App\Models\Role::where('organization_id', $organization->id)->with('permissions')->orderBy('name')->get() : collect();
        $permissions = $user->hasPermission('roles.view', $organization) ? \App\Models\Permission::where('scope', 'organization')->orderBy('key')->get() : collect();
        $members = $user->hasPermission('members.view', $organization) ? $organization->users()->orderBy('name')->get() : collect();
        $assignments = $user->hasPermission('assignments.view', $organization) ? DB::table('organization_role_user')->where('organization_id', $organization->id)->get()->groupBy('user_id') : collect();
        $headCandidates = $user->hasPermission('units.heads.view', $organization) && $user->hasPermission('employees.view', $organization)
            ? app(EmployeeAccess::class)->filter(Employee::query(), $user, $organization, 'employees.view')->orderBy('last_name')->orderBy('first_name')->get() : collect();
        $unitChanges = $user->hasPermission('units.view', $organization) && $user->hasPermission('units.history.view', $organization)
            ? DB::table('unit_changes')->leftJoin('users','users.id','=','unit_changes.actor_id')->where('unit_changes.organization_id',$organization->id)
                ->when(! $user->hasPermission('units.heads.view', $organization), fn ($q) => $q->where('unit_changes.action','!=','head_changed'))
                ->orderByDesc('unit_changes.id')->select('unit_changes.*','users.name as actor_name')->limit(100)->get() : collect();
        $chart = []; $stack=[]; $visited=[];
        foreach ($units->whereNull('parent_id')->reverse() as $root) $stack[] = [$root,0];
        while ($stack) {
            [$current,$level] = array_pop($stack); if (isset($visited[$current->id])) continue;
            $visited[$current->id] = true; $chart[] = ['unit'=>$current,'level'=>$level];
            foreach ($units->where('parent_id',$current->id)->reverse() as $child) $stack[] = [$child,$level+1];
        }
        $invitationEvents = $user->hasPermission('invitations.events.view', $organization)
            ? DB::table('invitation_events as events')->join('users as targets','targets.id','=','events.user_id')->leftJoin('users as actors','actors.id','=','events.actor_id')->where('events.organization_id',$organization->id)->orderByDesc('events.id')->limit(100)->get(['events.action','events.created_at','targets.name as target_name','actors.name as actor_name']) : collect();
        return view('organization', compact('organization', 'units', 'admins', 'roles', 'permissions', 'members', 'assignments', 'headCandidates', 'unitChanges', 'chart', 'invitationEvents'));
    }
    public function addAdmin(Request $request, Organization $organization) {
        $this->authorizePermission($request, 'admins.assign', $organization);
        $data=$request->validate(['name'=>'required|string|max:255','email'=>'required|email|max:255']); $newUser=null;
        DB::transaction(function () use ($request,$data,$organization,&$newUser) {
            $user=User::where('email',$data['email'])->first();
            if (! $user) {
                $this->authorizePermission($request,'members.accounts.create',$organization); $this->authorizePermission($request,'invitations.send',$organization);
                $user=User::create(['name'=>$data['name'],'email'=>$data['email'],'password'=>\Illuminate\Support\Str::random(64)]);
                $user->must_set_password=true; $user->save(); $newUser=$user;
            }
            $organization->users()->syncWithoutDetaching([$user->id=>['is_admin'=>true]]);
        });
        if ($newUser && ! app(\App\Services\AccountInvitations::class)->send($organization,$newUser,$request->user())) return back()->with('status','Administrator created, but the invitation failed. Check mail configuration and resend it.');
        return back()->with('status',$newUser?'Administrator assigned and invitation sent.':'Organization administrator assigned.');
    }
    public function createUnit(Request $request, Organization $organization) {
        $this->authorizePermission($request, 'units.create', $organization);
        $data = $request->validate(['name' => 'required|string|max:255', 'parent_id' => ['nullable', Rule::exists('units', 'id')->where('organization_id', $organization->id)]]);
        DB::transaction(function () use ($organization, $request, $data) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $unit = $organization->units()->create($data);
            $parent = isset($data['parent_id']) ? $organization->units()->whereKey($data['parent_id'])->value('name') : null;
            $this->recordUnitChange($request, $organization, $unit, 'created', null, $parent);
        });
        return back()->with('status', 'Unit created.');
    }
    public function updateUnit(Request $request, Organization $organization, Unit $unit) {
        $this->authorizePermission($request, 'units.update', $organization); abort_unless($unit->organization_id === $organization->id, 404);
        $data = $request->validate(['name' => 'required|string|max:255', 'parent_id' => ['nullable', Rule::exists('units', 'id')->where('organization_id', $organization->id)]]);
        DB::transaction(function () use ($organization, $unit, $data, $request) {
            // Serialize hierarchy changes for this tenant to prevent concurrent cycles.
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $cursor = $data['parent_id'] ?? null; $visited = [];
            while ($cursor !== null) {
                if ((int) $cursor === $unit->id || isset($visited[$cursor])) throw ValidationException::withMessages(['parent_id' => 'A unit cannot be placed inside itself or its descendants.']);
                $visited[$cursor] = true;
                $cursor = $organization->units()->whereKey($cursor)->value('parent_id');
            }
            $unit->refresh();
            $oldParent=$unit->parent_id === null ? null : $organization->units()->whereKey($unit->parent_id)->value('name');
            $newParent=empty($data['parent_id']) ? null : $organization->units()->whereKey($data['parent_id'])->value('name');
            $oldName=$unit->name; $oldParentId=$unit->parent_id;
            $unit->update($data);
            if ($oldName !== $unit->name) $this->recordUnitChange($request,$organization,$unit,'renamed',$oldName,$unit->name);
            if ((string)$oldParentId !== (string)$unit->parent_id) $this->recordUnitChange($request,$organization,$unit,'moved',$oldParent,$newParent);
        });
        return back()->with('status', 'Unit updated.');
    }
    private function recordUnitChange(Request $request, Organization $organization, Unit $unit, string $action, ?string $before, ?string $after): void {
        DB::table('unit_changes')->insert(['organization_id'=>$organization->id,'unit_id'=>$unit->id,'actor_id'=>$request->user()->id,'action'=>$action,'unit_name'=>$unit->name,'before_value'=>$before,'after_value'=>$after,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function assignUnitHead(Request $request, Organization $organization, Unit $unit) {
        $this->authorizePermission($request,'units.heads.assign',$organization);
        abort_unless((int)$unit->organization_id === (int)$organization->id,404);
        $data=$request->validate(['head_employee_id'=>['nullable','integer',Rule::exists('employees','id')->where('organization_id',$organization->id)]]);
        DB::transaction(function () use ($request,$organization,$unit,$data) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail(); $unit->refresh();
            $headId=$data['head_employee_id'] ?? null;
            if ((string)$unit->head_employee_id === (string)$headId) return;
            if ($headId !== null) {
                $head=Employee::where('organization_id',$organization->id)->whereKey($headId)->firstOrFail();
                abort_unless(app(EmployeeAccess::class)->allows($request->user(),$organization,'employees.view',$head),403);
            }
            $old=$unit->head_employee_id; $unit->head_employee_id=$headId; $unit->save();
            $this->recordUnitChange($request,$organization,$unit,'head_changed',$old === null ? null : (string)$old,$headId === null ? null : (string)$headId);
        });
        return back()->with('status','Unit head updated.');
    }
    public function resetBranding(Request $request, Organization $organization) {
        $this->authorizePermission($request, 'branding.update', $organization);
        $organization->update(['primary_color'=>'#2459a6','accent_color'=>'#12856f']);
        return back()->with('status','Theme colors restored to defaults.');
    }
    public function branding(Request $request, Organization $organization) {
        $this->authorizePermission($request, 'branding.update', $organization);
        $data = $request->validate(['primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048', 'remove_logo' => 'nullable|boolean']);
        $old = $organization->logo_path; $new = null;
        if ($request->hasFile('logo')) $new = $request->file('logo')->store('organization-logos/'.$organization->id, 'public');
        $organization->update(['primary_color' => $data['primary_color'], 'accent_color' => $data['accent_color'], 'logo_path' => $new ?? (!empty($data['remove_logo']) ? null : $old)]);
        if ($old && ($new || !empty($data['remove_logo']))) Storage::disk('public')->delete($old);
        return back()->with('status', 'Branding saved.');
    }
}
