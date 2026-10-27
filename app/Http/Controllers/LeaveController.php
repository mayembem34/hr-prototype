<?php
namespace App\Http\Controllers;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Services\EmployeeAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class LeaveController extends Controller {
    private function self(Request $request,Organization $org,string $permission): Employee {
        abort_unless($request->user()->hasPermission($permission),403);
        abort_unless($org->users()->where('users.id',$request->user()->id)->exists(),403);
        return Employee::where('organization_id',$org->id)->where('user_id',$request->user()->id)->firstOrFail();
    }
    private function op(Request $request,Organization $org,string $permission): void { abort_unless($request->user()->hasPermission($permission,$org),403); }
    private function dateInfo(array $data): array {
        $start=CarbonImmutable::parse($data['start_date'])->startOfDay(); $end=CarbonImmutable::parse($data['end_date'])->startOfDay();
        if ($start->year !== $end->year) throw ValidationException::withMessages(['end_date'=>'Submit separate requests for each calendar year.']);
        $days=0;
        for ($date=$start; $date->lessThanOrEqualTo($end); $date=$date->addDay()) {
            if ($date->dayOfWeekIso <= 5) $days++;
        }
        if ($days===0) throw ValidationException::withMessages(['end_date'=>'The selected dates must include at least one weekday.']);
        return [$start,$end,$days];
    }
    private function consumed(Organization $org,Employee $employee,LeaveType $type,int $year): int {
        return (int) LeaveRequest::where('organization_id',$org->id)->where('employee_id',$employee->id)->where('leave_type_id',$type->id)->whereYear('start_date',$year)->whereIn('status',['pending_supervisor','pending_officer','approved'])->sum('days');
    }
    public function mine(Request $request,Organization $organization) {
        $employee=$this->self($request,$organization,'leave.self.view'); $types=LeaveType::where('organization_id',$organization->id)->where('active',true)->orderBy('name')->get();
        $requests=LeaveRequest::where('organization_id',$organization->id)->where('employee_id',$employee->id)->with(['type','steps.user','events.actor'])->orderByDesc('id')->get();
        $balances=$types->mapWithKeys(fn($type)=>[$type->id=>max(0,$type->annual_days-$this->consumed($organization,$employee,$type,now()->year))]);
        return view('leave.mine',compact('organization','employee','types','requests','balances'));
    }
    public function requestLeave(Request $request,Organization $organization) {
        $employee=$this->self($request,$organization,'leave.self.request');
        $data=$request->validate(['leave_type_id'=>['required','integer',Rule::exists('leave_types','id')->where('organization_id',$organization->id)->where('active',true)],'start_date'=>'required|date_format:Y-m-d','end_date'=>'required|date_format:Y-m-d|after_or_equal:start_date','reason'=>'nullable|string|max:2000']);
        [$start,$end,$days]=$this->dateInfo($data);
        DB::transaction(function () use ($request,$organization,$employee,$data,$start,$end,$days) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $employee=$this->self($request,$organization,'leave.self.request');
            $type=LeaveType::where('organization_id',$organization->id)->whereKey($data['leave_type_id'])->where('active',true)->firstOrFail();
            if ($this->consumed($organization,$employee,$type,$start->year)+$days > $type->annual_days) throw ValidationException::withMessages(['end_date'=>'The request exceeds the available annual allowance.']);
            if (LeaveRequest::where('organization_id',$organization->id)->where('employee_id',$employee->id)->whereIn('status',['pending_supervisor','pending_officer','approved'])->where('start_date','<=',$end->toDateString())->where('end_date','>=',$start->toDateString())->exists()) throw ValidationException::withMessages(['start_date'=>'This request overlaps an existing pending or approved request.']);
            $supervisor=$employee->manager_id ? Employee::where('organization_id',$organization->id)->whereKey($employee->manager_id)->first() : null;
            if (! $supervisor?->user_id || ! $organization->users()->where('users.id',$supervisor->user_id)->exists() || (int)$supervisor->user_id === (int)$request->user()->id) throw ValidationException::withMessages(['start_date'=>'An immediate supervisor with an organization account must be assigned before requesting leave.']);
            $officers=DB::table('leave_approving_officers')->where('organization_id',$organization->id)->orderBy('position')->get();
            if ($officers->contains('user_id',$request->user()->id)) throw ValidationException::withMessages(['start_date'=>'You are configured as an approving officer. Ask an administrator to update the approval route before submitting your own leave.']);
            $leave=new LeaveRequest(['start_date'=>$start->toDateString(),'end_date'=>$end->toDateString(),'days'=>$days,'reason'=>$data['reason']??null]); $leave->organization_id=$organization->id; $leave->employee_id=$employee->id; $leave->leave_type_id=$type->id; $leave->status='pending_supervisor'; $leave->save();
            DB::table('leave_approval_steps')->insert(['organization_id'=>$organization->id,'leave_request_id'=>$leave->id,'user_id'=>$supervisor->user_id,'position'=>0,'stage'=>'supervisor','status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
            foreach ($officers as $index=>$officer) DB::table('leave_approval_steps')->insert(['organization_id'=>$organization->id,'leave_request_id'=>$leave->id,'user_id'=>$officer->user_id,'position'=>$index+1,'stage'=>'officer','status'=>'queued','created_at'=>now(),'updated_at'=>now()]);
            $this->recordEvent($organization,$leave,$request,'request','submitted',null);
        });
        return back()->with('status','Leave request submitted.');
    }
    public function cancel(Request $request,Organization $organization,LeaveRequest $leave) {
        $employee=$this->self($request,$organization,'leave.self.cancel');
        abort_unless((int)$leave->organization_id===(int)$organization->id && (int)$leave->employee_id===(int)$employee->id,404);
        DB::transaction(function () use ($leave,$organization,$request) { $leave=LeaveRequest::whereKey($leave->id)->lockForUpdate()->firstOrFail(); abort_unless(in_array($leave->status,['pending_supervisor','pending_officer'],true),422); $leave->status='cancelled'; $leave->save(); DB::table('leave_approval_steps')->where('leave_request_id',$leave->id)->whereIn('status',['pending','queued'])->update(['status'=>'cancelled','updated_at'=>now()]); $this->recordEvent($organization,$leave,$request,'request','cancelled',null); }); return back()->with('status','Leave request cancelled.');
    }
    public function manage(Request $request,Organization $organization) {
        $this->op($request,$organization,'leave.types.view');
        $types=LeaveType::where('organization_id',$organization->id)->orderBy('name')->get();
        return view('leave.types',compact('organization','types'));
    }
    public function createType(Request $request,Organization $organization) {
        $this->op($request,$organization,'leave.types.manage');
        $data=$request->validate(['name'=>['required','string','max:255',Rule::unique('leave_types')->where('organization_id',$organization->id)],'annual_days'=>'required|integer|min:0|max:366']);
        $type=new LeaveType($data); $type->organization_id=$organization->id; $type->save(); return back()->with('status','Leave type created.');
    }
    public function updateType(Request $request,Organization $organization,LeaveType $type) {
        $this->op($request,$organization,'leave.types.manage'); abort_unless((int)$type->organization_id===(int)$organization->id,404);
        $data=$request->validate(['name'=>['required','string','max:255',Rule::unique('leave_types')->where('organization_id',$organization->id)->ignore($type->id)],'annual_days'=>'required|integer|min:0|max:366','active'=>'required|boolean']);
        DB::transaction(function () use ($type,$organization,$data) { Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail(); $type->update($data); }); return back()->with('status','Leave type updated.');
    }
    private function recordEvent(Organization $organization, LeaveRequest $leave, Request $request, string $stage, string $decision, ?string $note): void {
        DB::table('leave_approval_events')->insert(['organization_id'=>$organization->id,'leave_request_id'=>$leave->id,'actor_id'=>$request->user()->id,'stage'=>$stage,'decision'=>$decision,'note'=>$note,'created_at'=>now(),'updated_at'=>now()]);
    }
    private function isSupervisor(Request $request, Organization $organization, Employee $employee): bool {
        return $employee->manager_id !== null && Employee::where('organization_id',$organization->id)->whereKey($employee->manager_id)->where('user_id',$request->user()->id)->exists();
    }
    public function reviewers(Request $request,Organization $organization) {
        $this->op($request,$organization,'leave.approvers.view');
        $officers=DB::table('leave_approving_officers as o')->join('users as u','u.id','=','o.user_id')->where('o.organization_id',$organization->id)->orderBy('o.position')->get(['o.position','o.user_id','u.name','u.email']);
        $members=$request->user()->hasPermission('members.view',$organization) ? $organization->users()->orderBy('name')->get() : collect();
        return view('leave.officers',compact('organization','officers','members'));
    }
    public function addOfficer(Request $request,Organization $organization) {
        $this->op($request,$organization,'leave.approvers.manage');
        $data=$request->validate(['user_id'=>['required','integer',Rule::exists('organization_user','user_id')->where('organization_id',$organization->id),Rule::unique('leave_approving_officers','user_id')->where('organization_id',$organization->id)],'position'=>['required','integer','min:1','max:9999',Rule::unique('leave_approving_officers','position')->where('organization_id',$organization->id)]]);
        $user=\App\Models\User::findOrFail($data['user_id']);
        if (! $user->hasPermission('leave.requests.officer_approve',$organization)) throw ValidationException::withMessages(['user_id'=>'The selected user needs the approving officer permission.']);
        DB::transaction(function () use ($organization,$data) { Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail(); DB::table('leave_approving_officers')->insert(['organization_id'=>$organization->id,'user_id'=>$data['user_id'],'position'=>$data['position'],'created_at'=>now(),'updated_at'=>now()]); });
        return back()->with('status','Approving officer added for future requests.');
    }
    public function removeOfficer(Request $request,Organization $organization,\App\Models\User $member) {
        $this->op($request,$organization,'leave.approvers.manage');
        DB::transaction(function () use ($organization,$member) { Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail(); DB::table('leave_approving_officers')->where('organization_id',$organization->id)->where('user_id',$member->id)->delete(); });
        return back()->with('status','Approving officer removed for future requests.');
    }
    public function reviews(Request $request,Organization $organization) {
        $user=$request->user();
        $all=$user->hasPermission('leave.requests.view_all',$organization);
        $view=$user->hasPermission('leave.requests.view',$organization);
        $supervisor=$user->hasPermission('leave.requests.supervisor_approve',$organization);
        $officer=$user->hasPermission('leave.requests.officer_approve',$organization);
        abort_unless($all || $view || $supervisor || $officer,403);
        $query=LeaveRequest::where('organization_id',$organization->id);
        if (! $all) {
            $visible=app(EmployeeAccess::class)->filter(Employee::query(),$user,$organization,'leave.requests.view')->select('employees.id');
            $managerIds=Employee::where('organization_id',$organization->id)->where('user_id',$user->id)->select('id');
            $supervisees=app(EmployeeAccess::class)->filter(Employee::query(),$user,$organization,'leave.requests.supervisor_approve')->whereIn('employees.manager_id',$managerIds)->select('employees.id');
            $assigned=DB::table('leave_approval_steps')->where('organization_id',$organization->id)->where('stage','officer')->where('user_id',$user->id)->where('status','pending')->select('leave_request_id');
            $query->where(function ($q) use ($visible,$supervisees,$assigned,$officer) { $q->whereIn('employee_id',$visible)->orWhereIn('employee_id',$supervisees); if ($officer) $q->orWhereIn('id',$assigned); });
        }
        $requests=$query->with(['employee','type','steps.user','events.actor'])->orderByDesc('id')->get();
        $members=$user->hasPermission('leave.approvers.manage',$organization) && $user->hasPermission('members.view',$organization) && $all ? $organization->users()->orderBy('name')->get() : collect();
        return view('leave.review',compact('organization','requests','members'));
    }
    public function addOfficerToRequest(Request $request,Organization $organization,LeaveRequest $leave) {
        $this->op($request,$organization,'leave.approvers.manage'); $this->op($request,$organization,'leave.requests.view_all');
        abort_unless((int)$leave->organization_id===(int)$organization->id,404);
        $data=$request->validate(['user_id'=>['required','integer',Rule::exists('organization_user','user_id')->where('organization_id',$organization->id)]]);
        DB::transaction(function () use ($request,$organization,$leave,$data) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $leave=LeaveRequest::whereKey($leave->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($leave->status,['pending_supervisor','pending_officer'],true),422);
            $employee=Employee::where('organization_id',$organization->id)->whereKey($leave->employee_id)->firstOrFail();
            $officer=\App\Models\User::findOrFail($data['user_id']);
            if ((int)$employee->user_id===(int)$officer->id || ! $officer->hasPermission('leave.requests.officer_approve',$organization)) throw ValidationException::withMessages(['user_id'=>'Select a different member with the approving officer permission.']);
            if (DB::table('leave_approval_steps')->where('leave_request_id',$leave->id)->where('stage','officer')->where('user_id',$officer->id)->exists()) throw ValidationException::withMessages(['user_id'=>'This officer is already on the approval route.']);
            $position=(int)DB::table('leave_approval_steps')->where('leave_request_id',$leave->id)->max('position')+1;
            DB::table('leave_approval_steps')->insert(['organization_id'=>$organization->id,'leave_request_id'=>$leave->id,'user_id'=>$officer->id,'position'=>$position,'stage'=>'officer','status'=>'queued','created_at'=>now(),'updated_at'=>now()]);
            $this->recordEvent($organization,$leave,$request,'route','officer_added',$officer->name);
        });
        return back()->with('status','Officer added to this application.');
    }
    public function decide(Request $request,Organization $organization,LeaveRequest $leave) {
        abort_unless((int)$leave->organization_id===(int)$organization->id,404);
        $data=$request->validate(['decision'=>['required',Rule::in(['approved','rejected'])],'decision_note'=>'nullable|string|max:2000']);
        DB::transaction(function () use ($request,$organization,$leave,$data) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail(); $leave=LeaveRequest::whereKey($leave->id)->lockForUpdate()->firstOrFail();
            $employee=Employee::where('organization_id',$organization->id)->whereKey($leave->employee_id)->firstOrFail();
            abort_if((int)$employee->user_id===(int)$request->user()->id,403,'You cannot approve your own leave.');
            $step=DB::table('leave_approval_steps')->where('organization_id',$organization->id)->where('leave_request_id',$leave->id)->where('status','pending')->orderBy('position')->lockForUpdate()->first();
            abort_unless($step && in_array($leave->status,['pending_supervisor','pending_officer'],true),422,'This request is no longer awaiting a decision.');
            if ($step->stage==='supervisor' && $leave->status==='pending_supervisor') {
                $this->op($request,$organization,'leave.requests.supervisor_approve');
                abort_unless($this->isSupervisor($request,$organization,$employee) && app(EmployeeAccess::class)->allows($request->user(),$organization,'leave.requests.supervisor_approve',$employee),403);
            } elseif ($step->stage==='officer' && $leave->status==='pending_officer') {
                $this->op($request,$organization,'leave.requests.officer_approve'); abort_unless((int)$step->user_id===(int)$request->user()->id,403);
            } else abort(422);
            if ($data['decision']==='approved') { $type=LeaveType::where('organization_id',$organization->id)->whereKey($leave->leave_type_id)->firstOrFail(); $year=CarbonImmutable::parse($leave->start_date)->year; if ($this->consumed($organization,$employee,$type,$year)>$type->annual_days) throw ValidationException::withMessages(['decision'=>'The allowance has changed and this request exceeds it.']); }
            DB::table('leave_approval_steps')->where('id',$step->id)->update(['user_id'=>$request->user()->id,'status'=>$data['decision'],'updated_at'=>now()]);
            $next=DB::table('leave_approval_steps')->where('organization_id',$organization->id)->where('leave_request_id',$leave->id)->where('status','queued')->orderBy('position')->first();
            if ($data['decision']==='rejected') { $leave->status='rejected'; DB::table('leave_approval_steps')->where('leave_request_id',$leave->id)->where('status','queued')->update(['status'=>'skipped','updated_at'=>now()]); }
            elseif ($next) { DB::table('leave_approval_steps')->where('id',$next->id)->update(['status'=>'pending','updated_at'=>now()]); $leave->status='pending_officer'; }
            else $leave->status='approved';
            $leave->decision_note=$data['decision_note']??null; $leave->reviewer_id=$request->user()->id; $leave->decided_at=now(); $leave->save();
            $this->recordEvent($organization,$leave,$request,$step->stage,$data['decision'],$data['decision_note']??null);
        }); return back()->with('status','Leave decision recorded.');
    }
}
