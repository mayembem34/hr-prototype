<?php
namespace Tests\Feature;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class OvertimeTest extends TestCase {
 use RefreshDatabase;
 private function member(Organization $o): User {$u=User::factory()->create();$o->users()->attach($u);return $u;}
 private function employee(Organization $o,User $u,$unit=null): Employee {$e=new Employee(['employee_number'=>'E'.$u->id,'first_name'=>'Person'.$u->id,'last_name'=>'Test','status'=>'active','unit_id'=>$unit?->id]);$e->organization_id=$o->id;$e->user_id=$u->id;$e->save();return $e;}
 private function grant(Organization $o,User $u,array $keys,$unit): void {$role=new Role(['name'=>'Reviewer '.$u->id]);$role->organization_id=$o->id;$role->save();foreach($keys as $key)$role->permissions()->attach(Permission::where('key',$key)->value('id'),['access_scope'=>'unit','unit_id'=>$unit->id]);DB::table('organization_role_user')->insert(['organization_id'=>$o->id,'role_id'=>$role->id,'user_id'=>$u->id]);}
 public function test_request_decision_scope_overlap_and_notification(): void {
  $o=Organization::create(['name'=>'A']);$other=Organization::create(['name'=>'B']);$unit=$o->units()->create(['name'=>'Inside']);$outside=$o->units()->create(['name'=>'Outside']);$staff=$this->member($o);$e=$this->employee($o,$staff,$unit);$outsider=$this->member($o);$this->employee($o,$outsider,$outside);$reviewer=$this->member($o);$this->grant($o,$reviewer,['overtime.requests.view','overtime.requests.decide'],$unit);$base='/organizations/'.$o->id;$data=['starts_at'=>'2027-01-01T23:00','ends_at'=>'2027-01-02T01:30','reason'=>'Work'];
  $this->actingAs($staff)->post($base.'/my-overtime',$data)->assertRedirect();$entry=DB::table('overtime_requests')->first();$this->assertSame(150,(int)$entry->minutes);$this->post($base.'/my-overtime',$data)->assertSessionHasErrors('starts_at');$this->actingAs($outsider)->post($base.'/my-overtime',$data)->assertRedirect();$outsideRequest=DB::table('overtime_requests')->where('employee_id','!=',$e->id)->value('id');
  $this->actingAs($reviewer)->get($base.'/overtime')->assertOk()->assertSee('Person'.$staff->id)->assertDontSee('Person'.$outsider->id);$this->post($base.'/overtime/'.$outsideRequest.'/decision',['decision'=>'approved'])->assertForbidden();$this->post('/organizations/'.$other->id.'/overtime/'.$entry->id.'/decision',['decision'=>'approved'])->assertNotFound();
  $this->post($base.'/overtime/'.$entry->id.'/decision',['decision'=>'approved'])->assertRedirect();$this->assertDatabaseHas('overtime_requests',['id'=>$entry->id,'status'=>'approved']);$this->assertDatabaseHas('overtime_events',['overtime_request_id'=>$entry->id,'action'=>'approved']);$this->assertDatabaseHas('daily_notifications',['organization_id'=>$o->id,'user_id'=>$staff->id]);$this->post($base.'/overtime/'.$entry->id.'/decision',['decision'=>'rejected'])->assertStatus(422);
 }
 public function test_self_approval_rejected_and_pending_can_be_cancelled(): void {
  $o=Organization::create(['name'=>'A']);$u=$this->member($o);$unit=$o->units()->create(['name'=>'One']);$this->employee($o,$u,$unit);$this->grant($o,$u,['overtime.requests.view','overtime.requests.decide'],$unit);$base='/organizations/'.$o->id;
  $this->actingAs($u)->post($base.'/my-overtime',['starts_at'=>'2027-01-01T08:00','ends_at'=>'2027-01-01T10:00','reason'=>'Work'])->assertRedirect();$id=DB::table('overtime_requests')->value('id');$this->post($base.'/overtime/'.$id.'/decision',['decision'=>'approved'])->assertForbidden();$this->post($base.'/my-overtime/'.$id.'/cancel')->assertRedirect();$this->assertDatabaseHas('overtime_requests',['id'=>$id,'status'=>'cancelled']);
  DB::table('organization_user')->where('organization_id',$o->id)->where('user_id',$u->id)->update(['is_active'=>false]);$this->get($base.'/my-overtime')->assertForbidden();
 }
}
