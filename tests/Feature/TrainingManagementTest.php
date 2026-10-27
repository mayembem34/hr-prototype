<?php
namespace Tests\Feature;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\TrainingReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class TrainingManagementTest extends TestCase {
 use RefreshDatabase;
 private function member(Organization $o,bool $admin=false): User {$u=User::factory()->create();$o->users()->attach($u,['is_admin'=>$admin]);return $u;}
 private function employee(Organization $o,User $u,$unit=null): Employee {$e=new Employee(['employee_number'=>'E'.$u->id,'first_name'=>'Name'.$u->id,'last_name'=>'Test','status'=>'active','unit_id'=>$unit?->id]);$e->organization_id=$o->id;$e->user_id=$u->id;$e->save();return $e;}
 private function grant(Organization $o,User $u,array $keys,$unit=null): void {$role=new Role(['name'=>'Role'.$u->id]);$role->organization_id=$o->id;$role->save();foreach($keys as $key)$role->permissions()->attach(Permission::where('key',$key)->value('id'),['access_scope'=>$unit?'unit_tree':'organization','unit_id'=>$unit?->id]);DB::table('organization_role_user')->insert(['organization_id'=>$o->id,'role_id'=>$role->id,'user_id'=>$u->id]);}
 private function course(Organization $o,User $user): int {$this->actingAs($user)->post('/organizations/'.$o->id.'/training',['title'=>'Safety','active'=>1,'delivery_url'=>'https://example.com/course'])->assertRedirect();return DB::table('training_courses')->where('organization_id',$o->id)->value('id');}
 public function test_bulk_targets_are_tenant_scoped_deduplicated_and_atomic(): void {
  $o=Organization::create(['name'=>'A']);$other=Organization::create(['name'=>'B']);$admin=$this->member($o,true);$root=$o->units()->create(['name'=>'Root']);$child=$o->units()->create(['name'=>'Child','parent_id'=>$root->id]);$out=$o->units()->create(['name'=>'Outside']);$one=$this->employee($o,$this->member($o),$root);$two=$this->employee($o,$this->member($o),$child);$three=$this->employee($o,$this->member($o),$out);$foreign=$this->employee($other,$this->member($other));$id=$this->course($o,$admin);$base='/organizations/'.$o->id.'/training/'.$id.'/assign';
  $manager=$this->member($o);$this->grant($o,$manager,['training.courses.view','training.assignments.create','training.assignments.view','units.view'],$root);
  $this->actingAs($manager)->post($base,['target'=>'unit','unit_id'=>$root->id,'include_nested'=>1])->assertRedirect();$this->assertDatabaseCount('training_assignments',2);
  $this->post($base,['target'=>'individual','employee_ids'=>[$one->id,$three->id]])->assertForbidden();$this->assertDatabaseCount('training_assignments',2);
  $this->post($base,['target'=>'organization'])->assertForbidden();$this->post($base,['target'=>'individual','employee_ids'=>[$foreign->id]])->assertSessionHasErrors('employee_ids.0');
  $this->actingAs($admin)->post($base,['target'=>'organization'])->assertRedirect();$this->post($base,['target'=>'organization'])->assertRedirect();$this->assertDatabaseCount('training_assignments',3);
  $this->actingAs($this->member($o))->post($base,['target'=>'individual','employee_ids'=>[$one->id]])->assertForbidden();
 }
 public function test_enrolment_approval_progress_certificate_privacy_and_reminders(): void {
  Storage::fake('local');$o=Organization::create(['name'=>'A']);$admin=$this->member($o,true);$user=$this->member($o);$e=$this->employee($o,$user);$other=$this->member($o);$this->employee($o,$other);$course=$this->course($o,$admin);$url='/organizations/'.$o->id;
  $this->actingAs($user)->post($url.'/training/'.$course.'/enroll')->assertRedirect();$a=DB::table('training_assignments')->first();$this->post($url.'/training/assignments/'.$a->id.'/progress',['status'=>'completed'])->assertStatus(422);
  $this->actingAs($admin)->post($url.'/training/assignments/'.$a->id.'/decision',['decision'=>'approved'])->assertRedirect();DB::table('training_assignments')->where('id',$a->id)->update(['due_date'=>now()->utc()->toDateString()]);$this->assertSame(1,app(TrainingReminders::class)->sendDue());$this->assertSame(0,app(TrainingReminders::class)->sendDue());
  $this->actingAs($user)->post($url.'/training/assignments/'.$a->id.'/progress',['status'=>'completed'])->assertRedirect();$this->post($url.'/training/assignments/'.$a->id.'/certificates',['certificate'=>UploadedFile::fake()->create('certificate.pdf',20,'application/pdf')])->assertRedirect();$cert=DB::table('training_certificates')->first();
  $this->get($url.'/training/assignments/'.$a->id.'/certificates/'.$cert->id)->assertOk();$this->actingAs($other)->get($url.'/training/assignments/'.$a->id.'/certificates/'.$cert->id)->assertForbidden();$this->actingAs($admin)->get($url.'/training')->assertOk()->assertSee('Safety');
 }
}
