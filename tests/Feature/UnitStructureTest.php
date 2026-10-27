<?php
namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnitStructureTest extends TestCase {
    use RefreshDatabase;
    private function admin(Organization $org): User { $u=User::factory()->create(); $org->users()->attach($u,['is_admin'=>true]); return $u; }
    private function employee(Organization $org, string $name, $unit=null, $user=null): Employee { $e=new Employee(['employee_number'=>$name,'first_name'=>$name,'last_name'=>'Person','status'=>'active','unit_id'=>$unit?->id]); $e->organization_id=$org->id; $e->user_id=$user?->id; $e->save(); return $e; }
    private function member(Organization $org, array $grants): User {
        $u=User::factory()->create(); $org->users()->attach($u,['is_admin'=>false]); $role=new Role(['name'=>'Test role']); $role->organization_id=$org->id; $role->save();
        foreach ($grants as [$key,$scope,$unit]) $role->permissions()->attach(Permission::where('key',$key)->value('id'),['access_scope'=>$scope,'unit_id'=>$unit?->id]);
        DB::table('organization_role_user')->insert(['organization_id'=>$org->id,'role_id'=>$role->id,'user_id'=>$u->id]); return $u;
    }
    public function test_creating_renaming_moving_and_head_assignment_are_recorded(): void {
        $org=Organization::create(['name'=>'Org']); $admin=$this->admin($org); $parent=$org->units()->create(['name'=>'Parent']); $head=$this->employee($org,'Alice');
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/units',['name'=>'Child'])->assertRedirect(); $unit=$org->units()->where('name','Child')->firstOrFail();
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/units/'.$unit->id,['name'=>'Team','parent_id'=>$parent->id])->assertRedirect();
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/units/'.$unit->id.'/head',['head_employee_id'=>$head->id])->assertRedirect();
        $this->assertDatabaseHas('units',['id'=>$unit->id,'head_employee_id'=>$head->id,'parent_id'=>$parent->id]);
        foreach (['created','renamed','moved','head_changed'] as $action) $this->assertDatabaseHas('unit_changes',['unit_id'=>$unit->id,'actor_id'=>$admin->id,'action'=>$action]);
        $this->actingAs($admin)->get('/organizations/'.$org->id)->assertOk()->assertSee('Organization chart')->assertSee('Alice Person')->assertSee('Unit structure history');
    }
    public function test_head_assignment_requires_permission_and_matching_organization(): void {
        $org=Organization::create(['name'=>'A']); $other=Organization::create(['name'=>'B']); $admin=$this->admin($org); $outsider=$this->employee($other,'Other'); $unit=$org->units()->create(['name'=>'Team']);
        $user=$this->member($org,[['organization.view','organization',null],['units.view','organization',null]]);
        $this->actingAs($user)->post('/organizations/'.$org->id.'/units/'.$unit->id.'/head',['head_employee_id'=>$outsider->id])->assertForbidden();
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/units/'.$unit->id.'/head',['head_employee_id'=>$outsider->id])->assertSessionHasErrors('head_employee_id');
        $this->assertNull($unit->fresh()->head_employee_id);
    }
    public function test_head_view_respects_employee_scope_and_leadership_grants_no_permissions(): void {
        $org=Organization::create(['name'=>'A']); $root=$org->units()->create(['name'=>'Root']); $outside=$org->units()->create(['name'=>'Outside']); $headUser=User::factory()->create(); $org->users()->attach($headUser,['is_admin'=>false]);
        $head=$this->employee($org,'HiddenHead',$outside,$headUser); $root->head_employee_id=$head->id; $root->save();
        $viewer=$this->member($org,[['organization.view','organization',null],['units.view','organization',null],['units.heads.view','organization',null],['employees.view','unit',$root]]);
        $this->actingAs($viewer)->get('/organizations/'.$org->id)->assertOk()->assertSee('Restricted')->assertDontSee('HiddenHead Person');
        $this->assertFalse($headUser->hasPermission('employees.view',$org));
    }
    public function test_history_head_events_are_hidden_without_head_view(): void {
        $org=Organization::create(['name'=>'A']); $admin=$this->admin($org); $unit=$org->units()->create(['name'=>'Root']); $head=$this->employee($org,'SecretHead');
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/units/'.$unit->id.'/head',['head_employee_id'=>$head->id])->assertRedirect();
        $viewer=$this->member($org,[['organization.view','organization',null],['units.view','organization',null],['units.history.view','organization',null]]);
        $this->actingAs($viewer)->get('/organizations/'.$org->id)->assertOk()->assertSee('Unit structure history')->assertDontSee('SecretHead')->assertDontSee('head changed');
    }
    public function test_user_cannot_assign_an_employee_outside_their_employee_view_scope(): void {
        $org=Organization::create(['name'=>'A']); $a=$org->units()->create(['name'=>'A']); $b=$org->units()->create(['name'=>'B']); $candidate=$this->employee($org,'Outside',$b);
        $user=$this->member($org,[['units.heads.assign','organization',null],['employees.view','unit',$a]]);
        $this->actingAs($user)->post('/organizations/'.$org->id.'/units/'.$a->id.'/head',['head_employee_id'=>$candidate->id])->assertForbidden();
        $this->assertNull($a->fresh()->head_employee_id);
    }
}
