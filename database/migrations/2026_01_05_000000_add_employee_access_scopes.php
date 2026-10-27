<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['organization_id','user_id'], 'employees_org_user_unique');
        });
        Schema::table('permission_role', function (Blueprint $table) {
            $table->string('access_scope', 20)->default('organization');
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
        });
        DB::table('permissions')->insert(['key'=>'employees.accounts.assign','label'=>'Link employee profiles to user accounts','scope'=>'organization','created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void {
        DB::table('permissions')->where('key','employees.accounts.assign')->delete();
        Schema::table('permission_role', function (Blueprint $table) { $table->dropConstrainedForeignId('unit_id'); $table->dropColumn('access_scope'); });
        Schema::table('employees', function (Blueprint $table) { $table->dropUnique('employees_org_user_unique'); $table->dropConstrainedForeignId('user_id'); });
    }
};
