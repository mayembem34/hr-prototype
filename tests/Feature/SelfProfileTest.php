<?php
namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SelfProfileTest extends TestCase {
    use RefreshDatabase;
    private function admin(Organization $org): User { $u=User::factory()->create(); $org->users()->attach($u,['is_admin'=>true]); return $u; }
    private function member(Organization $org): User { $u=User::factory()->create(); $org->users()->attach($u,['is_admin'=>false]); return $u; }
    public function test_admin_creates_account_user_logs_in_and_completes_profile(): void {
        $org=Organization::create(['name'=>'Organization']); $admin=$this->admin($org);
        \Illuminate\Support\Facades\Mail::fake();
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/members',['name'=>'Alice','email'=>'alice@example.com'])->assertRedirect();
        $invited=User::where('email','alice@example.com')->firstOrFail(); $token=str_repeat('a',64);
        DB::table('account_invitations')->where('user_id',$invited->id)->update(['token_hash'=>hash('sha256',$token)]);
        auth()->logout();
        $this->post('/invite/'.$token,['password'=>'secret-password-123','password_confirmation'=>'secret-password-123'])->assertRedirect();
        $this->post('/login',['email'=>'alice@example.com','password'=>'secret-password-123'])->assertRedirect('/organizations/'.$org->id.'/my-profile');
        $this->post('/organizations/'.$org->id.'/my-profile',['first_name'=>'Alice','last_name'=>'Jones','phone'=>'123','date_of_birth'=>'1990-01-01','address'=>'Prague','emergency_contact_name'=>'Sam','emergency_contact_phone'=>'456'])->assertRedirect();
        $user=User::where('email','alice@example.com')->firstOrFail();
        $this->assertDatabaseHas('employees',['organization_id'=>$org->id,'user_id'=>$user->id,'first_name'=>'Alice','address'=>'Prague','emergency_contact_name'=>'Sam']);
        $employee=Employee::where('user_id',$user->id)->firstOrFail();
        $this->assertDatabaseHas('employee_changes',['employee_id'=>$employee->id,'action'=>'self_created']);
    }
    public function test_self_profile_rejects_cross_organization_and_ignores_privileged_fields(): void {
        $org=Organization::create(['name'=>'A']); $other=Organization::create(['name'=>'B']); $user=$this->member($org); $unit=$org->units()->create(['name'=>'Unit']);
        $this->actingAs($user)->get('/organizations/'.$other->id.'/my-profile')->assertForbidden();
        $this->actingAs($user)->post('/organizations/'.$org->id.'/my-profile',['first_name'=>'First','last_name'=>'Last','unit_id'=>$unit->id,'status'=>'suspended','employee_number'=>'CUSTOM','manager_id'=>100])->assertRedirect();
        $e=Employee::where('user_id',$user->id)->firstOrFail(); $this->assertNull($e->unit_id); $this->assertNull($e->manager_id); $this->assertSame('active',$e->status); $this->assertStringStartsWith('AUTO-',$e->employee_number);
    }
    public function test_existing_linked_profile_is_updated_without_duplicate_or_changes_to_employment(): void {
        $org=Organization::create(['name'=>'A']); $user=$this->member($org); $employee=new Employee(['employee_number'=>'E1','first_name'=>'Old','last_name'=>'Name','status'=>'active','job_title'=>'Engineer']); $employee->organization_id=$org->id; $employee->user_id=$user->id; $employee->save();
        $this->actingAs($user)->post('/organizations/'.$org->id.'/my-profile',['first_name'=>'New','last_name'=>'Name','job_title'=>'CEO'])->assertRedirect();
        $this->assertSame('Engineer',$employee->fresh()->job_title); $this->assertSame('New',$employee->fresh()->first_name); $this->assertDatabaseCount('employees',1);
        $this->assertDatabaseHas('employee_changes',['employee_id'=>$employee->id,'action'=>'self_updated']);
    }
    public function test_default_hr_manager_can_view_profiles_across_the_organization(): void {
        $platform=User::factory()->create(); $platform->is_platform_admin=true; $platform->save();
        $this->actingAs($platform)->post('/organizations',['name'=>'New Org'])->assertRedirect();
        $org=Organization::where('name','New Org')->firstOrFail(); $role=Role::where('organization_id',$org->id)->where('name','HR Manager')->firstOrFail();
        $hr=$this->member($org); DB::table('organization_role_user')->insert(['organization_id'=>$org->id,'role_id'=>$role->id,'user_id'=>$hr->id]);
        $staff=$this->member($org); $this->actingAs($staff)->post('/organizations/'.$org->id.'/my-profile',['first_name'=>'Alice','last_name'=>'Jones','address'=>'Prague'])->assertRedirect();
        $employee=Employee::where('user_id',$staff->id)->firstOrFail();
        $this->actingAs($hr)->get('/organizations/'.$org->id.'/employees/'.$employee->id)->assertOk()->assertSee('Alice')->assertSee('Prague');
    }
    public function test_non_admin_member_manager_cannot_create_new_user_accounts(): void {
        $org=Organization::create(['name'=>'A']); $member=$this->member($org); $role=new Role(['name'=>'Member manager']); $role->organization_id=$org->id; $role->save(); $role->permissions()->attach(DB::table('permissions')->where('key','members.add')->value('id'));
        DB::table('organization_role_user')->insert(['organization_id'=>$org->id,'role_id'=>$role->id,'user_id'=>$member->id]);
        $this->actingAs($member)->post('/organizations/'.$org->id.'/members',['name'=>'Other','email'=>'new@example.com','password'=>'password-12345'])->assertForbidden();
        $this->assertDatabaseMissing('users',['email'=>'new@example.com']);
    }
}
