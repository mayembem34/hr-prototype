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
    public function organizations() { return $this->belongsToMany(Organization::class)->withPivot('is_admin','is_active')->wherePivot('is_active',true)->withTimestamps(); }
    public function hasPermission(string $key, ?Organization $organization = null): bool {
        $permission = \App\Models\Permission::where('key', $key)->first();
        if (! $permission) return false;
        if ($permission->scope === 'session') return true;
        if ($permission->scope === 'public') return true;
        if ($permission->scope === 'platform') return (bool) $this->is_platform_admin;
        if (! $organization) return false;
        if ($this->is_platform_admin) return true;
        $member = $this->organizations()->where('organizations.id', $organization->id)->first();
        if (! $member) return false;
        if ($member->pivot->is_admin) return true;
        if ($permission->scope === 'privileged') return false;
        return \Illuminate\Support\Facades\DB::table('organization_role_user as assignments')
            ->join('roles', 'roles.id', '=', 'assignments.role_id')
            ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('assignments.user_id', $this->id)->where('assignments.organization_id', $organization->id)
            ->where('roles.organization_id', $organization->id)->where('permissions.key', $key)->exists();
    }
    public function hasOrganizationPermission(string $key, Organization $organization): bool {
        if (! $this->hasPermission($key, $organization)) return false;
        if ($this->is_platform_admin || $this->organizations()->where('organizations.id', $organization->id)->wherePivot('is_admin', true)->exists()) return true;
        return \Illuminate\Support\Facades\DB::table('organization_role_user as a')->join('roles as r','r.id','=','a.role_id')->join('permission_role as pr','pr.role_id','=','r.id')->join('permissions as p','p.id','=','pr.permission_id')
            ->where('a.organization_id',$organization->id)->where('r.organization_id',$organization->id)->where('a.user_id',$this->id)->where('p.key',$key)->where('pr.access_scope','organization')->exists();
    }
    public function administers(Organization $organization): bool {
        return $this->is_platform_admin || $this->organizations()->whereKey($organization->id)->wherePivot('is_admin', true)->exists();
    }
}
