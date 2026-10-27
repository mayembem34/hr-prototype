<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('organization_user',fn(Blueprint $t)=>$t->boolean('is_active')->default(true));
  Schema::table('lifecycle_tasks',fn(Blueprint $t)=>$t->string('assignee',20)->default('staff'));
  Schema::table('job_applications',fn(Blueprint $t)=>$t->foreignId('hired_user_id')->nullable()->constrained('users')->nullOnDelete());
  Schema::create('lifecycle_notifications',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('message');$t->timestamp('read_at')->nullable();$t->timestamps();$t->index(['organization_id','user_id','read_at']);});
  Schema::create('recruitment_interviews',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('application_id')->constrained('job_applications')->restrictOnDelete();$t->dateTime('scheduled_at');$t->string('location')->nullable();$t->string('status',20)->default('scheduled');$t->text('notes')->nullable();$t->timestamps();});
  Schema::create('recruitment_offers',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('application_id')->constrained('job_applications')->restrictOnDelete();$t->date('offer_date');$t->string('status',20)->default('draft');$t->text('terms')->nullable();$t->timestamps();});
  foreach([['recruitment.interviews.view','View interviews','organization'],['recruitment.interviews.manage','Schedule and update interviews','organization'],['recruitment.offers.view','View offers','organization'],['recruitment.offers.manage','Create and update offers','organization'],['recruitment.hire','Hire a candidate and create their account','organization'],['members.reactivate','Reactivate an organization membership','privileged'],['lifecycle.offboarding.finalize','Finalize offboarding and remove organization access','organization'],['lifecycle.self.view','View own lifecycle checklist','session'],['lifecycle.self.update','Complete own assigned checklist tasks','session'],['lifecycle.self.notifications.view','View own lifecycle notifications','session'],['lifecycle.self.notifications.update','Mark own lifecycle notifications read','session']] as [$key,$label,$scope])DB::table('permissions')->insert(['key'=>$key,'label'=>$label,'scope'=>$scope,'created_at'=>now(),'updated_at'=>now()]);
 }
 public function down(): void {
  DB::table('permissions')->whereIn('key',['recruitment.interviews.view','recruitment.interviews.manage','recruitment.offers.view','recruitment.offers.manage','recruitment.hire','members.reactivate','lifecycle.offboarding.finalize','lifecycle.self.view','lifecycle.self.update','lifecycle.self.notifications.view','lifecycle.self.notifications.update'])->delete();
  Schema::dropIfExists('lifecycle_notifications');Schema::dropIfExists('recruitment_offers');Schema::dropIfExists('recruitment_interviews');
  Schema::table('job_applications',fn(Blueprint $t)=>$t->dropConstrainedForeignId('hired_user_id'));
  Schema::table('lifecycle_tasks',fn(Blueprint $t)=>$t->dropColumn('assignee'));
  Schema::table('organization_user',fn(Blueprint $t)=>$t->dropColumn('is_active'));
 }
};
