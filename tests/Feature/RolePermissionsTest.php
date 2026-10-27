<?php
namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RolePermissionsTest extends TestCase {
    use RefreshDatabase;
    private function org(): Organization { return Organization::create(['name' => 'Test']); }
    private function admin(Organization $org): User { $u = User::factory()->create(); $org->users()->attach($u, ['is_admin' => true]); return $u; }
    private function member(Organization $org, array $keys): User {
        $user = User::factory()->create(); $org->users()->attach($user, ['is_admin' => false]);
        $role = new Role(['name' => 'Role '. $user->id]); $role->organization_id = $org->id; $role->save();
        $role->permissions()->sync(Permission::whereIn('key', $keys)->pluck('id'));
        DB::table('organization_role_user')->insert(['organization_id'=>$org->id,'role_id'=>$role->id,'user_id'=>$user->id]);
        return $user;
    }
    public function test_default_catalogue_exists_and_unknown_permissions_are_denied_even_to_admins(): void {
        $org = $this->org(); $admin = $this->admin($org);
        $this->assertDatabaseHas('permissions', ['key'=>'units.view']);
        $this->assertTrue($admin->hasPermission('units.update', $org));
        $this->assertFalse($admin->hasPermission('units.missing', $org));
        $this->assertFalse($admin->hasPermission('organizations.create'));
    }
    public function test_read_only_member_can_view_but_not_change_units(): void {
        $org = $this->org(); $user = $this->member($org, ['organization.view', 'units.view']); $org->units()->create(['name'=>'HR']);
        $this->actingAs($user)->get('/organizations/'.$org->id)->assertOk()->assertSee('HR')->assertDontSee('Add unit');
        $this->actingAs($user)->post('/organizations/'.$org->id.'/units', ['name'=>'Bad'])->assertForbidden();
    }
    public function test_permission_is_not_valid_in_another_organization(): void {
        $a = $this->org(); $b = Organization::create(['name'=>'B']); $user = $this->member($a, ['organization.view', 'units.create']);
        $b->users()->attach($user, ['is_admin'=>false]);
        $this->actingAs($user)->post('/organizations/'.$b->id.'/units', ['name'=>'Bad'])->assertForbidden();
    }
    public function test_org_admin_can_create_role_and_assign_it_to_member(): void {
        $org = $this->org(); $admin = $this->admin($org); $member = User::factory()->create(); $org->users()->attach($member, ['is_admin'=>false]);
        $id = Permission::where('key','units.view')->value('id');
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/roles', ['name'=>'Reader','permissions'=>[$id]])->assertRedirect();
        $role = Role::where('name','Reader')->firstOrFail();
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/members/'.$member->id.'/roles', ['roles'=>[$role->id]])->assertRedirect();
        $this->assertTrue($member->fresh()->hasPermission('units.view', $org));
        $this->assertFalse($member->fresh()->hasPermission('units.update', $org));
    }
    public function test_delegated_role_manager_cannot_grant_more_than_they_hold(): void {
        $org = $this->org(); $user = $this->member($org, ['organization.view','roles.create']);
        $id = Permission::where('key','units.update')->value('id');
        $this->actingAs($user)->post('/organizations/'.$org->id.'/roles', ['name'=>'Escalation','permissions'=>[$id]])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name'=>'Escalation']);
    }
    public function test_cross_tenant_role_assignment_is_rejected(): void {
        $org = $this->org(); $other = Organization::create(['name'=>'Other']); $admin = $this->admin($org); $user = User::factory()->create(); $org->users()->attach($user);
        $role = new Role(['name'=>'Foreign']); $role->organization_id = $other->id; $role->save();
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/members/'.$user->id.'/roles', ['roles'=>[$role->id]])->assertSessionHasErrors('roles.0');
    }
    public function test_organization_admin_may_assign_another_admin(): void {
        $org = $this->org(); $admin = $this->admin($org); $member = User::factory()->create();
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/admins', ['name'=>$member->name,'email'=>$member->email])->assertRedirect();
        $this->assertTrue($member->fresh()->hasPermission('roles.create', $org));
    }
}
