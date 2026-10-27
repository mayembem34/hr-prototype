<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class DailyAlerts {
 public function send(int $organizationId,?int $userId,string $message): void {
  if ($userId && DB::table('organization_user')->where('organization_id',$organizationId)->where('user_id',$userId)->where('is_active',true)->exists()) DB::table('daily_notifications')->insert(['organization_id'=>$organizationId,'user_id'=>$userId,'message'=>$message,'created_at'=>now(),'updated_at'=>now()]);
 }
}
