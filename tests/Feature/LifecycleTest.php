<?php
namespace Tests\Feature;
use App\Models\Employee;
use App\Models\LifecycleCase;
use App\Models\LifecycleTask;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class LifecycleTest extends TestCase {
    use RefreshDatabase;
    private function admin(Organization $org): User { $u=User::factory()->create();$org->users()->attach($u,['is_admin'=>true]);return $u; }
    private function employee(Organization $org,string $num,$unit=null): Employee { $e=new Employee(['employee_number'=>$num,'first_name'=>$num,'last_name'=>'Test','status'=>'active','unit_id'=>$unit?->id]);$e->organization_id=$org->id;$e->save();return $e; }
    private function member(Organization $org,array $keys,$unit): User { $u=User::factory()->create();$org->users()->attach($u);$role=new Role(['name'=>'Scoped']);$role->organization_id=$org->id;$role->save();foreach($keys as $key)$role->permissions()->attach(Permission::where('key',$key)->value('id'),['access_scope'=>'unit','unit_id'=>$unit->id]);DB::table('organization_role_user')->insert(['organization_id'=>$org->id,'role_id'=>$role->id,'user_id'=>$u->id]);return $u; }
    public function test_recruitment_records_vacancy_and_application(): void {
        $org=Organization::create(['name'=>'A']);$admin=$this->admin($org);$base='/organizations/'.$org->id;
        $this->actingAs($admin)->post($base.'/vacancies',['title'=>'Engineer'])->assertRedirect();$v=Vacancy::firstOrFail();
        $this->post($base.'/applications',['vacancy_id'=>$v->id,'candidate_name'=>'Candidate','email'=>'candidate@example.com'])->assertRedirect();$a=DB::table('job_applications')->first();
        $this->post($base.'/applications/'.$a->id,['stage'=>'interview','notes'=>'Scheduled'])->assertRedirect();$this->assertDatabaseHas('job_applications',['id'=>$a->id,'stage'=>'interview']);
        $this->get($base.'/recruitment')->assertOk()->assertSee('Candidate')->assertSee('Engineer');
    }
    public function test_cases_checklists_and_probation_outcome(): void {
        $org=Organization::create(['name'=>'A']);$admin=$this->admin($org);$e=$this->employee($org,'E1');$base='/organizations/'.$org->id.'/lifecycle';
        foreach(['onboarding','probation','offboarding'] as $kind)$this->actingAs($admin)->post($base,['employee_id'=>$e->id,'kind'=>$kind])->assertRedirect();
        $case=LifecycleCase::where('kind','probation')->firstOrFail();$this->post($base.'/'.$case->id.'/tasks',['title'=>'Review objectives'])->assertRedirect();$task=LifecycleTask::firstOrFail();
        $this->post($base.'/'.$case->id.'/complete',['outcome'=>'confirmed'])->assertSessionHasErrors('case');
        $this->post($base.'/'.$case->id.'/tasks/'.$task->id,['completed'=>1])->assertRedirect();$this->post($base.'/'.$case->id.'/complete',['outcome'=>'confirmed'])->assertRedirect();
        $this->assertDatabaseHas('lifecycle_cases',['id'=>$case->id,'status'=>'completed','outcome'=>'confirmed']);$this->get($base)->assertOk()->assertSee('Review objectives');
        $offboard=LifecycleCase::where('kind','offboarding')->firstOrFail();$this->post($base.'/'.$offboard->id.'/complete',[])->assertRedirect();$this->assertSame('active',$e->fresh()->status);
    }
    public function test_tenant_and_scoped_permissions_are_enforced(): void {
        $a=Organization::create(['name'=>'A']);$b=Organization::create(['name'=>'B']);$admin=$this->admin($a);$inside=$a->units()->create(['name'=>'Inside']);$outside=$a->units()->create(['name'=>'Outside']);$e1=$this->employee($a,'IN',$inside);$e2=$this->employee($a,'OUT',$outside);$foreign=$this->employee($b,'FOREIGN');
        $this->actingAs($admin)->post('/organizations/'.$a->id.'/lifecycle',['employee_id'=>$foreign->id,'kind'=>'onboarding'])->assertSessionHasErrors('employee_id');
        $reader=$this->member($a,['lifecycle.cases.view','lifecycle.cases.create'],$inside);
        $this->actingAs($reader)->post('/organizations/'.$a->id.'/lifecycle',['employee_id'=>$e2->id,'kind'=>'probation'])->assertForbidden();
        $this->actingAs($reader)->post('/organizations/'.$a->id.'/lifecycle',['employee_id'=>$e1->id,'kind'=>'probation'])->assertRedirect();
        $this->get('/organizations/'.$a->id.'/lifecycle')->assertOk()->assertSee('IN Test')->assertDontSee('OUT Test');
        $this->post('/organizations/'.$a->id.'/vacancies',['title'=>'Not permitted'])->assertForbidden();
    }
}
