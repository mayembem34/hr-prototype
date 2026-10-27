<?php
namespace Tests\Feature;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class StaffChatTest extends TestCase {
 use RefreshDatabase;
 private function member(Organization $o,array $keys): User {$u=User::factory()->create();$o->users()->attach($u);$role=new Role(['name'=>'Chat '.$u->id]);$role->organization_id=$o->id;$role->save();$role->permissions()->sync(Permission::whereIn('key',$keys)->pluck('id'));DB::table('organization_role_user')->insert(['organization_id'=>$o->id,'role_id'=>$role->id,'user_id'=>$u->id]);return $u;}
 public function test_general_chat_is_organization_isolated_and_send_requires_permission(): void {
  $a=Organization::create(['name'=>'A']);$b=Organization::create(['name'=>'B']);$sender=$this->member($a,['chat.general.view','chat.general.send']);$reader=$this->member($a,['chat.general.view']);$outsider=$this->member($b,['chat.general.view','chat.general.send']);$url='/organizations/'.$a->id.'/staff-chat';
  $this->actingAs($sender)->post($url.'/messages',['body'=>'Hello organization'])->assertRedirect();$this->actingAs($reader)->get($url)->assertOk()->assertSee('Hello organization');$this->post($url.'/messages',['body'=>'Not allowed'])->assertForbidden();$this->actingAs($outsider)->get($url)->assertForbidden();$this->get('/organizations/'.$b->id.'/staff-chat')->assertOk()->assertDontSee('Hello organization');
  DB::table('organization_user')->where('organization_id',$a->id)->where('user_id',$sender->id)->update(['is_active'=>false]);$this->actingAs($sender)->get($url)->assertForbidden();$this->post($url.'/messages',['body'=>'No access'])->assertForbidden();
 }
 public function test_direct_messages_are_private_and_require_active_authorized_recipients(): void {
  $a=Organization::create(['name'=>'A']);$b=Organization::create(['name'=>'B']);$perms=['chat.direct.view','chat.direct.send'];$alice=$this->member($a,$perms);$bob=$this->member($a,['chat.direct.view']);$eve=$this->member($a,$perms);$foreign=$this->member($b,$perms);$base='/organizations/'.$a->id.'/staff-chat/direct/';
  $this->actingAs($alice)->post($base.$bob->id,['body'=>'Private hello'])->assertRedirect();$this->actingAs($bob)->get($base.$alice->id)->assertOk()->assertSee('Private hello');$this->post($base.$alice->id,['body'=>'No send'])->assertForbidden();$this->actingAs($eve)->get($base.$alice->id)->assertOk()->assertDontSee('Private hello');$this->get($base.$foreign->id)->assertNotFound();$this->post($base.$foreign->id,['body'=>'No'])->assertNotFound();
  DB::table('organization_user')->where('organization_id',$a->id)->where('user_id',$bob->id)->update(['is_active'=>false]);$this->actingAs($alice)->post($base.$bob->id,['body'=>'No longer available'])->assertSessionHasErrors('body');$this->actingAs($bob)->get($base.$alice->id)->assertForbidden();
 }
}
