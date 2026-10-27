<?php
namespace App\Services;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EmployeeAccess {
    public static function scopedPermission(string $key): bool {
        return (str_starts_with($key, 'employees.') && $key !== 'employees.accounts.assign') || str_starts_with($key, 'documents.') || str_starts_with($key, 'lifecycle.') || in_array($key, ['leave.requests.view','leave.requests.supervisor_approve'], true);
    }
    private function grants(User $user, Organization $org, string $permission): array {
        if (! self::scopedPermission($permission) || ! $user->hasPermission($permission, $org)) return [];
        if ($user->is_platform_admin || $user->organizations()->where('organizations.id', $org->id)->wherePivot('is_admin', true)->exists()) return [['access_scope'=>'organization', 'unit_id'=>null]];
        return DB::table('organization_role_user as a')->join('roles as r','r.id','=','a.role_id')
            ->join('permission_role as pr','pr.role_id','=','r.id')->join('permissions as p','p.id','=','pr.permission_id')
            ->where('a.organization_id',$org->id)->where('r.organization_id',$org->id)->where('a.user_id',$user->id)->where('p.key',$permission)
            ->get(['pr.access_scope','pr.unit_id'])->map(fn ($g)=>(array)$g)->all();
    }
    private function unitIds(Organization $org, int $root, bool $includeChildren): array {
        $units=DB::table('units')->where('organization_id',$org->id)->get(['id','parent_id']);
        if (! $units->contains('id',$root)) return [];
        $ids=[$root]; if (! $includeChildren) return $ids;
        do { $before=count($ids); foreach ($units as $unit) if ($unit->parent_id !== null && in_array((int)$unit->parent_id,$ids,true) && !in_array((int)$unit->id,$ids,true)) $ids[]=(int)$unit->id; } while (count($ids)>$before);
        return $ids;
    }
    public function filter(Builder $query, User $user, Organization $org, string $permission): Builder {
        $grants=$this->grants($user,$org,$permission); $own=false; $units=[]; $all=false;
        foreach ($grants as $grant) {
            if ($grant['access_scope']==='organization') $all=true;
            elseif ($grant['access_scope']==='own') $own=true;
            elseif (in_array($grant['access_scope'],['unit','unit_tree'],true) && $grant['unit_id']) $units=array_merge($units,$this->unitIds($org,(int)$grant['unit_id'],$grant['access_scope']==='unit_tree'));
        }
        $query->where('employees.organization_id',$org->id);
        if ($all) return $query;
        return $query->where(function (Builder $q) use ($own,$units,$user) {
            $q->whereRaw('1 = 0');
            if ($own) $q->orWhere('employees.user_id',$user->id);
            if ($units) $q->orWhereIn('employees.unit_id',array_unique($units));
        });
    }
    public function allows(User $user, Organization $org, string $permission, Employee $employee): bool {
        if ((int)$employee->organization_id !== (int)$org->id) return false;
        foreach ($this->grants($user,$org,$permission) as $grant) {
            if ($grant['access_scope']==='organization') return true;
            if ($grant['access_scope']==='own' && $employee->user_id && (int)$employee->user_id===(int)$user->id) return true;
            if (in_array($grant['access_scope'],['unit','unit_tree'],true) && $grant['unit_id'] && $employee->unit_id && in_array((int)$employee->unit_id,$this->unitIds($org,(int)$grant['unit_id'],$grant['access_scope']==='unit_tree'),true)) return true;
        }
        return false;
    }
}
