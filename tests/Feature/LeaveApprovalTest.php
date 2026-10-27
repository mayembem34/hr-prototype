<?php
namespace Tests\Feature;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class LeaveApprovalTest extends TestCase {
    use RefreshDatabase;
    private function user(Organization $org,bool $admin=false): User { $u=User::factory()->create();$org->users()->attach($u,['is_admin'=>$admin]);return $u; }
    private function employee(Organization $org,User $u,?Employee $manager=null): Employee { $e=new Employee(['employee_number'=>'E'.$u->id,'first_name'=>'Name'.$u->id,'last_name'=>'Person','status'=>'active']);$e->organization_id=$org->id;$e->user_id=$u->id;$e->manager_id=$manager?->id;$e->save();return $e; }
    private function grant(Organization $org,User $user,array $keys): void { $role=new Role(['name'=>'Role '.Role::count()]);$role->organization_id=$org->id;$role->save();$role->permissions()->sync(Permission::whereIn('key',$keys)->pluck('id'));DB::table('organization_role_user')->insert(['organization_id'=>$org->id,'role_id'=>$role->id,'user_id'=>$user->id]); }
    private function type(Organization $org): LeaveType { $type=new LeaveType(['name'=>'Annual','annual_days'=>20]);$type->organization_id=$org->id;$type->save();return $type; }
    private function requestLeave(Organization $org,User $user,LeaveType $type): LeaveRequest { $this->actingAs($user)->post('/organizations/'.$org->id.'/my-leave',['leave_type_id'=>$type->id,'start_date'=>'2027-06-01','end_date'=>'2027-06-02'])->assertRedirect(); return LeaveRequest::where('organization_id',$org->id)->firstOrFail(); }
    public function test_supervisor_then_multiple_officers_in_configured_order_and_orgwide_history(): void {
        $org=Organization::create(['name'=>'Org']);$admin=$this->user($org,true);$supervisor=$this->user($org);$first=$this->user($org);$second=$this->user($org);$staff=$this->user($org);$watcher=$this->user($org);
        $supervisorEmployee=$this->employee($org,$supervisor);$employee=$this->employee($org,$staff,$supervisorEmployee);
        $this->grant($org,$supervisor,['leave.requests.supervisor_approve']);$this->grant($org,$first,['leave.requests.officer_approve']);$this->grant($org,$second,['leave.requests.officer_approve']);$this->grant($org,$watcher,['leave.requests.view_all']);
        $base='/organizations/'.$org->id; $this->actingAs($admin)->post($base.'/leave-officers',['user_id'=>$second->id,'position'=>2])->assertRedirect();$this->actingAs($admin)->post($base.'/leave-officers',['user_id'=>$first->id,'position'=>1])->assertRedirect();
        $leave=$this->requestLeave($org,$staff,$this->type($org));$decision=$base.'/leave-requests/'.$leave->id.'/decision';
        $this->assertSame('pending_supervisor',$leave->fresh()->status);$this->actingAs($first)->post($decision,['decision'=>'approved'])->assertForbidden();
        $this->actingAs($supervisor)->post($decision,['decision'=>'approved','decision_note'=>'Supervisor agrees'])->assertRedirect();$this->assertSame('pending_officer',$leave->fresh()->status);
        $this->actingAs($second)->post($decision,['decision'=>'approved'])->assertForbidden();$this->actingAs($first)->post($decision,['decision'=>'approved','decision_note'=>'First agrees'])->assertRedirect();$this->assertSame('pending_officer',$leave->fresh()->status);
        $this->actingAs($second)->post($decision,['decision'=>'approved','decision_note'=>'Final agrees'])->assertRedirect();$this->assertSame('approved',$leave->fresh()->status);
        $this->actingAs($watcher)->get($base.'/leave-requests')->assertOk()->assertSee('Supervisor agrees')->assertSee('First agrees')->assertSee('Final agrees');
        $this->assertDatabaseCount('leave_approval_events',4);$this->assertSame($employee->id,$leave->employee_id);
    }
    public function test_rejection_stops_route_and_skips_remaining_officers(): void {
        $org=Organization::create(['name'=>'Org']);$admin=$this->user($org,true);$supervisor=$this->user($org);$officer=$this->user($org);$staff=$this->user($org);$manager=$this->employee($org,$supervisor);$this->employee($org,$staff,$manager);
        $this->grant($org,$supervisor,['leave.requests.supervisor_approve']);$this->grant($org,$officer,['leave.requests.officer_approve']);
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/leave-officers',['user_id'=>$officer->id,'position'=>1])->assertRedirect();$leave=$this->requestLeave($org,$staff,$this->type($org));
        $this->actingAs($supervisor)->post('/organizations/'.$org->id.'/leave-requests/'.$leave->id.'/decision',['decision'=>'rejected','decision_note'=>'Not available'])->assertRedirect();
        $this->assertSame('rejected',$leave->fresh()->status);$this->assertDatabaseHas('leave_approval_steps',['leave_request_id'=>$leave->id,'user_id'=>$officer->id,'status'=>'skipped']);
    }
    public function test_supervisor_is_required_and_no_officers_means_supervisor_is_final(): void {
        $org=Organization::create(['name'=>'Org']);$staff=$this->user($org);$employee=$this->employee($org,$staff);$type=$this->type($org);$url='/organizations/'.$org->id.'/my-leave';
        $this->actingAs($staff)->post($url,['leave_type_id'=>$type->id,'start_date'=>'2027-07-01','end_date'=>'2027-07-01'])->assertSessionHasErrors('start_date');
        $supervisor=$this->user($org);$manager=$this->employee($org,$supervisor);$employee->manager_id=$manager->id;$employee->save();$this->grant($org,$supervisor,['leave.requests.supervisor_approve']);
        $leave=$this->requestLeave($org,$staff,$type);$this->actingAs($supervisor)->post('/organizations/'.$org->id.'/leave-requests/'.$leave->id.'/decision',['decision'=>'approved'])->assertRedirect();$this->assertSame('approved',$leave->fresh()->status);
    }
    public function test_officer_configuration_is_separate_from_permission_and_snapshotted(): void {
        $org=Organization::create(['name'=>'Org']);$admin=$this->user($org,true);$supervisor=$this->user($org);$officer=$this->user($org);$staff=$this->user($org);$manager=$this->employee($org,$supervisor);$this->employee($org,$staff,$manager);$base='/organizations/'.$org->id;
        $this->actingAs($admin)->post($base.'/leave-officers',['user_id'=>$officer->id,'position'=>1])->assertSessionHasErrors('user_id');$this->grant($org,$officer,['leave.requests.officer_approve']);
        $this->actingAs($admin)->post($base.'/leave-officers',['user_id'=>$officer->id,'position'=>1])->assertRedirect();$leave=$this->requestLeave($org,$staff,$this->type($org));
        $this->actingAs($admin)->delete($base.'/leave-officers/'.$officer->id)->assertRedirect();$this->assertDatabaseHas('leave_approval_steps',['leave_request_id'=>$leave->id,'user_id'=>$officer->id,'status'=>'queued']);
    }
    public function test_admin_can_add_officer_to_a_pending_application(): void {
        $org=Organization::create(['name'=>'Org']); $admin=$this->user($org,true); $supervisor=$this->user($org); $officer=$this->user($org); $staff=$this->user($org); $manager=$this->employee($org,$supervisor); $this->employee($org,$staff,$manager);
        $this->grant($org,$supervisor,['leave.requests.supervisor_approve']); $this->grant($org,$officer,['leave.requests.officer_approve']);
        $leave=$this->requestLeave($org,$staff,$this->type($org)); $base='/organizations/'.$org->id.'/leave-requests/'.$leave->id;
        $this->actingAs($admin)->post($base.'/officers',['user_id'=>$officer->id])->assertRedirect();
        $this->actingAs($supervisor)->post($base.'/decision',['decision'=>'approved'])->assertRedirect(); $this->assertSame('pending_officer',$leave->fresh()->status);
        $this->actingAs($officer)->post($base.'/decision',['decision'=>'approved'])->assertRedirect(); $this->assertSame('approved',$leave->fresh()->status);
        $this->assertDatabaseHas('leave_approval_events',['leave_request_id'=>$leave->id,'decision'=>'officer_added']);
    }
}
