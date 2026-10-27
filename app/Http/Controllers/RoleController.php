<?php
namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller {
    private function authorizeOp(Request $request, Organization $organization, string $permission): void {
        abort_unless($request->user()->hasPermission($permission, $organization), 403);
    }
    private function ownedRole(Organization $org, Role $role): void { abort_unless((int)$role->organization_id === (int)$org->id, 404); }
    private function canGrant(Request $request, Organization $org, $permissions): void {
        foreach ($permissions as $permission) {
            if ($permission->scope !== 'organization' || ! $request->user()->hasOrganizationPermission($permission->key, $org)) abort(403, 'You cannot manage permissions beyond your own access.');
        }
    }
    private function validateRole(Request $request, Organization $organization, ?Role $role = null): array {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->where('organization_id', $organization->id)->ignore($role?->id)],
            'permissions' => 'array',
            'permissions.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')->where('scope', 'organization')],
        ]);
    }
    private function scopeAssignments(Request $request, Organization $organization, $permissions): array {
        $data = $request->validate(['scopes'=>'array', 'scope_units'=>'array']);
        $out=[];
        foreach ($permissions as $permission) {
            $scope=$data['scopes'][$permission->id] ?? 'organization';
            if (! in_array($scope, ['organization','own','unit','unit_tree'], true) || ($scope !== 'organization' && ! \App\Services\EmployeeAccess::scopedPermission($permission->key))) throw ValidationException::withMessages(['scopes'=>'Invalid permission scope.']);
            $unit=null;
            if (in_array($scope, ['unit','unit_tree'], true)) {
                $unit=$data['scope_units'][$permission->id] ?? null;
                if (! is_scalar($unit) || ! ctype_digit((string)$unit) || ! $organization->units()->whereKey($unit)->exists()) throw ValidationException::withMessages(['scope_units'=>'Select a unit in this organization for each unit scope.']);
                $unit=(int)$unit;
            }
            $out[$permission->id]=['access_scope'=>$scope,'unit_id'=>$unit];
        }
        return $out;
    }
    public function create(Request $request, Organization $organization) {
        $this->authorizeOp($request, $organization, 'roles.create');
        $data = $this->validateRole($request, $organization); $permissions = Permission::whereIn('id', $data['permissions'] ?? [])->get();
        $this->canGrant($request, $organization, $permissions);
        $scopes = $this->scopeAssignments($request, $organization, $permissions);
        DB::transaction(function () use ($organization, $data, $scopes) {
            $role = new Role(['name' => $data['name']]); $role->organization_id = $organization->id; $role->save(); $role->permissions()->sync($scopes);
        });
        return back()->with('status', 'Role created.');
    }
    public function update(Request $request, Organization $organization, Role $role) {
        $this->authorizeOp($request, $organization, 'roles.update'); $this->ownedRole($organization, $role);
        $data = $this->validateRole($request, $organization, $role); $permissions = Permission::whereIn('id', $data['permissions'] ?? [])->get();
        $this->canGrant($request, $organization, $role->permissions); $this->canGrant($request, $organization, $permissions);
        $scopes = $this->scopeAssignments($request, $organization, $permissions);
        DB::transaction(function () use ($role, $data, $scopes) { $role->update(['name' => $data['name']]); $role->permissions()->sync($scopes); });
        return back()->with('status', 'Role updated.');
    }
    public function delete(Request $request, Organization $organization, Role $role) {
        $this->authorizeOp($request, $organization, 'roles.delete'); $this->ownedRole($organization, $role); $this->canGrant($request, $organization, $role->permissions);
        $role->delete(); return back()->with('status', 'Role deleted.');
    }
    public function addMember(Request $request, Organization $organization) {
        $this->authorizeOp($request, $organization, 'members.add');
        $data = $request->validate(['name'=>'required|string|max:255','email'=>'required|email|max:255']);
        $newUser = null;
        DB::transaction(function () use ($data,$organization,$request,&$newUser) {
            $user=User::where('email',$data['email'])->first();
            if (! $user) {
                abort_unless($request->user()->hasPermission('members.accounts.create',$organization) && $request->user()->hasPermission('invitations.send',$organization),403);
                $user=User::create(['name'=>$data['name'],'email'=>$data['email'],'password'=>\Illuminate\Support\Str::random(64)]);
                $user->must_set_password=true; $user->save(); $newUser=$user;
            }
            $existing=DB::table('organization_user')->where('organization_id',$organization->id)->where('user_id',$user->id)->first();
            if($existing && !$existing->is_active) { abort_unless($request->user()->hasPermission('members.reactivate',$organization),403);DB::table('organization_user')->where('id',$existing->id)->update(['is_active'=>true,'is_admin'=>false,'updated_at'=>now()]); }
            elseif(!$existing)$organization->users()->attach($user->id,['is_admin'=>false]);
        });
        if ($newUser && ! app(\App\Services\AccountInvitations::class)->send($organization,$newUser,$request->user())) return back()->with('status','Account created, but the invitation could not be sent. Check mail configuration and resend it.');
        return back()->with('status',$newUser ? 'Account created and invitation sent.' : 'Existing member added.');
    }
    public function assign(Request $request, Organization $organization, User $member) {
        $this->authorizeOp($request, $organization, 'assignments.update');
        abort_unless($organization->users()->where('users.id', $member->id)->exists(), 404);
        $data = $request->validate(['roles' => 'array', 'roles.*' => ['integer', 'distinct', Rule::exists('roles', 'id')->where('organization_id', $organization->id)]]);
        $selected = Role::where('organization_id', $organization->id)->whereIn('id', $data['roles'] ?? [])->with('permissions')->get();
        $currentIds = DB::table('organization_role_user')->where('organization_id', $organization->id)->where('user_id', $member->id)->pluck('role_id');
        $current = Role::where('organization_id', $organization->id)->whereIn('id', $currentIds)->with('permissions')->get();
        foreach ($current->concat($selected) as $role) $this->canGrant($request, $organization, $role->permissions);
        DB::transaction(function () use ($organization, $member, $selected) {
            DB::table('organization_role_user')->where('organization_id', $organization->id)->where('user_id', $member->id)->delete();
            foreach ($selected as $role) DB::table('organization_role_user')->insert(['organization_id' => $organization->id, 'role_id' => $role->id, 'user_id' => $member->id]);
        });
        return back()->with('status', 'Role assignments saved.');
    }
}
