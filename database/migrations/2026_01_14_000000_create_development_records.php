<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('development_records',function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->restrictOnDelete(); $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->string('area',30); $t->string('title'); $t->text('details')->nullable(); $t->string('status',30); $t->date('start_date')->nullable(); $t->date('due_date')->nullable(); $t->date('completed_date')->nullable(); $t->string('provider')->nullable(); $t->string('reference')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps(); $t->index(['organization_id','area','employee_id']);
        });
        foreach (['performance'=>'performance reviews','training'=>'training','qualifications'=>'qualifications','career'=>'career development'] as $area=>$label) foreach (['view'=>'View','create'=>'Create','update'=>'Update'] as $operation=>$verb) DB::table('permissions')->insert(['key'=>"development.$area.$operation",'label'=>"$verb $label",'scope'=>'organization','created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void {
        $keys=[]; foreach (['performance','training','qualifications','career'] as $area) foreach (['view','create','update'] as $operation) $keys[]="development.$area.$operation";
        DB::table('permissions')->whereIn('key',$keys)->delete(); Schema::dropIfExists('development_records');
    }
};
