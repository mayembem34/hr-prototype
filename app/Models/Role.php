<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Role extends Model {
    protected $fillable = ['name'];
    public function permissions() { return $this->belongsToMany(Permission::class)->withPivot(['access_scope','unit_id']); }
}
