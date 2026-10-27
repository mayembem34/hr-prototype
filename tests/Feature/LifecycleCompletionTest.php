<?php
namespace Tests\Feature;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\LifecycleCase;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
class LifecycleCompletionTest extends TestCase {
 use RefreshDatabase;
 private function admin(Organization $org): User { $u=User::factory()->create();$org->users()->attach($u,['is_admin'=>true]);return $u; }
 private function employee(Organization $org,User $u): Employee { $e=new Employee(['employee_number'=>'E'.$u->id,'first_name'=>'Employee','last_name'=>'Test','status'=>'active']);$e->organization_id=$org->id;$e->user_id=$u->id;$e->save();return $e; }
 public function test_accepted_offer_hiring_and_employee_onboarding(): void {
  Mail::fake();$org=Organization::create(['name'=>'A']);$admin=$this->admin($org);$base='/organizations/'.$org->id;
  $this->actingAs($admin)->post($base.'/vacancies',['title'=>'Analyst'])->assertRedirect();$v=Vacancy::firstOrFail();$this->post($base.'/applications',['vacancy_id'=>$v->id,'candidate_name'=>'Alice','email'=>'alice@example.com'])->assertRedirect();$a=JobApplication::firstOrFail();
  $this->post($base.'/applications/'.$a->id.'/hire')->assertSessionHasErrors('candidate');
  $this->post($base.'/applications/'.$a->id.'/interviews',['scheduled_at'=>'2027-02-01T10:00','location'=>'Office'])->assertRedirect();$this->assertDatabaseHas('recruitment_interviews',['application_id'=>$a->id,'location'=>'Office']);
  $this->post($base.'/applications/'.$a->id.'/offers',['offer_date'=>'2027-02-02','status'=>'accepted'])->assertRedirect();$this->post($base.'/applications/'.$a->id.'/hire')->assertRedirect();$this->assertSame('hired',$a->fresh()->stage);
  $u=User::where('email','alice@example.com')->firstOrFail();$this->assertTrue((bool)$u->must_set_password);
  $this->actingAs($u)->post($base.'/my-profile',['first_name'=>'Alice','last_name'=>'Test'])->assertRedirect();$case=LifecycleCase::where('kind','onboarding')->firstOrFail();$task=$case->tasks()->where('assignee','employee')->firstOrFail();
  $this->get($base.'/my-lifecycle')->assertOk()->assertSee($task->title);$this->get($base.'/my-lifecycle/notifications')->assertOk()->assertSee('Your onboarding checklist is ready.');$this->post($base.'/my-lifecycle/'.$case->id.'/tasks/'.$task->id)->assertRedirect();$this->assertTrue((bool)$task->fresh()->completed);
  $staff=$case->tasks()->where('assignee','staff')->firstOrFail();$this->post($base.'/my-lifecycle/'.$case->id.'/tasks/'.$staff->id)->assertNotFound();
 }
 public function test_offboarding_removes_only_this_organization_access(): void {
  $a=Organization::create(['name'=>'A']);$b=Organization::create(['name'=>'B']);$admin=$this->admin($a);$user=User::factory()->create();$a->users()->attach($user,['is_admin'=>false]);$b->users()->attach($user,['is_admin'=>false]);$e=$this->employee($a,$user);$base='/organizations/'.$a->id.'/lifecycle';
  $this->actingAs($admin)->post($base,['employee_id'=>$e->id,'kind'=>'offboarding'])->assertRedirect();$case=LifecycleCase::where('kind','offboarding')->firstOrFail();foreach($case->tasks as $task)$this->post($base.'/'.$case->id.'/tasks/'.$task->id,['completed'=>1])->assertRedirect();$this->post($base.'/'.$case->id.'/complete',[])->assertRedirect();
  $this->assertSame('left',$e->fresh()->status);$this->assertFalse($user->fresh()->organizations()->where('organizations.id',$a->id)->exists());$this->assertTrue($user->fresh()->organizations()->where('organizations.id',$b->id)->exists());
  $this->actingAs($user)->get('/organizations/'.$a->id.'/my-profile')->assertForbidden();
 }
 public function test_last_organization_admin_cannot_be_offboarded(): void {
  $org=Organization::create(['name'=>'A']);$platform=User::factory()->create();$platform->is_platform_admin=true;$platform->save();$admin=$this->admin($org);$e=$this->employee($org,$admin);$base='/organizations/'.$org->id.'/lifecycle';
  $this->actingAs($platform)->post($base,['employee_id'=>$e->id,'kind'=>'offboarding'])->assertRedirect();$case=LifecycleCase::where('kind','offboarding')->firstOrFail();foreach($case->tasks as $task)$this->post($base.'/'.$case->id.'/tasks/'.$task->id,['completed'=>1])->assertRedirect();$this->post($base.'/'.$case->id.'/complete',[])->assertSessionHasErrors('case');
  $this->assertSame('active',$e->fresh()->status);$this->assertTrue($admin->fresh()->organizations()->where('organizations.id',$org->id)->exists());
 }
 public function test_inactive_membership_cannot_accept_an_old_invitation(): void {
  $org=Organization::create(['name'=>'A']);$user=User::factory()->create();$org->users()->attach($user);$user->must_set_password=true;$user->save();$token=str_repeat('q',64);
  DB::table('account_invitations')->insert(['organization_id'=>$org->id,'user_id'=>$user->id,'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addHours(2),'created_at'=>now(),'updated_at'=>now()]);
  DB::table('organization_user')->where('organization_id',$org->id)->where('user_id',$user->id)->update(['is_active'=>false]);
  $this->post('/invite/'.$token,['password'=>'secure-password-123','password_confirmation'=>'secure-password-123'])->assertNotFound();$this->assertTrue((bool)$user->fresh()->must_set_password);
 }
}
