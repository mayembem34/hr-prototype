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
class LayoutNavigationTest extends TestCase {
 use RefreshDatabase;
 public function test_member_navigation_respects_permissions_and_links_to_self_service(): void {
  $org=Organization::create(['name'=>'Example']);$user=User::factory()->create();$org->users()->attach($user);$e=new Employee(['employee_number'=>'E1','first_name'=>'A','last_name'=>'B','status'=>'active']);$e->organization_id=$org->id;$e->user_id=$user->id;$e->save();$role=new Role(['name'=>'Limited']);$role->organization_id=$org->id;$role->save();$role->permissions()->sync(Permission::whereIn('key',['organization.view','chat.general.view'])->pluck('id'));DB::table('organization_role_user')->insert(['organization_id'=>$org->id,'role_id'=>$role->id,'user_id'=>$user->id]);
  $this->actingAs($user)->get('/organizations/'.$org->id)->assertOk()->assertSee('My profile')->assertSee('My training')->assertSee('Staff chat')->assertDontSee('Recruitment')->assertDontSee('Approving officers');
 }
 public function test_guest_login_page_works_without_navigation(): void {$this->get('/login')->assertOk()->assertSee('HR Management')->assertDontSee('Application navigation');}
}
