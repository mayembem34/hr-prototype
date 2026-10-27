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
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
class DailyOperationsTest extends TestCase {
    use RefreshDatabase;
    private function admin(Organization $org): User { $u=User::factory()->create(); $org->users()->attach($u,['is_admin'=>true]); return $u; }
    private function member(Organization $org): User { $u=User::factory()->create(); $org->users()->attach($u,['is_admin'=>false]); return $u; }
    private function employee(Organization $org,User $user): Employee { $e=new Employee(['employee_number'=>'E'.$user->id,'first_name'=>'Employee','last_name'=>(string)$user->id,'status'=>'active']);$e->organization_id=$org->id;$e->user_id=$user->id;$e->save();return $e; }
    public function test_invitation_is_single_use_expires_and_never_enables_login_before_setup(): void {
        Mail::fake();$org=Organization::create(['name'=>'A']);$admin=$this->admin($org);
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/members',['name'=>'New','email'=>'new@example.com'])->assertRedirect();
        $user=User::where('email','new@example.com')->firstOrFail();$this->assertTrue((bool)$user->must_set_password);
        $token=str_repeat('a',64);DB::table('account_invitations')->where('user_id',$user->id)->update(['token_hash'=>hash('sha256',$token)]);
        auth()->logout();$this->get('/invite/'.$token)->assertOk();$this->post('/invite/'.$token,['password'=>'example-password','password_confirmation'=>'example-password'])->assertRedirect('/login');
        $this->assertFalse((bool)$user->fresh()->must_set_password);$this->get('/invite/'.$token)->assertNotFound();$this->assertDatabaseHas('invitation_events',['user_id'=>$user->id,'action'=>'accepted']);
        $this->post('/login',['email'=>'new@example.com','password'=>'example-password'])->assertRedirect('/organizations/'.$org->id.'/my-profile');
    }
    public function test_expired_invitation_and_cross_org_resend_are_rejected(): void {
        Mail::fake();$a=Organization::create(['name'=>'A']);$b=Organization::create(['name'=>'B']);$admin=$this->admin($a);$other=$this->admin($b);
        $this->actingAs($admin)->post('/organizations/'.$a->id.'/members',['name'=>'New','email'=>'new@example.com'])->assertRedirect();$user=User::where('email','new@example.com')->firstOrFail();
        $token=str_repeat('b',64);DB::table('account_invitations')->where('user_id',$user->id)->update(['token_hash'=>hash('sha256',$token),'expires_at'=>now()->subMinute()]);
        $this->get('/invite/'.$token)->assertNotFound();$this->actingAs($other)->post('/organizations/'.$b->id.'/members/'.$user->id.'/invitation')->assertNotFound();
    }
    public function test_theme_colors_are_applied_and_reset(): void {
        $org=Organization::create(['name'=>'A']);$admin=$this->admin($org);
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/branding',['primary_color'=>'#FFFFFF','accent_color'=>'#111111'])->assertRedirect();
        $this->get('/organizations/'.$org->id)->assertOk()->assertSee('--primary:#FFFFFF',false)->assertSee('--on-primary:#000000',false);
        $this->post('/organizations/'.$org->id.'/branding/reset')->assertRedirect();$this->assertSame('#2459a6',$org->fresh()->primary_color);
    }
    public function test_leave_request_approval_and_allowance_are_enforced(): void {
        $org=Organization::create(['name'=>'A']);$admin=$this->admin($org);$user=$this->member($org);$employee=$this->employee($org,$user);$supervisor=$this->employee($org,$admin);$employee->manager_id=$supervisor->id;$employee->save();
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/leave-types',['name'=>'Annual','annual_days'=>5])->assertRedirect();$type=LeaveType::where('organization_id',$org->id)->firstOrFail();
        $this->actingAs($user)->post('/organizations/'.$org->id.'/my-leave',['leave_type_id'=>$type->id,'start_date'=>'2027-01-01','end_date'=>'2027-01-03'])->assertRedirect();
        $leave=LeaveRequest::where('employee_id',$employee->id)->firstOrFail();$this->assertSame(3,$leave->days);
        $this->actingAs($user)->post('/organizations/'.$org->id.'/my-leave',['leave_type_id'=>$type->id,'start_date'=>'2027-02-01','end_date'=>'2027-02-03'])->assertSessionHasErrors('end_date');
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/leave-requests/'.$leave->id.'/decision',['decision'=>'approved'])->assertRedirect();$this->assertSame('approved',$leave->fresh()->status);
        $this->actingAs($user)->post('/organizations/'.$org->id.'/my-leave/'.$leave->id.'/cancel')->assertStatus(422);
    }
    public function test_leave_cannot_be_approved_by_self_or_cross_organization(): void {
        $a=Organization::create(['name'=>'A']);$b=Organization::create(['name'=>'B']);$admin=$this->admin($a);$other=$this->admin($b);$employee=$this->employee($a,$admin); $supervisorUser=$this->member($a); $supervisor=$this->employee($a,$supervisorUser); $employee->manager_id=$supervisor->id; $employee->save();
        $type=new LeaveType(['name'=>'Annual','annual_days'=>10]);$type->organization_id=$a->id;$type->save();
        $this->actingAs($admin)->post('/organizations/'.$a->id.'/my-leave',['leave_type_id'=>$type->id,'start_date'=>'2027-03-01','end_date'=>'2027-03-01'])->assertRedirect();$leave=LeaveRequest::where('employee_id',$employee->id)->firstOrFail();
        $this->actingAs($admin)->post('/organizations/'.$a->id.'/leave-requests/'.$leave->id.'/decision',['decision'=>'approved'])->assertForbidden();
        $this->actingAs($other)->post('/organizations/'.$b->id.'/leave-requests/'.$leave->id.'/decision',['decision'=>'approved'])->assertNotFound();
    }
    public function test_unit_scoped_reviewer_cannot_view_or_approve_other_units(): void {
        $org=Organization::create(['name'=>'A']); $unitA=$org->units()->create(['name'=>'Unit A']); $unitB=$org->units()->create(['name'=>'Unit B']);
        $reviewer=$this->member($org); $staffA=$this->member($org); $staffB=$this->member($org); $employeeA=$this->employee($org,$staffA); $employeeB=$this->employee($org,$staffB); $supervisor=$this->employee($org,$reviewer); $employeeA->manager_id=$supervisor->id; $employeeB->manager_id=$supervisor->id; $employeeA->unit_id=$unitA->id; $employeeA->save(); $employeeB->unit_id=$unitB->id; $employeeB->save();
        $role=new Role(['name'=>'Reviewer']); $role->organization_id=$org->id; $role->save();
        foreach (['leave.requests.view','leave.requests.supervisor_approve'] as $key) $role->permissions()->attach(Permission::where('key',$key)->value('id'),['access_scope'=>'unit','unit_id'=>$unitA->id]);
        DB::table('organization_role_user')->insert(['organization_id'=>$org->id,'role_id'=>$role->id,'user_id'=>$reviewer->id]);
        $type=new LeaveType(['name'=>'Annual','annual_days'=>10]);$type->organization_id=$org->id;$type->save();
        $this->actingAs($staffA)->post('/organizations/'.$org->id.'/my-leave',['leave_type_id'=>$type->id,'start_date'=>'2027-04-01','end_date'=>'2027-04-01'])->assertRedirect();
        $this->actingAs($staffB)->post('/organizations/'.$org->id.'/my-leave',['leave_type_id'=>$type->id,'start_date'=>'2027-04-02','end_date'=>'2027-04-02'])->assertRedirect();
        $leaveA=LeaveRequest::where('employee_id',$employeeA->id)->firstOrFail();$leaveB=LeaveRequest::where('employee_id',$employeeB->id)->firstOrFail();
        $this->actingAs($reviewer)->get('/organizations/'.$org->id.'/leave-requests')->assertOk()->assertSee('2027-04-01')->assertDontSee('2027-04-02');
        $this->actingAs($reviewer)->post('/organizations/'.$org->id.'/leave-requests/'.$leaveB->id.'/decision',['decision'=>'approved'])->assertForbidden();
        $this->actingAs($reviewer)->post('/organizations/'.$org->id.'/leave-requests/'.$leaveA->id.'/decision',['decision'=>'approved'])->assertRedirect();
    }
    public function test_resend_invalidates_previous_invitation(): void {
        Mail::fake();$org=Organization::create(['name'=>'A']);$admin=$this->admin($org);
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/members',['name'=>'New','email'=>'new@example.com'])->assertRedirect();$user=User::where('email','new@example.com')->firstOrFail();
        $token=str_repeat('c',64);DB::table('account_invitations')->where('user_id',$user->id)->update(['token_hash'=>hash('sha256',$token)]);
        $this->post('/organizations/'.$org->id.'/members/'.$user->id.'/invitation')->assertRedirect();$this->get('/invite/'.$token)->assertNotFound();
        $this->assertDatabaseCount('account_invitations',1);
    }
}
