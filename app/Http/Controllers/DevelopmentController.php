<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Organization;
use App\Services\EmployeeAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DevelopmentController extends Controller {
    private const AREAS = ['performance'=>'Performance reviews', 'training'=>'Training', 'qualifications'=>'Qualifications', 'career'=>'Career development'];
    private function area(string $area): string { abort_unless(isset(self::AREAS[$area]),404); return self::AREAS[$area]; }
    private function employee(Request $request, Organization $organization, Employee $employee, string $permission): void {
        abort_unless((int) $employee->organization_id === (int) $organization->id,404);
        abort_unless(app(EmployeeAccess::class)->allows($request->user(),$organization,$permission,$employee),403);
    }
    private function fields(Request $request): array {
        return $request->validate(['title'=>'required|string|max:255','details'=>'nullable|string|max:10000','status'=>['required',Rule::in(['planned','in_progress','completed','cancelled'])],'start_date'=>'nullable|date_format:Y-m-d','due_date'=>'nullable|date_format:Y-m-d|after_or_equal:start_date','completed_date'=>'nullable|date_format:Y-m-d','provider'=>'nullable|string|max:255','reference'=>'nullable|string|max:255']);
    }
    public function index(Request $request, Organization $organization, string $area) {
        $label=$this->area($area); $permission="development.$area.view";
        abort_unless($request->user()->hasPermission($permission,$organization),403);
        $ids=app(EmployeeAccess::class)->filter(Employee::query(),$request->user(),$organization,$permission)->select('employees.id');
        $records=DB::table('development_records as d')->join('employees as e','e.id','=','d.employee_id')->where('d.organization_id',$organization->id)->where('e.organization_id',$organization->id)->where('d.area',$area)->whereIn('d.employee_id',$ids)->select('d.*','e.first_name','e.last_name')->orderByDesc('d.id')->get();
        $employees=app(EmployeeAccess::class)->filter(Employee::query(),$request->user(),$organization,"development.$area.create")->orderBy('last_name')->orderBy('first_name')->get();
        return view('development.index',compact('organization','area','label','records','employees'));
    }
    public function store(Request $request, Organization $organization, string $area) {
        $this->area($area); $data=$this->fields($request);
        $employeeData=$request->validate(['employee_id'=>['required','integer',Rule::exists('employees','id')->where('organization_id',$organization->id)]]);
        $employee=Employee::where('organization_id',$organization->id)->findOrFail($employeeData['employee_id']);
        $this->employee($request,$organization,$employee,"development.$area.create");
        DB::table('development_records')->insert(array_merge($data,['organization_id'=>$organization->id,'employee_id'=>$employee->id,'area'=>$area,'created_by'=>$request->user()->id,'updated_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]));
        return back()->with('status','Development record created.');
    }
    public function update(Request $request, Organization $organization, string $area, int $record) {
        $this->area($area);
        $entry=DB::table('development_records')->where('organization_id',$organization->id)->where('area',$area)->where('id',$record)->first(); abort_unless($entry,404);
        $employee=Employee::where('organization_id',$organization->id)->findOrFail($entry->employee_id);
        $this->employee($request,$organization,$employee,"development.$area.update");
        $data=$this->fields($request);
        DB::table('development_records')->where('organization_id',$organization->id)->where('area',$area)->where('id',$record)->update(array_merge($data,['updated_by'=>$request->user()->id,'updated_at'=>now()]));
        return back()->with('status','Development record updated.');
    }
}
