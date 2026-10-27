<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LeaveApprovalEvent extends Model {
    protected $guarded=['id'];
    public function actor() { return $this->belongsTo(User::class,'actor_id'); }
}
