<?php
namespace App\Http\Controllers;
use App\Models\Employee;
use App\Models\LifecycleCase;
use App\Models\LifecycleTask;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SelfLifecycleController extends Controller {
 private function self(Request $r,Organization $o,string $permission): Employee {
  abort_unless($r->user()->hasPermission($permission),403);abort_unless($o->users()->where('users.id',$r->user()->id)->exists(),403);
  return Employee::where('organization_id',$o->id)->where('user_id',$r->user()->id)->firstOrFail();
 }
 public function index(Request $r,Organization $organization) {
  $e=$this->self($r,$organization,'lifecycle.self.view');$cases=LifecycleCase::where('organization_id',$organization->id)->where('employee_id',$e->id)->with(['tasks'=>fn($q)=>$q->where('assignee','employee')])->orderByDesc('id')->get();
  return view('lifecycle.self',compact('organization','cases'));
 }
 public function complete(Request $r,Organization $organization,LifecycleCase $case,LifecycleTask $task) {
  $e=$this->self($r,$organization,'lifecycle.self.update');abort_unless((int)$case->organization_id===(int)$organization->id && (int)$case->employee_id===(int)$e->id && (int)$task->organization_id===(int)$organization->id && (int)$task->lifecycle_case_id===(int)$case->id && $task->assignee==='employee',404);
  DB::transaction(function()use($r,$organization,$case,$task){$case=LifecycleCase::whereKey($case->id)->lockForUpdate()->firstOrFail();abort_unless($case->status==='open',422);$task->refresh();if(!$task->completed){$task->completed=true;$task->completed_by=$r->user()->id;$task->completed_at=now();$task->save();DB::table('lifecycle_events')->insert(['organization_id'=>$organization->id,'actor_id'=>$r->user()->id,'entity_type'=>'case','entity_id'=>$case->id,'action'=>'task_completed','details'=>$task->title,'created_at'=>now(),'updated_at'=>now()]);}});return back()->with('status','Your task is marked complete.');
 }
 public function notifications(Request $r,Organization $organization) {
  $this->self($r,$organization,'lifecycle.self.notifications.view');$notifications=DB::table('lifecycle_notifications')->where('organization_id',$organization->id)->where('user_id',$r->user()->id)->orderByDesc('id')->get();return view('lifecycle.notifications',compact('organization','notifications'));
 }
 public function readNotification(Request $r,Organization $organization,int $notification) {
  $this->self($r,$organization,'lifecycle.self.notifications.update');$updated=DB::table('lifecycle_notifications')->where('id',$notification)->where('organization_id',$organization->id)->where('user_id',$r->user()->id)->update(['read_at'=>now(),'updated_at'=>now()]);abort_unless($updated,404);return back()->with('status','Notification marked read.');
 }
}
