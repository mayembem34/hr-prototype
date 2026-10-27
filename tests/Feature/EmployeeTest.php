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

class EmployeeTest extends TestCase {
    use RefreshDatabase;
    private function admin(Organization $org): User { $u = User::factory()->create(); $org->users()->attach($u, ['is_admin'=>true]); return $u; }
    private function member(Organization $org, array $keys): User {
        $u = User::factory()->create(); $org->users()->attach($u, ['is_admin'=>false]);
        $role = new Role(['name'=>'Read role']); $role->organization_id = $org->id; $role->save();
        $role->permissions()->sync(Permission::whereIn('key',$keys)->pluck('id'));
        DB::table('organization_role_user')->insert(['organization_id'=>$org->id,'role_id'=>$role->id,'user_id'=>$u->id]); return $u;
    }
    private function payload(array $overrides = []): array { return array_merge(['employee_number'=>'E01','first_name'=>'Alice','last_name'=>'Example','status'=>'active'], $overrides); }
    private function employee(Organization $org, string $number): Employee {
        $e = new Employee($this->payload(['employee_number'=>$number])); $e->organization_id=$org->id; $e->save(); return $e;
    }
    public function test_employee_permissions_are_in_default_catalogue(): void {
        foreach (['employees.view','employees.create','employees.update','employees.history.view'] as $key) $this->assertDatabaseHas('permissions',['key'=>$key]);
    }
    public function test_admin_creates_and_updates_employee_and_records_history(): void {
        $org=Organization::create(['name'=>'A']); $admin=$this->admin($org); $unit=$org->units()->create(['name'=>'IT']);
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/employees', $this->payload(['unit_id'=>$unit->id,'job_title'=>'Developer']))->assertRedirect();
        $employee=Employee::where('organization_id',$org->id)->firstOrFail();
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/employees/'.$employee->id, $this->payload(['unit_id'=>$unit->id,'job_title'=>'Lead']))->assertRedirect();
        $this->assertSame('Lead',$employee->fresh()->job_title);
        $this->assertDatabaseCount('employee_changes',2);
        $this->actingAs($admin)->get('/organizations/'.$org->id.'/employees/'.$employee->id)->assertOk()->assertSee('Change history');
    }
    public function test_employee_reader_cannot_edit_or_see_history(): void {
        $org=Organization::create(['name'=>'A']); $reader=$this->member($org,['employees.view']); $employee=$this->employee($org,'E1');
        $this->actingAs($reader)->get('/organizations/'.$org->id.'/employees/'.$employee->id)->assertOk()->assertDontSee('Change history')->assertDontSee('Save changes');
        $this->actingAs($reader)->post('/organizations/'.$org->id.'/employees/'.$employee->id,$this->payload())->assertForbidden();
    }
    public function test_cross_organization_employee_unit_and_manager_are_rejected(): void {
        $a=Organization::create(['name'=>'A']); $b=Organization::create(['name'=>'B']); $admin=$this->admin($a);
        $foreignEmployee=$this->employee($b,'B1'); $unit=$b->units()->create(['name'=>'Foreign']);
        $this->actingAs($admin)->get('/organizations/'.$a->id.'/employees/'.$foreignEmployee->id)->assertNotFound();
        $this->actingAs($admin)->post('/organizations/'.$a->id.'/employees',$this->payload(['manager_id'=>$foreignEmployee->id,'unit_id'=>$unit->id]))->assertSessionHasErrors(['manager_id','unit_id']);
        $this->assertDatabaseMissing('employees',['organization_id'=>$a->id,'employee_number'=>'E01']);
    }
    public function test_reporting_cycles_are_rejected_without_changing_history(): void {
        $org=Organization::create(['name'=>'A']); $admin=$this->admin($org); $first=$this->employee($org,'E1'); $second=$this->employee($org,'E2'); $second->update(['manager_id'=>$first->id]);
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/employees/'.$first->id,$this->payload(['employee_number'=>'E1','manager_id'=>$second->id]))->assertSessionHasErrors('manager_id');
        $this->assertNull($first->fresh()->manager_id); $this->assertDatabaseCount('employee_changes',0);
    }
    public function test_numbers_are_unique_within_each_organization(): void {
        $org=Organization::create(['name'=>'A']); $admin=$this->admin($org); $this->employee($org,'E01');
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/employees',$this->payload())->assertSessionHasErrors('employee_number');
        $other=Organization::create(['name'=>'B']); $otherAdmin=$this->admin($other);
        $this->actingAs($otherAdmin)->post('/organizations/'.$other->id.'/employees',$this->payload())->assertRedirect();
        $this->assertDatabaseHas('employees',['organization_id'=>$other->id,'employee_number'=>'E01']);
    }
}
