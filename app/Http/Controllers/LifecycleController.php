<?php
namespace App\Http\Controllers;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\LifecycleCase;
use App\Models\LifecycleTask;
use App\Models\Organization;
use App\Models\Vacancy;
use App\Services\EmployeeAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class LifecycleController extends Controller {
    private function permit(Request $r,Organization $o,string $p): void { abort_unless($r->user()->hasPermission($p,$o),403); }
    private function employee(Request $r,Organization $o,Employee $e,string $p): void { abort_unless((int)$e->organization_id===(int)$o->id,404); abort_unless(app(EmployeeAccess::class)->allows($r->user(),$o,$p,$e),403); }
    private function owns(Organization $o,$entity): void { abort_unless((int)$entity->organization_id===(int)$o->id,404); }
    private function event(Request $r,Organization $o,string $type,int $id,string $action,?string $detail=null): void { DB::table('lifecycle_events')->insert(['organization_id'=>$o->id,'actor_id'=>$r->user()->id,'entity_type'=>$type,'entity_id'=>$id,'action'=>$action,'details'=>$detail,'created_at'=>now(),'updated_at'=>now()]); }
    public function recruitment(Request $r,Organization $organization) {
        $this->permit($r,$organization,'recruitment.vacancies.view');
        $vacancies=Vacancy::where('organization_id',$organization->id)->orderByDesc('id')->get();
        $applications=$r->user()->hasPermission('recruitment.applications.view',$organization)?JobApplication::where('organization_id',$organization->id)->with('vacancy')->orderByDesc('id')->get():collect();
        return view('lifecycle.recruitment',compact('organization','vacancies','applications'));
    }
    public function createVacancy(Request $r,Organization $organization) {
        $this->permit($r,$organization,'recruitment.vacancies.create');$data=$r->validate(['title'=>'required|string|max:255','description'=>'nullable|string|max:10000']);
        DB::transaction(function()use($r,$organization,$data){$v=new Vacancy($data);$v->organization_id=$organization->id;$v->status='open';$v->save();$this->event($r,$organization,'vacancy',$v->id,'created');});return back()->with('status','Vacancy created.');
    }
    public function updateVacancy(Request $r,Organization $organization,Vacancy $vacancy) {
        $this->permit($r,$organization,'recruitment.vacancies.update');$this->owns($organization,$vacancy);
        $data=$r->validate(['title'=>'required|string|max:255','description'=>'nullable|string|max:10000','status'=>['required',Rule::in(['open','closed'])]]);
        DB::transaction(function()use($r,$organization,$vacancy,$data){$vacancy->update($data);$this->event($r,$organization,'vacancy',$vacancy->id,'updated');});return back()->with('status','Vacancy updated.');
    }
    public function createApplication(Request $r,Organization $organization) {
        $this->permit($r,$organization,'recruitment.applications.create');
        $data=$r->validate(['vacancy_id'=>['required','integer',Rule::exists('vacancies','id')->where('organization_id',$organization->id)->where('status','open')],'candidate_name'=>'required|string|max:255','email'=>'nullable|email|max:255','notes'=>'nullable|string|max:10000']);
        DB::transaction(function()use($r,$organization,$data){$a=new JobApplication($data);$a->organization_id=$organization->id;$a->vacancy_id=$data['vacancy_id'];$a->stage='applied';$a->save();$this->event($r,$organization,'application',$a->id,'created');});return back()->with('status','Application recorded.');
    }
    public function updateApplication(Request $r,Organization $organization,JobApplication $application) {
        $this->permit($r,$organization,'recruitment.applications.update');$this->owns($organization,$application);
        $data=$r->validate(['stage'=>['required',Rule::in(['applied','screening','interview','offer','hired','rejected','withdrawn'])],'notes'=>'nullable|string|max:10000']);
        DB::transaction(function()use($r,$organization,$application,$data){$old=$application->stage;$application->update($data);$this->event($r,$organization,'application',$application->id,'updated',$old.' → '.$application->stage);});return back()->with('status','Application updated.');
    }
    public function cases(Request $r,Organization $organization) {
        $this->permit($r,$organization,'lifecycle.cases.view');
        $ids=app(EmployeeAccess::class)->filter(Employee::query(),$r->user(),$organization,'lifecycle.cases.view')->select('employees.id');
        $cases=LifecycleCase::where('organization_id',$organization->id)->whereIn('employee_id',$ids)->with(['employee','tasks'])->orderByDesc('id')->get();
        $employees=app(EmployeeAccess::class)->filter(Employee::query(),$r->user(),$organization,'employees.view')->orderBy('last_name')->orderBy('first_name')->get();
        $events=$r->user()->hasPermission('lifecycle.events.view',$organization)?DB::table('lifecycle_events as e')->leftJoin('users as u','u.id','=','e.actor_id')->where('e.organization_id',$organization->id)->where('e.entity_type','case')->whereIn('e.entity_id',$cases->pluck('id'))->select('e.*','u.name as actor_name')->orderByDesc('e.id')->limit(100)->get():collect();
        $events=$events->filter(fn($event)=>($case=$cases->firstWhere('id',$event->entity_id)) && app(EmployeeAccess::class)->allows($r->user(),$organization,'lifecycle.events.view',$case->employee));
        return view('lifecycle.cases',compact('organization','cases','employees','events'));
    }
    public function createCase(Request $r,Organization $organization) {
        $this->permit($r,$organization,'lifecycle.cases.create');
        $data=$r->validate(['employee_id'=>['required','integer',Rule::exists('employees','id')->where('organization_id',$organization->id)],'kind'=>['required',Rule::in(['onboarding','probation','offboarding'])],'start_date'=>'nullable|date_format:Y-m-d','due_date'=>'nullable|date_format:Y-m-d|after_or_equal:start_date','notes'=>'nullable|string|max:10000']);
        $employee=Employee::where('organization_id',$organization->id)->whereKey($data['employee_id'])->firstOrFail();$this->employee($r,$organization,$employee,'lifecycle.cases.create');
        DB::transaction(function()use($r,$organization,$employee,$data){$case=new LifecycleCase($data);$case->organization_id=$organization->id;$case->employee_id=$employee->id;$case->status='open';$case->save();$this->event($r,$organization,'case',$case->id,'created',$case->kind);});return back()->with('status','Lifecycle case created.');
    }
    public function addTask(Request $r,Organization $organization,LifecycleCase $case) {
        $this->owns($organization,$case);$employee=Employee::where('organization_id',$organization->id)->whereKey($case->employee_id)->firstOrFail();$this->employee($r,$organization,$employee,'lifecycle.tasks.create');$data=$r->validate(['title'=>'required|string|max:255']);
        DB::transaction(function()use($r,$organization,$case,$data){$case=LifecycleCase::whereKey($case->id)->lockForUpdate()->firstOrFail();abort_unless($case->status==='open',422);$task=new LifecycleTask($data);$task->organization_id=$organization->id;$task->lifecycle_case_id=$case->id;$task->save();$this->event($r,$organization,'case',$case->id,'task_added',$task->title);});return back()->with('status','Task added.');
    }
    public function updateTask(Request $r,Organization $organization,LifecycleCase $case,LifecycleTask $task) {
        $this->owns($organization,$case);$this->owns($organization,$task);abort_unless((int)$task->lifecycle_case_id===(int)$case->id,404);$employee=Employee::where('organization_id',$organization->id)->whereKey($case->employee_id)->firstOrFail();$this->employee($r,$organization,$employee,'lifecycle.tasks.update');$data=$r->validate(['completed'=>'required|boolean']);
        DB::transaction(function()use($r,$organization,$case,$task,$data){$case=LifecycleCase::whereKey($case->id)->lockForUpdate()->firstOrFail();abort_unless($case->status==='open',422);$task->refresh();$task->completed=(bool)$data['completed'];$task->completed_by=$task->completed?$r->user()->id:null;$task->completed_at=$task->completed?now():null;$task->save();$this->event($r,$organization,'case',$case->id,$task->completed?'task_completed':'task_reopened',$task->title);});return back()->with('status','Task updated.');
    }
    public function completeCase(Request $r,Organization $organization,LifecycleCase $case) {
        $this->owns($organization,$case);$employee=Employee::where('organization_id',$organization->id)->whereKey($case->employee_id)->firstOrFail();$this->employee($r,$organization,$employee,'lifecycle.cases.update');
        $data=$r->validate(['outcome'=>['nullable',Rule::in(['confirmed','extended','not_confirmed','completed'])]]);
        if ($case->kind==='probation' && !in_array($data['outcome']??null,['confirmed','extended','not_confirmed'],true)) throw ValidationException::withMessages(['outcome'=>'Select a probation outcome.']);
        DB::transaction(function()use($r,$organization,$case,$data){$case=LifecycleCase::whereKey($case->id)->lockForUpdate()->firstOrFail();abort_unless($case->status==='open',422);if ($case->tasks()->where('completed',false)->exists()) throw ValidationException::withMessages(['case'=>'Complete all checklist tasks first.']);$case->status='completed';$case->outcome=$data['outcome']??'completed';$case->save();$this->event($r,$organization,'case',$case->id,'completed',$case->outcome);});return back()->with('status','Lifecycle case completed.');
    }
}
