<?php
namespace Tests\Feature;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class DailyOperationsTest extends TestCase {
 use RefreshDatabase;
 private function member(Organization $o,bool $admin=false): User {$u=User::factory()->create();$o->users()->attach($u,['is_admin'=>$admin]);return $u;}
 private function employee(Organization $o,User $u,$unit=null,?Employee $manager=null): Employee {$e=new Employee(['employee_number'=>'E'.$u->id,'first_name'=>'Name'.$u->id,'last_name'=>'Test','status'=>'active','unit_id'=>$unit?->id]);$e->organization_id=$o->id;$e->user_id=$u->id;$e->manager_id=$manager?->id;$e->save();return $e;}
 private function grant(Organization $o,User $u,array $keys,$unit=null): void {$role=new Role(['name'=>'Role'.$u->id]);$role->organization_id=$o->id;$role->save();foreach($keys as $key)$role->permissions()->attach(Permission::where('key',$key)->value('id'),['access_scope'=>$unit?'unit':'organization','unit_id'=>$unit?->id]);DB::table('organization_role_user')->insert(['organization_id'=>$o->id,'role_id'=>$role->id,'user_id'=>$u->id]);}
 public function test_clock_in_out_and_scoped_correction(): void {
  $o=Organization::create(['name'=>'One']);$unit=$o->units()->create(['name'=>'Inside']);$other=$o->units()->create(['name'=>'Outside']);$staff=$this->member($o);$e=$this->employee($o,$staff,$unit);$outsider=$this->member($o);$this->employee($o,$outsider,$other);$manager=$this->member($o);$this->grant($o,$manager,['attendance.records.view','attendance.records.correct','attendance.history.view'],$unit);$base='/organizations/'.$o->id;
  $this->actingAs($staff)->post($base.'/attendance/clock-in')->assertRedirect();$this->post($base.'/attendance/clock-in')->assertSessionHasErrors('attendance');$this->post($base.'/attendance/clock-out')->assertRedirect();$this->post($base.'/attendance/clock-out')->assertSessionHasErrors('attendance');
  $this->actingAs($outsider)->post($base.'/attendance/clock-in')->assertRedirect();$id=DB::table('attendance_entries')->where('employee_id',$e->id)->value('id');$outsideId=DB::table('attendance_entries')->where('employee_id','!=',$e->id)->value('id');
  $this->actingAs($manager)->get($base.'/attendance')->assertOk()->assertSee('Name'.$staff->id)->assertDontSee('Name'.$outsider->id);
  $this->post($base.'/attendance/'.$outsideId,['clock_in'=>'2026-10-01T08:00','clock_out'=>'2026-10-01T17:00','reason'=>'Correct'])->assertForbidden();
  $this->post($base.'/attendance/'.$id,['clock_in'=>'2026-10-01T08:00','clock_out'=>'2026-10-01T17:00','reason'=>'Correct'])->assertRedirect();$this->assertDatabaseHas('attendance_events',['attendance_entry_id'=>$id,'action'=>'corrected','reason'=>'Correct']);
  $this->actingAs($staff)->post($base.'/attendance/'.$id,['clock_in'=>'2026-10-01T08:00','clock_out'=>'2026-10-01T17:00','reason'=>'No'])->assertForbidden();
 }
 public function test_leave_notifications_are_private_and_membership_scoped(): void {
  $o=Organization::create(['name'=>'One']);$supervisor=$this->member($o);$staff=$this->member($o);$manager=$this->employee($o,$supervisor);$this->employee($o,$staff,null,$manager);$this->grant($o,$supervisor,['leave.requests.supervisor_approve']);$type=new LeaveType(['name'=>'Annual','annual_days'=>20]);$type->organization_id=$o->id;$type->save();$base='/organizations/'.$o->id;
  $this->actingAs($staff)->post($base.'/my-leave',['leave_type_id'=>$type->id,'start_date'=>'2027-06-01','end_date'=>'2027-06-02'])->assertRedirect();$leave=DB::table('leave_requests')->first();$notice=DB::table('daily_notifications')->where('user_id',$supervisor->id)->first();$this->assertNotNull($notice);
  $this->actingAs($staff)->post($base.'/daily-notifications/'.$notice->id.'/read')->assertNotFound();$this->actingAs($supervisor)->post($base.'/leave-requests/'.$leave->id.'/decision',['decision'=>'approved'])->assertRedirect();$this->assertDatabaseHas('daily_notifications',['organization_id'=>$o->id,'user_id'=>$staff->id]);$this->actingAs($staff)->get($base.'/daily-notifications')->assertOk()->assertSee('approved');
  DB::table('organization_user')->where('organization_id',$o->id)->where('user_id',$staff->id)->update(['is_active'=>false]);$this->get($base.'/daily-notifications')->assertForbidden();
 }
}
