<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SelfProfileController extends Controller {
    private const PERSONAL_FIELDS = ['first_name','last_name','email','phone','date_of_birth','address','emergency_contact_name','emergency_contact_phone'];
    private function authorizeSelf(Request $request, Organization $organization, string $permission): void {
        abort_unless($request->user()->hasPermission($permission), 403);
        abort_unless($organization->users()->where('users.id',$request->user()->id)->exists(), 403);
    }
    public function show(Request $request, Organization $organization) {
        $this->authorizeSelf($request,$organization,'profile.self.view');
        $employee=Employee::where('organization_id',$organization->id)->where('user_id',$request->user()->id)->first();
        return view('employees.self-profile',compact('organization','employee'));
    }
    public function save(Request $request, Organization $organization) {
        $this->authorizeSelf($request,$organization,'profile.self.update');
        $data=$request->validate([
            'first_name'=>'required|string|max:255', 'last_name'=>'required|string|max:255', 'email'=>'nullable|email|max:255', 'phone'=>'nullable|string|max:80',
            'date_of_birth'=>'nullable|date_format:Y-m-d|before_or_equal:today', 'address'=>'nullable|string|max:500',
            'emergency_contact_name'=>'nullable|string|max:255', 'emergency_contact_phone'=>'nullable|string|max:80',
        ]);
        DB::transaction(function () use ($request,$organization,$data) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $this->authorizeSelf($request,$organization,'profile.self.update');
            $employee=Employee::where('organization_id',$organization->id)->where('user_id',$request->user()->id)->first();
            $before=$employee ? $employee->only(self::PERSONAL_FIELDS) : null;
            if (! $employee) {
                $employee=new Employee(); $employee->organization_id=$organization->id; $employee->user_id=$request->user()->id;
                $employee->employee_number='AUTO-'.Str::uuid(); $employee->status='active';
            }
            $employee->fill($data);
            if ($employee->isDirty()) {
                $isNew=!$employee->exists; $employee->save();
                DB::table('employee_changes')->insert(['organization_id'=>$organization->id,'employee_id'=>$employee->id,'actor_id'=>$request->user()->id,'action'=>$isNew?'self_created':'self_updated','before'=>$before===null?null:json_encode($before,JSON_THROW_ON_ERROR),'after'=>json_encode($employee->only(self::PERSONAL_FIELDS),JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
            }
        });
        return redirect()->route('self-profile.show',$organization)->with('status','Your employee profile has been saved.');
    }
}
