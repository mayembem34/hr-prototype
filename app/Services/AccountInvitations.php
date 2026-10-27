<?php
namespace App\Services;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;
class AccountInvitations {
    public function send(Organization $organization, User $user, User $actor): bool {
        $token=Str::random(64);
        DB::transaction(function () use ($organization,$user,$actor,$token) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            abort_unless(DB::table('organization_user')->where('organization_id',$organization->id)->where('user_id',$user->id)->where('is_active',true)->exists(),403);
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->must_set_password, 422, 'This account is already active.');
            DB::table('account_invitations')->where('user_id',$user->id)->whereNull('used_at')->delete();
            DB::table('account_invitations')->insert(['organization_id'=>$organization->id,'user_id'=>$user->id,'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addHours(48),'created_at'=>now(),'updated_at'=>now()]);
            DB::table('invitation_events')->insert(['organization_id'=>$organization->id,'user_id'=>$user->id,'actor_id'=>$actor->id,'action'=>'issued','created_at'=>now(),'updated_at'=>now()]);
        });
        try {
            Mail::send('emails.invitation',['name'=>$user->name,'organizationName'=>$organization->name,'url'=>route('invitations.form',$token)],function ($m) use ($user) { $m->to($user->email)->subject('Set up your HR account'); });
            return true;
        } catch (Throwable $e) {
            report($e); DB::table('invitation_events')->insert(['organization_id'=>$organization->id,'user_id'=>$user->id,'actor_id'=>$actor->id,'action'=>'delivery_failed','created_at'=>now(),'updated_at'=>now()]); return false;
        }
    }
}
