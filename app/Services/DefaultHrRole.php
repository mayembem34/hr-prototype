<?php
namespace App\Services;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
class DefaultHrRole {
    public function createIfMissing(Organization $organization): void {
        if (Role::where('organization_id',$organization->id)->where('name','HR Manager')->exists()) return;
        $role=new Role(['name'=>'HR Manager']); $role->organization_id=$organization->id; $role->save();
        $permissions=Permission::whereIn('key',['organization.view','units.view','employees.view','documents.view','documents.preview','documents.download'])->pluck('id');
        $role->permissions()->sync($permissions);
    }
}
