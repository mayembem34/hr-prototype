<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id(); $table->string('key')->unique(); $table->string('label'); $table->string('scope'); $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->timestamps(); $table->unique(['organization_id', 'name']);
        });
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete(); $table->foreignId('permission_id')->constrained()->cascadeOnDelete(); $table->primary(['role_id', 'permission_id']);
        });
        Schema::create('organization_role_user', function (Blueprint $table) {
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('role_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['organization_id', 'role_id', 'user_id'], 'org_role_user_primary');
        });
        $permissions = [
            ['session.login', 'Sign in', 'public'], ['session.logout', 'Sign out', 'session'],
            ['organizations.list', 'List accessible organizations', 'session'], ['organizations.create', 'Create organizations', 'platform'],
            ['organization.view', 'View organization workspace', 'organization'],
            ['units.view', 'View units', 'organization'], ['units.create', 'Create units', 'organization'], ['units.update', 'Edit and move units', 'organization'],
            ['branding.view', 'View branding settings', 'organization'], ['branding.update', 'Update branding settings', 'organization'],
            ['admins.view', 'View organization administrators', 'organization'], ['admins.assign', 'Assign organization administrators', 'privileged'],
            ['roles.view', 'View roles and permissions', 'organization'], ['roles.create', 'Create roles', 'organization'],
            ['roles.update', 'Edit roles', 'organization'], ['roles.delete', 'Delete roles', 'organization'],
            ['members.view', 'View organization members', 'organization'], ['members.add', 'Add organization members', 'organization'],
            ['assignments.view', 'View role assignments', 'organization'], ['assignments.update', 'Assign and remove roles', 'organization'],
            ['help.view', 'View help and permission guidance', 'organization'],
        ];
        foreach ($permissions as [$key, $label, $scope]) DB::table('permissions')->insert(['key'=>$key, 'label'=>$label, 'scope'=>$scope, 'created_at'=>now(), 'updated_at'=>now()]);
    }
    public function down(): void {
        Schema::dropIfExists('organization_role_user'); Schema::dropIfExists('permission_role'); Schema::dropIfExists('roles'); Schema::dropIfExists('permissions');
    }
};
