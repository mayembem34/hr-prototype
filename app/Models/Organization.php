<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Organization extends Model {
    protected $fillable=['name','logo_path','primary_color','accent_color'];
    public function users(): BelongsToMany { return $this->belongsToMany(User::class)->withPivot('is_admin','is_active')->wherePivot('is_active',true)->withTimestamps(); }
    public function units(): HasMany { return $this->hasMany(Unit::class); }
}
