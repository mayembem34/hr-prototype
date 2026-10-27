<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LifecycleCase extends Model { protected $fillable=['kind','start_date','due_date','notes','outcome','status']; public function employee(){return $this->belongsTo(Employee::class);} public function tasks(){return $this->hasMany(LifecycleTask::class);} }
