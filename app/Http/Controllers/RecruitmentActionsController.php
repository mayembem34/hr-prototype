<?php
namespace App\Http\Controllers;
use App\Models\JobApplication;
use App\Models\Organization;
use App\Models\User;
use App\Services\AccountInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class RecruitmentActionsController extends Controller {
 private function auth(Request $r,Organization $o,JobApplication $a,string $permission): void { abort_unless((int)$a->organization_id===(int)$o->id,404);abort_unless($r->user()->hasPermission($permission,$o),403); }
 private function event(Request $r,Organization $o,JobApplication $a,string $action): void { DB::table('lifecycle_events')->insert(['organization_id'=>$o->id,'entity_type'=>'application','entity_id'=>$a->id,'actor_id'=>$r->user()->id,'action'=>$action,'created_at'=>now(),'updated_at'=>now()]); }
 public function interview(Request $r,Organization $organization,JobApplication $application) {
  $this->auth($r,$organization,$application,'recruitment.interviews.manage');$data=$r->validate(['scheduled_at'=>'required|date_format:Y-m-d\\TH:i','location'=>'nullable|string|max:255','notes'=>'nullable|string|max:10000']);
  DB::transaction(function()use($r,$organization,$application,$data){ DB::table('recruitment_interviews')->insert(['organization_id'=>$organization->id,'application_id'=>$application->id,'scheduled_at'=>$data['scheduled_at'],'location'=>$data['location']??null,'notes'=>$data['notes']??null,'status'=>'scheduled','created_at'=>now(),'updated_at'=>now()]);$this->event($r,$organization,$application,'interview_scheduled');});return back()->with('status','Interview scheduled.');
 }
 public function offer(Request $r,Organization $organization,JobApplication $application) {
  $this->auth($r,$organization,$application,'recruitment.offers.manage');$data=$r->validate(['offer_date'=>'required|date_format:Y-m-d','status'=>['required',Rule::in(['draft','issued','accepted','declined'])],'terms'=>'nullable|string|max:10000']);
  DB::transaction(function()use($r,$organization,$application,$data){DB::table('recruitment_offers')->insert(['organization_id'=>$organization->id,'application_id'=>$application->id,'offer_date'=>$data['offer_date'],'status'=>$data['status'],'terms'=>$data['terms']??null,'created_at'=>now(),'updated_at'=>now()]);$this->event($r,$organization,$application,'offer_recorded');});return back()->with('status','Offer recorded.');
 }
 public function hire(Request $r,Organization $organization,JobApplication $application) {
  $this->auth($r,$organization,$application,'recruitment.hire');$newUser=null;
  DB::transaction(function()use($r,$organization,$application,&$newUser){
   Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();$application->refresh();
   if($application->hired_user_id || $application->stage==='hired') throw ValidationException::withMessages(['candidate'=>'This candidate is already hired.']);
   if(!$application->email || !DB::table('recruitment_offers')->where('organization_id',$organization->id)->where('application_id',$application->id)->where('status','accepted')->exists()) throw ValidationException::withMessages(['candidate'=>'An email address and accepted offer are required.']);
   $user=User::where('email',$application->email)->first();
   if(!$user){abort_unless($r->user()->hasPermission('members.accounts.create',$organization) && $r->user()->hasPermission('invitations.send',$organization),403);$user=User::create(['name'=>$application->candidate_name,'email'=>$application->email,'password'=>Str::random(64)]);$user->must_set_password=true;$user->save();$newUser=$user;}
   $membership=DB::table('organization_user')->where('organization_id',$organization->id)->where('user_id',$user->id)->first();
   if($membership && !$membership->is_active) throw ValidationException::withMessages(['candidate'=>'This account has an inactive membership; restore it separately before hiring.']);
   if(!$membership)$organization->users()->attach($user->id,['is_admin'=>false]);
   $application->hired_user_id=$user->id;$application->stage='hired';$application->save();$employee=\App\Models\Employee::where('organization_id',$organization->id)->where('user_id',$user->id)->first();if($employee) app(\App\Services\LifecycleDefaults::class)->ensureOnboarding($organization,$employee,$r->user()->id);$this->event($r,$organization,$application,'hired');
  });
  if($newUser && !app(AccountInvitations::class)->send($organization,$newUser,$r->user()))return back()->with('status','Candidate hired, but the invitation failed. Resend it from Members.');
  return back()->with('status',$newUser?'Candidate hired and invitation sent.':'Candidate linked to their existing account.');
 }
}
