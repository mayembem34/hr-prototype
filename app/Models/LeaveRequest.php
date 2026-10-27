<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LeaveRequest extends Model {
    protected $fillable=['start_date','end_date','days','reason'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function steps() { return $this->hasMany(LeaveApprovalStep::class, 'leave_request_id')->with('user')->orderBy('position'); }
    public function events() { return $this->hasMany(LeaveApprovalEvent::class, 'leave_request_id')->with('actor')->orderBy('id'); }
    public function type() { return $this->belongsTo(LeaveType::class,'leave_type_id'); }
}
