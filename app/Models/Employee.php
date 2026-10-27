<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Employee extends Model {
    protected $fillable = ['employee_number','first_name','last_name','email','phone','job_title','employment_type','start_date','status','unit_id','manager_id'];
    protected function casts(): array { return ['start_date' => 'date']; }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
    public function manager(): BelongsTo { return $this->belongsTo(Employee::class, 'manager_id'); }
}
