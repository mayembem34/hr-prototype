<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('title'); $table->string('category', 100); $table->string('original_name');
            $table->string('mime_type', 100); $table->string('storage_path')->unique(); $table->unsignedBigInteger('size');
            $table->timestamp('archived_at')->nullable(); $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(); $table->index(['organization_id', 'employee_id']);
        });
        Schema::create('employee_document_actions', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained('employee_documents')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40); $table->timestamps();
            $table->index(['organization_id', 'document_id']);
        });
        foreach ([['documents.view','View employee document lists'], ['documents.upload','Upload employee documents'], ['documents.preview','Preview employee documents'], ['documents.download','Download employee documents'], ['documents.archive','Archive employee documents'], ['documents.restore','Restore archived employee documents'], ['documents.audit.view','View document action history']] as [$key,$label]) {
            DB::table('permissions')->insert(['key'=>$key,'label'=>$label,'scope'=>'organization','created_at'=>now(),'updated_at'=>now()]);
        }
    }
    public function down(): void {
        DB::table('permissions')->whereIn('key',['documents.view','documents.upload','documents.preview','documents.download','documents.archive','documents.restore','documents.audit.view'])->delete();
        Schema::dropIfExists('employee_document_actions'); Schema::dropIfExists('employee_documents');
    }
};
