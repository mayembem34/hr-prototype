<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('overtime_requests',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('employee_id')->constrained()->restrictOnDelete();$t->dateTime('starts_at');$t->dateTime('ends_at');$t->unsignedInteger('minutes');$t->text('reason');$t->string('status',20)->default('pending');$t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();$t->text('decision_note')->nullable();$t->timestamp('decided_at')->nullable();$t->timestamps();$t->index(['organization_id','employee_id','status'],'overtime_org_emp_status_idx');});
  Schema::create('overtime_events',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('overtime_request_id')->constrained('overtime_requests')->restrictOnDelete();$t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();$t->string('action',20);$t->text('note')->nullable();$t->timestamps();});
  foreach([['overtime.self.view','View own overtime requests','session'],['overtime.self.create','Submit own overtime requests','session'],['overtime.self.cancel','Cancel own pending overtime request','session'],['overtime.requests.view','View employee overtime requests','organization'],['overtime.requests.decide','Approve or reject employee overtime','organization']] as [$key,$label,$scope])DB::table('permissions')->insert(['key'=>$key,'label'=>$label,'scope'=>$scope,'created_at'=>now(),'updated_at'=>now()]);
 }
 public function down(): void {DB::table('permissions')->whereIn('key',['overtime.self.view','overtime.self.create','overtime.self.cancel','overtime.requests.view','overtime.requests.decide'])->delete();Schema::dropIfExists('overtime_events');Schema::dropIfExists('overtime_requests');}
};
