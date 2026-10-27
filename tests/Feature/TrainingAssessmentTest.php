<?php
namespace Tests\Feature;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class TrainingAssessmentTest extends TestCase {
 use RefreshDatabase;
 public function test_published_assessment_requires_pass_and_supports_retries(): void {
  $org=Organization::create(['name'=>'A']);$admin=User::factory()->create();$user=User::factory()->create();$other=User::factory()->create();$org->users()->attach($admin,['is_admin'=>true]);$org->users()->attach($user);$org->users()->attach($other);
  $employee=new Employee(['employee_number'=>'E1','first_name'=>'Amy','last_name'=>'Test','status'=>'active']);$employee->organization_id=$org->id;$employee->user_id=$user->id;$employee->save();$base='/organizations/'.$org->id;
  $this->actingAs($admin)->post($base.'/training',['title'=>'Safety','active'=>1])->assertRedirect();$course=DB::table('training_courses')->value('id');$this->post($base.'/training/'.$course.'/assign',['target'=>'individual','employee_ids'=>[$employee->id]])->assertRedirect();$assignment=DB::table('training_assignments')->value('id');
  $url=$base.'/training/'.$course.'/assessment';$this->post($url,['pass_percent'=>70])->assertRedirect();$this->post($url.'/publish')->assertSessionHasErrors('assessment');$this->post($url.'/questions',['prompt'=>'Which is correct?','options'=>['One','Two','Three','Four'],'correct_option'=>1])->assertRedirect();$question=DB::table('training_questions')->first();$this->post($url.'/publish')->assertRedirect();$this->post($url.'/questions',['prompt'=>'Late','options'=>['1','2','3','4'],'correct_option'=>0])->assertStatus(422);
  $attempt=$base.'/training/assignments/'.$assignment.'/assessment';$this->actingAs($user)->post($base.'/training/assignments/'.$assignment.'/progress',['status'=>'completed'])->assertSessionHasErrors('status');$this->get($attempt)->assertOk()->assertSee('Which is correct?')->assertDontSee('Correct</strong>',false);
  $this->post($attempt,['answers'=>[$question->id=>0]])->assertRedirect();$this->assertDatabaseHas('training_attempts',['assignment_id'=>$assignment,'passed'=>false]);$this->post($attempt,['answers'=>[$question->id=>1]])->assertRedirect();$this->assertDatabaseHas('training_attempts',['assignment_id'=>$assignment,'passed'=>true]);$this->post($base.'/training/assignments/'.$assignment.'/progress',['status'=>'completed'])->assertRedirect();$this->assertDatabaseHas('training_assignments',['id'=>$assignment,'status'=>'completed']);
  $this->actingAs($other)->get($attempt)->assertNotFound();$this->get($base.'/training/assignments/'.$assignment.'/assessment/results')->assertForbidden();$this->actingAs($admin)->get($base.'/training/assignments/'.$assignment.'/assessment/results')->assertOk()->assertSee('Passed');
 }
}
