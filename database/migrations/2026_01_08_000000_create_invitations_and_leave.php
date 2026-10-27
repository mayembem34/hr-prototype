<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users',fn (Blueprint $t) => $t->boolean('must_set_password')->default(false));
        Schema::create('account_invitations',function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->restrictOnDelete(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash',64)->unique(); $t->timestamp('expires_at'); $t->timestamp('used_at')->nullable(); $t->timestamps();
        });
        Schema::create('invitation_events',function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->restrictOnDelete(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); $t->string('action',30); $t->timestamps();
        });
        Schema::create('leave_types',function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->restrictOnDelete(); $t->string('name'); $t->unsignedInteger('annual_days'); $t->boolean('active')->default(true); $t->timestamps(); $t->unique(['organization_id','name']);
        });
        Schema::create('leave_requests',function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->restrictOnDelete(); $t->foreignId('employee_id')->constrained()->restrictOnDelete(); $t->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $t->date('start_date'); $t->date('end_date'); $t->unsignedInteger('days'); $t->string('status',20)->default('pending'); $t->text('reason')->nullable(); $t->text('decision_note')->nullable();
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('decided_at')->nullable(); $t->timestamps(); $t->index(['organization_id','employee_id','status']);
        });
        foreach ([['invitations.send','Send and resend account invitations','organization'],['invitations.events.view','View invitation history','organization'],['invitations.accept','Accept account invitation','public'],['leave.self.view','View your leave','session'],['leave.self.request','Request leave','session'],['leave.self.cancel','Cancel your pending leave','session'],['leave.types.view','View leave types','organization'],['leave.types.manage','Manage leave types','organization'],['leave.requests.view','View leave requests','organization']] as [$key,$label,$scope]) DB::table('permissions')->insert(['key'=>$key,'label'=>$label,'scope'=>$scope,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void {
        DB::table('permissions')->whereIn('key',['invitations.send','invitations.events.view','invitations.accept','leave.self.view','leave.self.request','leave.self.cancel','leave.types.view','leave.types.manage','leave.requests.view'])->delete();
        Schema::dropIfExists('leave_requests'); Schema::dropIfExists('leave_types'); Schema::dropIfExists('invitation_events'); Schema::dropIfExists('account_invitations');
        Schema::table('users',fn (Blueprint $t) => $t->dropColumn('must_set_password'));
    }
};
