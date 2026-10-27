<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('vacancies',function(Blueprint $t) { $t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->string('title');$t->text('description')->nullable();$t->string('status',20)->default('open');$t->timestamps(); });
        Schema::create('job_applications',function(Blueprint $t) { $t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('vacancy_id')->constrained()->restrictOnDelete();$t->string('candidate_name');$t->string('email')->nullable();$t->string('stage',20)->default('applied');$t->text('notes')->nullable();$t->timestamps(); });
        Schema::create('lifecycle_cases',function(Blueprint $t) { $t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('employee_id')->constrained()->restrictOnDelete();$t->string('kind',20);$t->string('status',20)->default('open');$t->date('start_date')->nullable();$t->date('due_date')->nullable();$t->string('outcome',30)->nullable();$t->text('notes')->nullable();$t->timestamps();$t->index(['organization_id','employee_id','kind']); });
        Schema::create('lifecycle_tasks',function(Blueprint $t) { $t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('lifecycle_case_id')->constrained()->restrictOnDelete();$t->string('title');$t->boolean('completed')->default(false);$t->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('completed_at')->nullable();$t->timestamps(); });
        Schema::create('lifecycle_events',function(Blueprint $t) { $t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();$t->string('entity_type',30);$t->unsignedBigInteger('entity_id');$t->string('action',40);$t->text('details')->nullable();$t->timestamps();$t->index(['organization_id','entity_type','entity_id']); });
        foreach ([['recruitment.vacancies.view','View vacancies'],['recruitment.vacancies.create','Create vacancies'],['recruitment.vacancies.update','Update vacancies'],['recruitment.applications.view','View job applications'],['recruitment.applications.create','Create job applications'],['recruitment.applications.update','Update job applications'],['lifecycle.cases.view','View employee lifecycle cases'],['lifecycle.cases.create','Create employee lifecycle cases'],['lifecycle.cases.update','Update and complete employee lifecycle cases'],['lifecycle.tasks.create','Add lifecycle checklist tasks'],['lifecycle.tasks.update','Complete lifecycle checklist tasks'],['lifecycle.events.view','View lifecycle history']] as [$key,$label]) DB::table('permissions')->insert(['key'=>$key,'label'=>$label,'scope'=>'organization','created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void {
        DB::table('permissions')->whereIn('key',['recruitment.vacancies.view','recruitment.vacancies.create','recruitment.vacancies.update','recruitment.applications.view','recruitment.applications.create','recruitment.applications.update','lifecycle.cases.view','lifecycle.cases.create','lifecycle.cases.update','lifecycle.tasks.create','lifecycle.tasks.update','lifecycle.events.view'])->delete();
        Schema::dropIfExists('lifecycle_events');Schema::dropIfExists('lifecycle_tasks');Schema::dropIfExists('lifecycle_cases');Schema::dropIfExists('job_applications');Schema::dropIfExists('vacancies');
    }
};
