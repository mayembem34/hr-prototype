<?php
namespace Tests\Feature;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class DevelopmentTest extends TestCase {
    use RefreshDatabase;
    private function employee(Organization $org,string $name,$unit=null): Employee { $e=new Employee(['employee_number'=>$name,'first_name'=>$name,'last_name'=>'Test','status'=>'active','unit_id'=>$unit?->id]); $e->organization_id=$org->id; $e->save(); return $e; }
    private function member(Organization $org,array $keys,$unit): User { $u=User::factory()->create(); $org->users()->attach($u); $role=new Role(['name'=>'Scoped '.$u->id]); $role->organization_id=$org->id; $role->save(); foreach($keys as $key) $role->permissions()->attach(Permission::where('key',$key)->value('id'),['access_scope'=>'unit','unit_id'=>$unit->id]); DB::table('organization_role_user')->insert(['organization_id'=>$org->id,'role_id'=>$role->id,'user_id'=>$u->id]); return $u; }
    public function test_each_area_can_create_view_and_update_records(): void {
        $org=Organization::create(['name'=>'A']); $admin=User::factory()->create(); $org->users()->attach($admin,['is_admin'=>true]); $employee=$this->employee($org,'Alice');
        foreach (['performance','training','qualifications','career'] as $area) {
            $url="/organizations/{$org->id}/development/$area";
            $this->actingAs($admin)->post($url,['employee_id'=>$employee->id,'title'=>"$area item",'status'=>'planned'])->assertRedirect();
            $record=DB::table('development_records')->where('area',$area)->first();
            $this->get($url)->assertOk()->assertSee("$area item");
            $this->post("$url/{$record->id}",['title'=>"$area updated",'status'=>'completed'])->assertRedirect();
            $this->assertDatabaseHas('development_records',['id'=>$record->id,'status'=>'completed','title'=>"$area updated"]);
        }
    }
    public function test_permissions_scopes_and_organization_isolation(): void {
        $a=Organization::create(['name'=>'A']); $b=Organization::create(['name'=>'B']); $inside=$a->units()->create(['name'=>'Inside']); $outside=$a->units()->create(['name'=>'Outside']);
        $one=$this->employee($a,'Inside',$inside); $two=$this->employee($a,'Outside',$outside); $foreign=$this->employee($b,'Foreign');
        $admin=User::factory()->create(); $a->users()->attach($admin,['is_admin'=>true]); $url="/organizations/{$a->id}/development/training";
        foreach ([$one,$two] as $employee) $this->actingAs($admin)->post($url,['employee_id'=>$employee->id,'title'=>$employee->first_name.' course','status'=>'planned'])->assertRedirect();
        $reader=$this->member($a,['development.training.view'],$inside);
        $this->actingAs($reader)->get($url)->assertOk()->assertSee('Inside course')->assertDontSee('Outside course');
        $this->post($url,['employee_id'=>$one->id,'title'=>'No','status'=>'planned'])->assertForbidden();
        $id=DB::table('development_records')->where('employee_id',$one->id)->value('id');
        $this->post("$url/$id",['title'=>'No','status'=>'completed'])->assertForbidden();
        $this->get("/organizations/{$a->id}/development/performance")->assertForbidden();
        $writer=$this->member($a,['development.training.view','development.training.create','development.training.update'],$inside);
        $this->actingAs($writer)->post($url,['employee_id'=>$two->id,'title'=>'No','status'=>'planned'])->assertForbidden();
        $this->post($url,['employee_id'=>$foreign->id,'title'=>'No','status'=>'planned'])->assertSessionHasErrors('employee_id');
        $outsideId=DB::table('development_records')->where('employee_id',$two->id)->value('id');
        $this->post("$url/$outsideId",['title'=>'No','status'=>'planned'])->assertForbidden();
        $this->post("/organizations/{$b->id}/development/training/$id",['title'=>'No','status'=>'planned'])->assertNotFound();
    }
}
