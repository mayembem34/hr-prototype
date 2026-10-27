<?php
namespace App\Http\Controllers;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffChatController extends Controller {
 private function permit(Request $r,Organization $organization,string $permission): void {
  abort_unless($organization->users()->where('users.id',$r->user()->id)->exists() && $r->user()->hasPermission($permission,$organization),403);
 }
 private function peer(Organization $organization,int $userId): User {
  $peer=User::findOrFail($userId);abort_unless(DB::table('organization_user')->where('organization_id',$organization->id)->where('user_id',$userId)->exists(),404);return $peer;
 }
 public function index(Request $r,Organization $organization) {
  abort_unless($organization->users()->where('users.id',$r->user()->id)->exists(),403);$general=$r->user()->hasPermission('chat.general.view',$organization);$direct=$r->user()->hasPermission('chat.direct.view',$organization);abort_unless($general || $direct,403);
  $messages=$general?DB::table('staff_chat_messages as m')->join('users as u','u.id','=','m.sender_id')->where('m.organization_id',$organization->id)->whereNull('m.recipient_id')->orderByDesc('m.id')->select('m.*','u.name as sender_name')->paginate(50):null;
  $members=$direct?$organization->users()->where('users.id','!=',$r->user()->id)->orderBy('name')->get()->filter(fn($u)=>$u->hasPermission('chat.direct.view',$organization)):collect();
  return view('chat.index',compact('organization','messages','members','general','direct'));
 }
 public function sendGeneral(Request $r,Organization $organization) {
  $this->permit($r,$organization,'chat.general.view');$this->permit($r,$organization,'chat.general.send');$data=$r->validate(['body'=>'required|string|max:5000']);
  DB::transaction(function()use($r,$organization,$data){Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();$this->permit($r,$organization,'chat.general.view');$this->permit($r,$organization,'chat.general.send');DB::table('staff_chat_messages')->insert(['organization_id'=>$organization->id,'sender_id'=>$r->user()->id,'recipient_id'=>null,'body'=>$data['body'],'created_at'=>now(),'updated_at'=>now()]);});return redirect()->route('chat.index',$organization)->with('status','Message sent.');
 }
 public function direct(Request $r,Organization $organization,int $peer) {
  $this->permit($r,$organization,'chat.direct.view');$member=$this->peer($organization,$peer);abort_if($member->id===$r->user()->id,404);
  $me=$r->user()->id;$messages=DB::table('staff_chat_messages as m')->join('users as u','u.id','=','m.sender_id')->where('m.organization_id',$organization->id)->where(function($q)use($me,$peer){$q->where(fn($q)=>$q->where('m.sender_id',$me)->where('m.recipient_id',$peer))->orWhere(fn($q)=>$q->where('m.sender_id',$peer)->where('m.recipient_id',$me));})->select('m.*','u.name as sender_name')->orderByDesc('m.id')->paginate(50);
  $canReceive=$organization->users()->where('users.id',$member->id)->exists() && $member->hasPermission('chat.direct.view',$organization);return view('chat.direct',compact('organization','member','messages','canReceive'));
 }
 public function sendDirect(Request $r,Organization $organization,int $peer) {
  $this->permit($r,$organization,'chat.direct.view');$this->permit($r,$organization,'chat.direct.send');$data=$r->validate(['body'=>'required|string|max:5000']);
  DB::transaction(function()use($r,$organization,$peer,$data){Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();$this->permit($r,$organization,'chat.direct.view');$this->permit($r,$organization,'chat.direct.send');$member=$this->peer($organization,$peer);abort_if($member->id===$r->user()->id,404);if(!$organization->users()->where('users.id',$member->id)->exists() || !$member->hasPermission('chat.direct.view',$organization)) throw ValidationException::withMessages(['body'=>'This member cannot receive direct messages.']);DB::table('staff_chat_messages')->insert(['organization_id'=>$organization->id,'sender_id'=>$r->user()->id,'recipient_id'=>$member->id,'body'=>$data['body'],'created_at'=>now(),'updated_at'=>now()]);});return redirect()->route('chat.direct',[$organization,$peer])->with('status','Message sent.');
 }
}
