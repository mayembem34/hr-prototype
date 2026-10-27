<?php
namespace App\Services;
use App\Models\LifecycleCase;
use App\Models\LifecycleTask;
class LifecycleDefaults {
 public function ensureOnboarding(\App\Models\Organization $org,\App\Models\Employee $employee,int $actorId): void {
  if(LifecycleCase::where('organization_id',$org->id)->where('employee_id',$employee->id)->where('kind','onboarding')->exists())return;
  $case=new LifecycleCase(['kind'=>'onboarding']);$case->organization_id=$org->id;$case->employee_id=$employee->id;$case->status='open';$case->save();$this->populate($case);app(\App\Services\LifecycleAlerts::class)->notifyEmployee($case,'Your onboarding checklist is ready.');
  \Illuminate\Support\Facades\DB::table('lifecycle_events')->insert(['organization_id'=>$org->id,'actor_id'=>$actorId,'entity_type'=>'case','entity_id'=>$case->id,'action'=>'created','details'=>'onboarding','created_at'=>now(),'updated_at'=>now()]);
 }
 public function populate(LifecycleCase $case): void {
  $templates=[
   'onboarding'=>[['Complete personal profile','employee'],['Provide required documents','employee'],['Confirm account and access setup','staff'],['Complete equipment handover','staff']],
   'probation'=>[['Agree probation objectives','staff'],['Complete probation review','staff']],
   'offboarding'=>[['Return assigned equipment','employee'],['Complete employee clearance','staff'],['Confirm final administration','staff']],
  ];
  foreach($templates[$case->kind]??[] as [$title,$assignee]) { $t=new LifecycleTask(['title'=>$title]);$t->organization_id=$case->organization_id;$t->lifecycle_case_id=$case->id;$t->assignee=$assignee;$t->save(); }
 }
}
