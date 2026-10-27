<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('organization_holidays',function(Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->date('date'); $t->string('name'); $t->timestamps(); $t->unique(['organization_id','date']);
        });
        foreach ([['leave.holidays.view','View organization holidays'],['leave.holidays.create','Add organization holidays'],['leave.holidays.update','Edit organization holidays'],['leave.holidays.delete','Delete organization holidays']] as [$key,$label]) DB::table('permissions')->insert(['key'=>$key,'label'=>$label,'scope'=>'organization','created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void {
        DB::table('permissions')->whereIn('key',['leave.holidays.view','leave.holidays.create','leave.holidays.update','leave.holidays.delete'])->delete();
        Schema::dropIfExists('organization_holidays');
    }
};
