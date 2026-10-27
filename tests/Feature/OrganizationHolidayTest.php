<?php
namespace Tests\Feature;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\OrganizationHoliday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class OrganizationHolidayTest extends TestCase {
    use RefreshDatabase;
    private function admin(Organization $org): User { $u=User::factory()->create();$org->users()->attach($u,['is_admin'=>true]);return $u; }
    private function employee(Organization $org): User {
        $manager=User::factory()->create();$user=User::factory()->create();$org->users()->attach($manager);$org->users()->attach($user);
        $m=new Employee(['employee_number'=>'M','first_name'=>'Manager','last_name'=>'Test','status'=>'active']);$m->organization_id=$org->id;$m->user_id=$manager->id;$m->save();
        $e=new Employee(['employee_number'=>'E','first_name'=>'Employee','last_name'=>'Test','status'=>'active']);$e->organization_id=$org->id;$e->user_id=$user->id;$e->manager_id=$m->id;$e->save();return $user;
    }
    private function type(Organization $org): LeaveType { $type=new LeaveType(['name'=>'Annual','annual_days'=>20]);$type->organization_id=$org->id;$type->save();return $type; }
    private function holiday(Organization $org,string $date): OrganizationHoliday { $h=new OrganizationHoliday(['name'=>'Holiday','date'=>$date]);$h->organization_id=$org->id;$h->save();return $h; }
    public function test_only_the_organizations_holidays_are_excluded(): void {
        $a=Organization::create(['name'=>'A']);$b=Organization::create(['name'=>'B']);$userA=$this->employee($a);$userB=$this->employee($b);$typeA=$this->type($a);$typeB=$this->type($b);$this->holiday($a,'2027-01-11');
        foreach ([[$a,$userA,$typeA],[$b,$userB,$typeB]] as [$org,$user,$type]) $this->actingAs($user)->post('/organizations/'.$org->id.'/my-leave',['leave_type_id'=>$type->id,'start_date'=>'2027-01-08','end_date'=>'2027-01-11'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('leave_requests',['organization_id'=>$a->id,'days'=>1]);$this->assertDatabaseHas('leave_requests',['organization_id'=>$b->id,'days'=>2]);
    }
    public function test_holiday_only_request_is_rejected(): void {
        $org=Organization::create(['name'=>'A']);$user=$this->employee($org);$type=$this->type($org);$this->holiday($org,'2027-01-11');
        $this->actingAs($user)->post('/organizations/'.$org->id.'/my-leave',['leave_type_id'=>$type->id,'start_date'=>'2027-01-11','end_date'=>'2027-01-11'])->assertSessionHasErrors('end_date');$this->assertDatabaseCount('leave_requests',0);
    }
    public function test_authorization_isolation_and_duplicate_dates(): void {
        $a=Organization::create(['name'=>'A']);$b=Organization::create(['name'=>'B']);$admin=$this->admin($a);$member=$this->employee($a);$foreign=$this->holiday($b,'2027-01-11');
        $this->actingAs($member)->post('/organizations/'.$a->id.'/holidays',['name'=>'Test','date'=>'2027-01-11'])->assertForbidden();
        $this->actingAs($admin)->post('/organizations/'.$a->id.'/holidays/'.$foreign->id,['name'=>'Changed','date'=>'2027-01-12'])->assertNotFound();
        $this->actingAs($admin)->post('/organizations/'.$a->id.'/holidays',['name'=>'Test','date'=>'2027-01-11'])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($admin)->post('/organizations/'.$a->id.'/holidays',['name'=>'Duplicate','date'=>'2027-01-11'])->assertSessionHasErrors('date');
        $this->actingAs($admin)->get('/organizations/'.$a->id.'/holidays')->assertOk()->assertSee('Test')->assertDontSee('Changed');
    }
    public function test_calendar_change_does_not_recalculate_recorded_leave(): void {
        $org=Organization::create(['name'=>'A']);$admin=$this->admin($org);$user=$this->employee($org);$type=$this->type($org);
        $this->actingAs($user)->post('/organizations/'.$org->id.'/my-leave',['leave_type_id'=>$type->id,'start_date'=>'2027-01-08','end_date'=>'2027-01-11'])->assertSessionHasNoErrors();$leave=LeaveRequest::firstOrFail();$this->assertSame(2,$leave->days);
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/holidays',['name'=>'Later configured','date'=>'2027-01-11'])->assertSessionHasNoErrors();$this->assertSame(2,$leave->fresh()->days);
    }
}
