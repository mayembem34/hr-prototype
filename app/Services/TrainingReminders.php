<?php
namespace App\Services;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
class TrainingReminders {
 public function sendDue(): int {
  $count=0;
  foreach(Organization::orderBy('id')->get() as $organization) DB::transaction(function()use($organization,&$count){
   Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();$today=now()->utc()->toDateString();$limit=now()->utc()->addDays(7)->toDateString();
   $rows=DB::table('training_assignments as a')->join('employees as e','e.id','=','a.employee_id')->join('training_courses as c','c.id','=','a.course_id')->join('organization_user as m',function($join){$join->on('m.organization_id','=','a.organization_id')->on('m.user_id','=','e.user_id');})->where('a.organization_id',$organization->id)->where('e.organization_id',$organization->id)->where('c.organization_id',$organization->id)->where('m.is_active',true)->whereIn('a.status',['assigned','in_progress'])->whereNotNull('a.due_date')->where('a.due_date','<=',$limit)->select('a.id','a.due_date','e.user_id','c.title')->get();
   foreach($rows as $row){if(DB::table('training_reminders')->where('assignment_id',$row->id)->where('reminder_date',$today)->exists())continue;DB::table('training_reminders')->insert(['organization_id'=>$organization->id,'assignment_id'=>$row->id,'reminder_date'=>$today,'created_at'=>now(),'updated_at'=>now()]);app(DailyAlerts::class)->send($organization->id,$row->user_id,'Training due '.$row->due_date.': '.$row->title);$count++;}
  });return $count;
 }
}
