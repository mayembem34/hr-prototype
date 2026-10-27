<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller {
    private const FIELDS = ['employee_number','first_name','last_name','email','phone','job_title','employment_type','start_date','status','unit_id','manager_id'];
    private function authorizeEmployee(Request $request, Organization $organization, string $permission): void {
        abort_unless($request->user()->hasPermission($permission, $organization), 403);
    }
    private function own(Organization $organization, Employee $employee): void {
        abort_unless((int) $employee->organization_id === (int) $organization->id, 404);
    }
    private function validateEmployee(Request $request, Organization $organization, ?Employee $employee = null): array {
        return $request->validate([
            'employee_number' => ['required','string','max:80',Rule::unique('employees')->where('organization_id', $organization->id)->ignore($employee?->id)],
            'first_name' => 'required|string|max:255', 'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:80', 'job_title' => 'nullable|string|max:255',
            'employment_type' => 'nullable|string|max:80', 'start_date' => 'nullable|date_format:Y-m-d',
            'status' => ['required', Rule::in(['active','on_leave','suspended','left'])],
            'unit_id' => ['nullable','integer', Rule::exists('units','id')->where('organization_id', $organization->id)],
            'manager_id' => ['nullable','integer', Rule::exists('employees','id')->where('organization_id', $organization->id)],
        ]);
    }
    private function snapshot(Employee $employee): array { return $employee->only(self::FIELDS); }
    private function record(Organization $organization, Employee $employee, Request $request, string $action, ?array $before): void {
        DB::table('employee_changes')->insert(['organization_id'=>$organization->id, 'employee_id'=>$employee->id, 'actor_id'=>$request->user()->id, 'action'=>$action, 'before'=>$before === null ? null : json_encode($before, JSON_THROW_ON_ERROR), 'after'=>json_encode($this->snapshot($employee), JSON_THROW_ON_ERROR), 'created_at'=>now(), 'updated_at'=>now()]);
    }
    private function referenceData(Organization $organization): array {
        return ['units' => $organization->units()->orderBy('name')->get(), 'managers' => Employee::where('organization_id', $organization->id)->orderBy('last_name')->orderBy('first_name')->get()];
    }
    public function index(Request $request, Organization $organization) {
        $this->authorizeEmployee($request, $organization, 'employees.view');
        $employees = Employee::where('organization_id', $organization->id)->with(['unit','manager'])->orderBy('last_name')->orderBy('first_name')->paginate(30);
        $reference = $request->user()->hasPermission('employees.create', $organization) ? $this->referenceData($organization) : ['units'=>collect(), 'managers'=>collect()];
        return view('employees.index', ['organization'=>$organization, 'employees'=>$employees] + $reference);
    }
    public function store(Request $request, Organization $organization) {
        $this->authorizeEmployee($request, $organization, 'employees.create');
        $data = $this->validateEmployee($request, $organization);
        $employee = DB::transaction(function () use ($organization, $request, $data) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $employee = new Employee($data); $employee->organization_id = $organization->id; $employee->save();
            $this->record($organization, $employee, $request, 'created', null); return $employee;
        });
        return $request->user()->hasPermission('employees.view', $organization) ? redirect()->route('employees.show', [$organization, $employee])->with('status', 'Employee created.') : redirect()->route('home')->with('status', 'Employee created.');
    }
    public function show(Request $request, Organization $organization, Employee $employee) {
        $this->authorizeEmployee($request, $organization, 'employees.view'); $this->own($organization, $employee);
        $employee->load(['unit','manager']);
        $reference = $request->user()->hasPermission('employees.update', $organization) ? $this->referenceData($organization) : ['units'=>collect(), 'managers'=>collect()];
        $changes = $request->user()->hasPermission('employees.history.view', $organization) ? DB::table('employee_changes')->leftJoin('users', 'users.id', '=', 'employee_changes.actor_id')->where('employee_changes.organization_id', $organization->id)->where('employee_changes.employee_id', $employee->id)->orderByDesc('employee_changes.id')->select('employee_changes.*','users.name as actor_name')->get() : collect();
        return view('employees.show', ['organization'=>$organization,'employee'=>$employee,'changes'=>$changes] + $reference);
    }
    public function update(Request $request, Organization $organization, Employee $employee) {
        $this->authorizeEmployee($request, $organization, 'employees.update'); $this->own($organization, $employee);
        $data = $this->validateEmployee($request, $organization, $employee);
        DB::transaction(function () use ($organization, $employee, $request, $data) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $employee->refresh();
            $cursor = $data['manager_id'] ?? null; $visited = [];
            while ($cursor !== null) {
                if ((int) $cursor === (int) $employee->id || isset($visited[$cursor])) throw ValidationException::withMessages(['manager_id'=>'A reporting line cannot contain a cycle.']);
                $visited[$cursor] = true;
                $cursor = Employee::where('organization_id', $organization->id)->whereKey($cursor)->value('manager_id');
            }
            $before = $this->snapshot($employee); $employee->fill($data);
            if ($employee->isDirty()) { $employee->save(); $this->record($organization, $employee, $request, 'updated', $before); }
        });
        return back()->with('status', 'Employee updated.');
    }
}
