<?php
namespace App\Http\Controllers;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Services\AccountInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class InvitationController extends Controller {
    private function publicAccess(): void { abort_unless(Permission::where('key','invitations.accept')->where('scope','public')->exists(),403); }
    private function tokenRow(string $token) { return DB::table('account_invitations')->where('token_hash',hash('sha256',$token))->whereNull('used_at')->where('expires_at','>',now())->first(); }
    public function form(string $token) {
        $this->publicAccess(); abort_unless(strlen($token)===64 && $this->tokenRow($token),404); return view('invitations.accept',compact('token'));
    }
    public function accept(Request $request,string $token) {
        $this->publicAccess(); abort_unless(strlen($token)===64,404); $data=$request->validate(['password'=>'required|string|min:12|confirmed']);
        DB::transaction(function () use ($token,$data) {
            $invite=DB::table('account_invitations')->where('token_hash',hash('sha256',$token))->whereNull('used_at')->where('expires_at','>',now())->lockForUpdate()->first();
            abort_unless($invite,404); $user=User::whereKey($invite->user_id)->lockForUpdate()->firstOrFail(); abort_unless($user->must_set_password,404);
            $user->password=Hash::make($data['password']); $user->must_set_password=false; $user->email_verified_at=now(); $user->save();
            DB::table('account_invitations')->where('user_id',$user->id)->whereNull('used_at')->update(['used_at'=>now(),'updated_at'=>now()]);
            DB::table('invitation_events')->insert(['organization_id'=>$invite->organization_id,'user_id'=>$user->id,'actor_id'=>$user->id,'action'=>'accepted','created_at'=>now(),'updated_at'=>now()]);
        });
        return redirect()->route('login')->with('status','Your password is set. Please sign in.');
    }
    public function resend(Request $request,Organization $organization,User $member) {
        abort_unless($request->user()->hasPermission('invitations.send',$organization),403);
        abort_unless($organization->users()->where('users.id',$member->id)->exists(),404); abort_unless($member->must_set_password,422,'This account is already active.');
        $sent=app(AccountInvitations::class)->send($organization,$member,$request->user());
        return back()->with('status',$sent?'Invitation sent.':'Invitation could not be sent. Check mail configuration and try again.');
    }
}
