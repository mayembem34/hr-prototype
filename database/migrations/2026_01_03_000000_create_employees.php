<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->string('employee_number', 80); $table->string('first_name'); $table->string('last_name');
            $table->string('email')->nullable(); $table->string('phone', 80)->nullable();
            $table->string('job_title')->nullable(); $table->string('employment_type', 80)->nullable();
            $table->date('start_date')->nullable(); $table->string('status', 30)->default('active'); $table->timestamps();
            $table->unique(['organization_id', 'employee_number'], 'employees_org_number_unique');
            $table->index(['organization_id', 'last_name']);
        });
        Schema::create('employee_changes', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30); $table->json('before')->nullable(); $table->json('after'); $table->timestamps();
            $table->index(['organization_id', 'employee_id']);
        });
        foreach ([['employees.view','View employee records'], ['employees.create','Create employee records'], ['employees.update','Update employee records'], ['employees.history.view','View employee change history']] as [$key, $label]) {
            DB::table('permissions')->insert(['key'=>$key, 'label'=>$label, 'scope'=>'organization', 'created_at'=>now(), 'updated_at'=>now()]);
        }
    }
    public function down(): void {
        DB::table('permissions')->whereIn('key', ['employees.view','employees.create','employees.update','employees.history.view'])->delete();
        Schema::dropIfExists('employee_changes'); Schema::dropIfExists('employees');
    }
};
