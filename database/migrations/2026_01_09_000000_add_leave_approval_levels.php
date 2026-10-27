<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('leave_approving_officers', function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->restrictOnDelete(); $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('position'); $t->timestamps(); $t->unique(['organization_id','user_id']); $t->unique(['organization_id','position']);
        });
        Schema::create('leave_approval_steps', function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->restrictOnDelete(); $t->foreignId('leave_request_id')->constrained('leave_requests')->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $t->unsignedInteger('position'); $t->string('stage',30); $t->string('status',20)->default('pending'); $t->timestamps();
            $t->unique(['leave_request_id','position']); $t->index(['organization_id','user_id','status']);
        });
        Schema::create('leave_approval_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->restrictOnDelete(); $t->foreignId('leave_request_id')->constrained('leave_requests')->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); $t->string('stage',30); $t->string('decision',20); $t->text('note')->nullable(); $t->timestamps();
            $t->index(['organization_id','leave_request_id']);
        });
        foreach ([['leave.requests.view_all','View all leave applications and approval history'],['leave.requests.supervisor_approve','Approve leave as immediate supervisor'],['leave.requests.officer_approve','Approve leave as an assigned approving officer'],['leave.approvers.view','View leave approving officers'],['leave.approvers.manage','Configure leave approving officers']] as [$key,$label]) DB::table('permissions')->insert(['key'=>$key,'label'=>$label,'scope'=>'organization','created_at'=>now(),'updated_at'=>now()]);
        foreach (DB::table('leave_requests')->where('status','pending')->get() as $leave) {
            $employee=DB::table('employees')->where('organization_id',$leave->organization_id)->where('id',$leave->employee_id)->first();
            $manager=$employee && $employee->manager_id ? DB::table('employees')->where('organization_id',$leave->organization_id)->where('id',$employee->manager_id)->first() : null;
            DB::table('leave_approval_steps')->insert(['organization_id'=>$leave->organization_id,'leave_request_id'=>$leave->id,'user_id'=>$manager?->user_id,'position'=>0,'stage'=>'supervisor','status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
            DB::table('leave_requests')->where('id',$leave->id)->update(['status'=>'pending_supervisor']);
        }
        $ids=DB::table('permissions')->whereIn('key',['leave.requests.view_all','leave.requests.officer_approve'])->pluck('id');
        foreach (DB::table('roles')->where('name','HR Manager')->pluck('id') as $roleId) foreach ($ids as $id)
            if (! DB::table('permission_role')->where('role_id',$roleId)->where('permission_id',$id)->exists()) DB::table('permission_role')->insert(['role_id'=>$roleId,'permission_id'=>$id,'access_scope'=>'organization']);
    }
    public function down(): void {
        DB::table('leave_requests')->whereIn('status',['pending_supervisor','pending_officer'])->update(['status'=>'pending']);
        DB::table('permissions')->whereIn('key',['leave.requests.view_all','leave.requests.supervisor_approve','leave.requests.officer_approve','leave.approvers.view','leave.approvers.manage'])->delete();
        Schema::dropIfExists('leave_approval_events'); Schema::dropIfExists('leave_approval_steps'); Schema::dropIfExists('leave_approving_officers');
    }
};
