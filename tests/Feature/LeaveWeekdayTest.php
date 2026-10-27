<?php
namespace Tests\Feature;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class LeaveWeekdayTest extends TestCase {
    use RefreshDatabase;
    private function setupEmployee(): array {
        $org=Organization::create(['name'=>'Org']);$manager=User::factory()->create();$user=User::factory()->create();$org->users()->attach($manager);$org->users()->attach($user);
        $supervisor=new Employee(['employee_number'=>'M','first_name'=>'Manager','last_name'=>'Test','status'=>'active']);$supervisor->organization_id=$org->id;$supervisor->user_id=$manager->id;$supervisor->save();
        $employee=new Employee(['employee_number'=>'E','first_name'=>'Employee','last_name'=>'Test','status'=>'active']);$employee->organization_id=$org->id;$employee->user_id=$user->id;$employee->manager_id=$supervisor->id;$employee->save();
        $type=new LeaveType(['name'=>'Annual','annual_days'=>10]);$type->organization_id=$org->id;$type->save();return [$org,$user,$type];
    }
    public function test_friday_to_monday_counts_two_weekdays(): void {
        [$org,$user,$type]=$this->setupEmployee();
        $this->actingAs($user)->post('/organizations/'.$org->id.'/my-leave',['leave_type_id'=>$type->id,'start_date'=>'2027-01-08','end_date'=>'2027-01-11'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('leave_requests',['organization_id'=>$org->id,'days'=>2]);
    }
    public function test_weekend_only_request_is_rejected(): void {
        [$org,$user,$type]=$this->setupEmployee();
        $this->actingAs($user)->post('/organizations/'.$org->id.'/my-leave',['leave_type_id'=>$type->id,'start_date'=>'2027-01-09','end_date'=>'2027-01-10'])->assertSessionHasErrors('end_date');
        $this->assertDatabaseCount('leave_requests',0);
    }
    public function test_single_weekday_counts_one_and_old_recorded_days_are_preserved(): void {
        [$org,$user,$type]=$this->setupEmployee();
        $this->actingAs($user)->post('/organizations/'.$org->id.'/my-leave',['leave_type_id'=>$type->id,'start_date'=>'2027-01-01','end_date'=>'2027-01-01'])->assertSessionHasNoErrors();
        $old=LeaveRequest::firstOrFail();$old->days=3;$old->save();
        $this->actingAs($user)->post('/organizations/'.$org->id.'/my-leave',['leave_type_id'=>$type->id,'start_date'=>'2027-01-04','end_date'=>'2027-01-04'])->assertSessionHasNoErrors();
        $this->assertSame(3,$old->fresh()->days);$this->assertDatabaseHas('leave_requests',['start_date'=>'2027-01-04','days'=>1]);
    }
}
