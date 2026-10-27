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
        return [$start,$end,(int)$start->diffInDays($end)+1];
    }
    private function consumed(Organization $org,Employee $employee,LeaveType $type,int $year): int {
        return (int) LeaveRequest::where('organization_id',$org->id)->where('employee_id',$employee->id)->where('leave_type_id',$type->id)->whereYear('start_date',$year)->whereIn('status',['pending','approved'])->sum('days');
    }
    public function mine(Request $request,Organization $organization) {
        $employee=$this->self($request,$organization,'leave.self.view'); $types=LeaveType::where('organization_id',$organization->id)->where('active',true)->orderBy('name')->get();
        $requests=LeaveRequest::where('organization_id',$organization->id)->where('employee_id',$employee->id)->with('type')->orderByDesc('id')->get();
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
            if (LeaveRequest::where('organization_id',$organization->id)->where('employee_id',$employee->id)->whereIn('status',['pending','approved'])->where('start_date','<=',$end->toDateString())->where('end_date','>=',$start->toDateString())->exists()) throw ValidationException::withMessages(['start_date'=>'This request overlaps an existing pending or approved request.']);
            $leave=new LeaveRequest(['start_date'=>$start->toDateString(),'end_date'=>$end->toDateString(),'days'=>$days,'reason'=>$data['reason']??null]); $leave->organization_id=$organization->id; $leave->employee_id=$employee->id; $leave->leave_type_id=$type->id; $leave->status='pending'; $leave->save();
        });
        return back()->with('status','Leave request submitted.');
    }
    public function cancel(Request $request,Organization $organization,LeaveRequest $leave) {
        $employee=$this->self($request,$organization,'leave.self.cancel');
        abort_unless((int)$leave->organization_id===(int)$organization->id && (int)$leave->employee_id===(int)$employee->id,404);
        DB::transaction(function () use ($leave) { $leave=LeaveRequest::whereKey($leave->id)->lockForUpdate()->firstOrFail(); abort_unless($leave->status==='pending',422); $leave->status='cancelled'; $leave->save(); }); return back()->with('status','Leave request cancelled.');
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
    public function reviews(Request $request,Organization $organization) {
        $this->op($request,$organization,'leave.requests.view');
        $eligible=app(EmployeeAccess::class)->filter(Employee::query(),$request->user(),$organization,'leave.requests.view')->select('employees.id');
        $requests=LeaveRequest::where('organization_id',$organization->id)->whereIn('employee_id',$eligible)->with(['employee','type'])->orderByDesc('id')->get();
        return view('leave.review',compact('organization','requests'));
    }
    public function decide(Request $request,Organization $organization,LeaveRequest $leave) {
        $this->op($request,$organization,'leave.requests.review'); abort_unless((int)$leave->organization_id===(int)$organization->id,404);
        $data=$request->validate(['decision'=>['required',Rule::in(['approved','rejected'])],'decision_note'=>'nullable|string|max:2000']);
        DB::transaction(function () use ($request,$organization,$leave,$data) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail(); $leave=LeaveRequest::whereKey($leave->id)->lockForUpdate()->firstOrFail();
            $employee=Employee::where('organization_id',$organization->id)->whereKey($leave->employee_id)->firstOrFail();
            abort_unless(app(EmployeeAccess::class)->allows($request->user(),$organization,'leave.requests.review',$employee),403);
            abort_if((int)$employee->user_id===(int)$request->user()->id,403,'You cannot approve your own leave.'); abort_unless($leave->status==='pending',422);
            if ($data['decision']==='approved') { $type=LeaveType::where('organization_id',$organization->id)->whereKey($leave->leave_type_id)->firstOrFail(); $year=CarbonImmutable::parse($leave->start_date)->year; if ($this->consumed($organization,$employee,$type,$year) > $type->annual_days) throw ValidationException::withMessages(['decision'=>'The allowance has changed and this request exceeds it.']); }
            $leave->status=$data['decision']; $leave->decision_note=$data['decision_note']??null; $leave->reviewer_id=$request->user()->id; $leave->decided_at=now(); $leave->save();
        }); return back()->with('status','Leave request '.$data['decision'].'.');
    }
}
