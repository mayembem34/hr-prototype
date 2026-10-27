<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable {
    use HasFactory, Notifiable;
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_platform_admin' => 'boolean']; }
    public function organizations() { return $this->belongsToMany(Organization::class)->withPivot('is_admin')->withTimestamps(); }
    public function administers(Organization $organization): bool {
        return $this->is_platform_admin || $this->organizations()->whereKey($organization->id)->wherePivot('is_admin', true)->exists();
    }
}
