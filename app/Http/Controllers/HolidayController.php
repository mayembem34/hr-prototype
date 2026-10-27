<?php
namespace App\Http\Controllers;
use App\Models\Organization;
use App\Models\OrganizationHoliday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class HolidayController extends Controller {
    private function allowed(Request $request,Organization $org,string $permission): void { abort_unless($request->user()->hasPermission($permission,$org),403); }
    private function validateHoliday(Request $request,Organization $org,?OrganizationHoliday $holiday=null): array {
        return $request->validate(['name'=>'required|string|max:255','date'=>['required','date_format:Y-m-d',Rule::unique('organization_holidays','date')->where('organization_id',$org->id)->ignore($holiday?->id)]]);
    }
    public function index(Request $request,Organization $organization) {
        $this->allowed($request,$organization,'leave.holidays.view');
        $holidays=OrganizationHoliday::where('organization_id',$organization->id)->orderBy('date')->get(); return view('leave.holidays',compact('organization','holidays'));
    }
    public function store(Request $request,Organization $organization) {
        $this->allowed($request,$organization,'leave.holidays.create');$data=$this->validateHoliday($request,$organization);
        DB::transaction(function() use($organization,$data) { Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();$holiday=new OrganizationHoliday($data);$holiday->organization_id=$organization->id;$holiday->save(); });
        return back()->with('status','Holiday added.');
    }
    public function update(Request $request,Organization $organization,OrganizationHoliday $holiday) {
        $this->allowed($request,$organization,'leave.holidays.update');abort_unless((int)$holiday->organization_id===(int)$organization->id,404);$data=$this->validateHoliday($request,$organization,$holiday);
        DB::transaction(function() use($organization,$holiday,$data) { Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();$holiday->update($data); });
        return back()->with('status','Holiday updated.');
    }
    public function delete(Request $request,Organization $organization,OrganizationHoliday $holiday) {
        $this->allowed($request,$organization,'leave.holidays.delete');abort_unless((int)$holiday->organization_id===(int)$organization->id,404);
        DB::transaction(function() use($organization,$holiday) { Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();$holiday->delete(); });
        return back()->with('status','Holiday deleted.');
    }
}
