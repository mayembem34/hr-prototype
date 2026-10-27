<?php
namespace App\Services;
use App\Models\LifecycleCase;
use Illuminate\Support\Facades\DB;
class LifecycleAlerts {
 public function notifyEmployee(LifecycleCase $case,string $message): void {
  $userId=$case->employee()->value('user_id');
  if($userId && DB::table('organization_user')->where('organization_id',$case->organization_id)->where('user_id',$userId)->where('is_active',true)->exists()) DB::table('lifecycle_notifications')->insert(['organization_id'=>$case->organization_id,'user_id'=>$userId,'message'=>$message,'created_at'=>now(),'updated_at'=>now()]);
 }
}
