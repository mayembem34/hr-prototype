<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Unit extends Model {
    protected $fillable = ['name', 'parent_id'];
    public function parent() { return $this->belongsTo(Unit::class, 'parent_id'); }
    public function children() { return $this->hasMany(Unit::class, 'parent_id'); }
    public function head() { return $this->belongsTo(Employee::class, 'head_employee_id'); }
}
