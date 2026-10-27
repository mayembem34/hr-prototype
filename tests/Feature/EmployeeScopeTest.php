<?php
namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeScopeTest extends TestCase {
    use RefreshDatabase;
    private function member(Organization $org): User { $u=User::factory()->create(); $org->users()->attach($u,['is_admin'=>false]); return $u; }
    private function admin(Organization $org): User { $u=User::factory()->create(); $org->users()->attach($u,['is_admin'=>true]); return $u; }
    private function employee(Organization $org, string $number, $unit=null, $user=null): Employee { $e=new Employee(['employee_number'=>$number,'first_name'=>$number,'last_name'=>'Person','status'=>'active','unit_id'=>$unit?->id]); $e->organization_id=$org->id; $e->user_id=$user?->id; $e->save(); return $e; }
    private function grant(Organization $org, User $user, array $grants): Role {
        $role=new Role(['name'=>'Role '.Role::count()]); $role->organization_id=$org->id; $role->save();
        foreach ($grants as [$key,$scope,$unit]) $role->permissions()->attach(Permission::where('key',$key)->firstOrFail()->id,['access_scope'=>$scope,'unit_id'=>$unit?->id]);
        DB::table('organization_role_user')->insert(['organization_id'=>$org->id,'role_id'=>$role->id,'user_id'=>$user->id]); return $role;
    }
    private function payload(Employee $employee, array $override=[]): array { return array_merge(['employee_number'=>$employee->employee_number,'first_name'=>$employee->first_name,'last_name'=>$employee->last_name,'status'=>'active','unit_id'=>$employee->unit_id],$override); }
    public function test_own_scope_filters_lists_and_denies_other_profiles(): void {
        $org=Organization::create(['name'=>'Org']); $user=$this->member($org); $own=$this->employee($org,'Self',null,$user); $other=$this->employee($org,'Other');
        $this->grant($org,$user,[['employees.view','own',null],['employees.update','own',null]]);
        $base='/organizations/'.$org->id.'/employees';
        $this->actingAs($user)->get($base)->assertOk()->assertSee('Self')->assertDontSee('Other');
        $this->actingAs($user)->get($base.'/'.$other->id)->assertForbidden();
        $this->actingAs($user)->post($base.'/'.$other->id,$this->payload($other))->assertForbidden();
        $this->actingAs($user)->post($base.'/'.$own->id,$this->payload($own,['job_title'=>'Updated']))->assertRedirect();
    }
    public function test_unit_tree_includes_descendants_but_unit_scope_does_not(): void {
        $org=Organization::create(['name'=>'Org']); $root=$org->units()->create(['name'=>'Root']); $child=$org->units()->create(['name'=>'Child','parent_id'=>$root->id]); $outside=$org->units()->create(['name'=>'Outside']);
        $top=$this->employee($org,'Top',$root); $nested=$this->employee($org,'Nested',$child); $external=$this->employee($org,'External',$outside);
        $tree=$this->member($org); $this->grant($org,$tree,[['employees.view','unit_tree',$root],['employees.update','unit_tree',$root]]);
        $unitOnly=$this->member($org); $this->grant($org,$unitOnly,[['employees.view','unit',$root]]);
        $base='/organizations/'.$org->id.'/employees';
        $this->actingAs($tree)->get($base)->assertSee('Top')->assertSee('Nested')->assertDontSee('External');
        $this->actingAs($unitOnly)->get($base.'/'.$nested->id)->assertForbidden();
        $this->actingAs($tree)->get($base.'/'.$external->id)->assertForbidden();
        $this->actingAs($tree)->post($base.'/'.$nested->id,$this->payload($nested,['unit_id'=>$outside->id]))->assertForbidden();
        $this->assertSame($child->id,$nested->fresh()->unit_id);
    }
    public function test_different_roles_combine_but_document_scope_is_checked_independently(): void {
        Storage::fake('local'); $org=Organization::create(['name'=>'Org']); $admin=$this->admin($org); $root=$org->units()->create(['name'=>'Root']); $child=$org->units()->create(['name'=>'Child','parent_id'=>$root->id]);
        $u=$this->member($org); $own=$this->employee($org,'Self',$root,$u); $other=$this->employee($org,'Other',$child);
        $this->grant($org,$u,[['employees.view','unit_tree',$root],['documents.view','unit_tree',$root]]); $this->grant($org,$u,[['documents.download','own',null]]);
        $base='/organizations/'.$org->id.'/employees/'.$other->id.'/documents';
        $this->actingAs($admin)->post($base,['title'=>'Contract','category'=>'Contract','file'=>UploadedFile::fake()->createWithContent('a.pdf',"%PDF-1.4\n%%EOF")])->assertRedirect();
        $doc=DB::table('employee_documents')->where('employee_id',$other->id)->first();
        $this->actingAs($u)->get($base)->assertOk()->assertSee('Contract')->assertDontSee('Download');
        $this->actingAs($u)->get($base.'/'.$doc->id.'/download')->assertForbidden();
    }
    public function test_account_link_requires_permission_and_organization_membership(): void {
        $org=Organization::create(['name'=>'Org']); $admin=$this->admin($org); $user=$this->member($org); $employee=$this->employee($org,'Self');
        $outsider=User::factory()->create(); $base='/organizations/'.$org->id.'/employees/'.$employee->id.'/account';
        $this->actingAs($user)->post($base,['user_id'=>$user->id])->assertForbidden();
        $this->actingAs($admin)->post($base,['user_id'=>$outsider->id])->assertSessionHasErrors('user_id');
        $this->actingAs($admin)->post($base,['user_id'=>$user->id])->assertRedirect();
        $this->assertSame($user->id,$employee->fresh()->user_id);
    }
    public function test_scoped_manager_cannot_grant_organization_wide_access(): void {
        $org=Organization::create(['name'=>'Org']); $root=$org->units()->create(['name'=>'Root']); $user=$this->member($org);
        $this->grant($org,$user,[['roles.create','organization',null],['employees.view','unit',$root]]);
        $permission=Permission::where('key','employees.view')->value('id');
        $this->actingAs($user)->post('/organizations/'.$org->id.'/roles',['name'=>'Escalation','permissions'=>[$permission]])->assertForbidden();
    }
    public function test_role_configuration_can_select_scopes_but_not_foreign_units(): void {
        $org=Organization::create(['name'=>'Org']); $other=Organization::create(['name'=>'Other']); $admin=$this->admin($org); $unit=$org->units()->create(['name'=>'Own']); $foreign=$other->units()->create(['name'=>'Foreign']); $id=Permission::where('key','employees.view')->value('id');
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/roles',['name'=>'Bad','permissions'=>[$id],'scopes'=>[$id=>'unit'],'scope_units'=>[$id=>$foreign->id]])->assertSessionHasErrors('scope_units');
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/roles',['name'=>'Good','permissions'=>[$id],'scopes'=>[$id=>'unit_tree'],'scope_units'=>[$id=>$unit->id]])->assertRedirect();
        $role=Role::where('name','Good')->firstOrFail(); $this->assertDatabaseHas('permission_role',['role_id'=>$role->id,'permission_id'=>$id,'access_scope'=>'unit_tree','unit_id'=>$unit->id]);
    }
}
