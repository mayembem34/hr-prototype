<?php
namespace App\Http\Controllers;
use App\Models\Employee;
use App\Models\Organization;
use App\Services\EmployeeAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TrainingAssessmentController extends Controller {
 private function permit(Request $r,Organization $o,string $p): void {abort_unless($r->user()->hasPermission($p,$o),403);}
 private function course(Organization $o,int $id) {return DB::table('training_courses')->where('organization_id',$o->id)->where('id',$id)->first() ?? abort(404);}
 private function assessment(Organization $o,int $course) {return DB::table('training_assessments')->where('organization_id',$o->id)->where('course_id',$course)->first();}
 private function own(Request $r,Organization $o,int $id,string $permission) {
  abort_unless($r->user()->hasPermission($permission) && $o->users()->where('users.id',$r->user()->id)->exists(),403);
  $e=Employee::where('organization_id',$o->id)->where('user_id',$r->user()->id)->firstOrFail();
  $a=DB::table('training_assignments')->where('organization_id',$o->id)->where('employee_id',$e->id)->where('id',$id)->first();abort_unless($a,404);return $a;
 }
 public function manage(Request $r,Organization $organization,int $course) {
  $this->permit($r,$organization,'training.assessments.view');$courseRow=$this->course($organization,$course);$assessment=$this->assessment($organization,$course);$questions=$assessment?DB::table('training_questions')->where('assessment_id',$assessment->id)->orderBy('id')->get():collect();
  return view('assessment.manage',compact('organization','courseRow','assessment','questions'));
 }
 public function create(Request $r,Organization $organization,int $course) {
  $this->permit($r,$organization,'training.assessments.manage');$data=$r->validate(['pass_percent'=>'required|integer|min:1|max:100']);
  DB::transaction(function()use($r,$organization,$course,$data){Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();$this->course($organization,$course);if($this->assessment($organization,$course))throw ValidationException::withMessages(['assessment'=>'An assessment already exists.']);DB::table('training_assessments')->insert(['organization_id'=>$organization->id,'course_id'=>$course,'pass_percent'=>$data['pass_percent'],'created_at'=>now(),'updated_at'=>now()]);});return back()->with('status','Assessment created. Add questions before publishing.');
 }
 public function addQuestion(Request $r,Organization $organization,int $course) {
  $this->permit($r,$organization,'training.assessments.manage');$data=$r->validate(['prompt'=>'required|string|max:3000','options'=>'required|array|size:4','options.*'=>'required|string|max:1000','correct_option'=>['required','integer',Rule::in([0,1,2,3])]]);
  DB::transaction(function()use($organization,$course,$data){Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();$this->course($organization,$course);$a=$this->assessment($organization,$course);abort_unless($a && !$a->published_at,422);DB::table('training_questions')->insert(['assessment_id'=>$a->id,'prompt'=>$data['prompt'],'options'=>json_encode(array_values($data['options'])),'correct_option'=>$data['correct_option'],'created_at'=>now(),'updated_at'=>now()]);});return back()->with('status','Question added.');
 }
 public function deleteQuestion(Request $r,Organization $organization,int $course,int $question) {
  $this->permit($r,$organization,'training.assessments.manage');DB::transaction(function()use($organization,$course,$question){Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();$this->course($organization,$course);$a=$this->assessment($organization,$course);abort_unless($a && !$a->published_at,422);$n=DB::table('training_questions')->where('assessment_id',$a->id)->where('id',$question)->delete();abort_unless($n,404);});return back()->with('status','Question removed.');
 }
 public function publish(Request $r,Organization $organization,int $course) {
  $this->permit($r,$organization,'training.assessments.manage');DB::transaction(function()use($organization,$course){Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();$this->course($organization,$course);$a=$this->assessment($organization,$course);abort_unless($a && !$a->published_at,422);if(!DB::table('training_questions')->where('assessment_id',$a->id)->exists())throw ValidationException::withMessages(['assessment'=>'Add at least one question before publishing.']);DB::table('training_assessments')->where('id',$a->id)->update(['published_at'=>now(),'updated_at'=>now()]);});return back()->with('status','Assessment published.');
 }
 public function take(Request $r,Organization $organization,int $assignment) {
  $a=$this->own($r,$organization,$assignment,'training.self.assessment.view');abort_unless(in_array($a->status,['assigned','in_progress','completed'],true),403);$courseRow=$this->course($organization,$a->course_id);$assessment=$this->assessment($organization,$a->course_id);abort_unless($assessment && $assessment->published_at,404);
  $questions=DB::table('training_questions')->where('assessment_id',$assessment->id)->orderBy('id')->get(['id','prompt','options']);$attempts=DB::table('training_attempts')->where('organization_id',$organization->id)->where('assignment_id',$a->id)->where('assessment_id',$assessment->id)->orderByDesc('id')->get();return view('assessment.take',compact('organization','a','courseRow','assessment','questions','attempts'));
 }
 public function submit(Request $r,Organization $organization,int $assignment) {
  $this->own($r,$organization,$assignment,'training.self.assessment.submit');$data=$r->validate(['answers'=>'required|array','answers.*'=>['required','integer',Rule::in([0,1,2,3])]]);
  DB::transaction(function()use($r,$organization,$assignment,$data){Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();$a=$this->own($r,$organization,$assignment,'training.self.assessment.submit');abort_unless(in_array($a->status,['assigned','in_progress'],true),422);$assessment=$this->assessment($organization,$a->course_id);abort_unless($assessment && $assessment->published_at,404);$questions=DB::table('training_questions')->where('assessment_id',$assessment->id)->orderBy('id')->get();abort_unless($questions->isNotEmpty(),422);
   $answers=$data['answers'];$keys=array_map('strval',array_keys($answers));$expected=$questions->pluck('id')->map(fn($id)=>(string)$id)->all();sort($keys);sort($expected);if($keys!==$expected)throw ValidationException::withMessages(['answers'=>'Answer every question and submit only the listed questions.']);$correct=0;foreach($questions as $q)if((int)$answers[$q->id]===(int)$q->correct_option)$correct++;$total=$questions->count();$score=(int)floor(100*$correct/$total);$passed=100*$correct >= (int)$assessment->pass_percent*$total;
   DB::table('training_attempts')->insert(['organization_id'=>$organization->id,'assessment_id'=>$assessment->id,'assignment_id'=>$a->id,'correct_count'=>$correct,'question_count'=>$total,'score_percent'=>$score,'passed'=>$passed,'created_at'=>now(),'updated_at'=>now()]);
   DB::table('training_events')->insert(['organization_id'=>$organization->id,'assignment_id'=>$a->id,'actor_id'=>$r->user()->id,'action'=>'assessment_submitted','note'=>"$correct/$total",'created_at'=>now(),'updated_at'=>now()]);
  });return back()->with('status','Assessment submitted. Your result appears below.');
 }
 public function results(Request $r,Organization $organization,int $assignment) {
  $a=DB::table('training_assignments')->where('organization_id',$organization->id)->where('id',$assignment)->first();abort_unless($a,404);$e=Employee::where('organization_id',$organization->id)->findOrFail($a->employee_id);abort_unless(app(EmployeeAccess::class)->allows($r->user(),$organization,'training.results.view',$e),403);$courseRow=$this->course($organization,$a->course_id);$attempts=DB::table('training_attempts')->where('organization_id',$organization->id)->where('assignment_id',$a->id)->orderByDesc('id')->get();return view('assessment.results',compact('organization','e','courseRow','attempts'));
 }
}
