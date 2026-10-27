<?php
namespace App\Http\Controllers;

use App\Models\Organization;
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
        $request->session()->regenerate(); return redirect()->intended(route('home'));
    }
    public function logout(Request $request) { $this->authorizePermission($request, 'session.logout'); Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login'); }
    public function index(Request $request) {
        $this->authorizePermission($request, 'organizations.list');
        $organizations = $request->user()->is_platform_admin ? Organization::orderBy('name')->get() : $request->user()->organizations()->orderBy('name')->get();
        $organizations = $organizations->filter(fn ($org) => $request->user()->hasPermission('organization.view', $org));
        return view('home', compact('organizations'));
    }
    public function createOrganization(Request $request) {
        $this->authorizePermission($request, 'organizations.create');
        $data = $request->validate(['name' => 'required|string|max:255']);
        $organization = Organization::create($data); return redirect()->route('organizations.show', $organization);
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
        return view('organization', compact('organization', 'units', 'admins', 'roles', 'permissions', 'members', 'assignments'));
    }
    public function addAdmin(Request $request, Organization $organization) {
        $this->authorizePermission($request, 'admins.assign', $organization);
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:255', 'password' => 'nullable|string|min:12']);
        DB::transaction(function () use ($data, $organization) {
            $user = User::where('email', $data['email'])->first();
            if (! $user) {
                if (empty($data['password'])) throw ValidationException::withMessages(['password' => 'A password is required for a new user.']);
                $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            }
            $organization->users()->syncWithoutDetaching([$user->id => ['is_admin' => true]]);
        });
        return back()->with('status', 'Organization administrator assigned.');
    }
    public function createUnit(Request $request, Organization $organization) {
        $this->authorizePermission($request, 'units.create', $organization);
        $data = $request->validate(['name' => 'required|string|max:255', 'parent_id' => ['nullable', Rule::exists('units', 'id')->where('organization_id', $organization->id)]]);
        $organization->units()->create($data); return back()->with('status', 'Unit created.');
    }
    public function updateUnit(Request $request, Organization $organization, Unit $unit) {
        $this->authorizePermission($request, 'units.update', $organization); abort_unless($unit->organization_id === $organization->id, 404);
        $data = $request->validate(['name' => 'required|string|max:255', 'parent_id' => ['nullable', Rule::exists('units', 'id')->where('organization_id', $organization->id)]]);
        DB::transaction(function () use ($organization, $unit, $data) {
            // Serialize hierarchy changes for this tenant to prevent concurrent cycles.
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $cursor = $data['parent_id'] ?? null; $visited = [];
            while ($cursor !== null) {
                if ((int) $cursor === $unit->id || isset($visited[$cursor])) throw ValidationException::withMessages(['parent_id' => 'A unit cannot be placed inside itself or its descendants.']);
                $visited[$cursor] = true;
                $cursor = $organization->units()->whereKey($cursor)->value('parent_id');
            }
            $unit->update($data);
        });
        return back()->with('status', 'Unit updated.');
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
