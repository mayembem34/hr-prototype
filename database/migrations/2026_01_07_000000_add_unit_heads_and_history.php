<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('units', function (Blueprint $table) { $table->foreignId('head_employee_id')->nullable()->constrained('employees')->restrictOnDelete(); });
        Schema::create('unit_changes', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); $table->string('action',32); $table->string('unit_name');
            $table->string('before_value')->nullable(); $table->string('after_value')->nullable(); $table->timestamps(); $table->index(['organization_id','unit_id']);
        });
        foreach ([['units.heads.view','View unit heads'], ['units.heads.assign','Assign unit heads'], ['units.history.view','View unit structure history']] as [$key,$label]) DB::table('permissions')->insert(['key'=>$key,'label'=>$label,'scope'=>'organization','created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void {
        DB::table('permissions')->whereIn('key',['units.heads.view','units.heads.assign','units.history.view'])->delete();
        Schema::dropIfExists('unit_changes');
        Schema::table('units', fn (Blueprint $table) => $table->dropConstrainedForeignId('head_employee_id'));
    }
};
