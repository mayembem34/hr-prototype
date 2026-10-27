<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('attendance_entries',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('employee_id')->constrained()->restrictOnDelete();$t->dateTime('clock_in');$t->dateTime('clock_out')->nullable();$t->timestamps();$t->index(['organization_id','employee_id','clock_in']);});
  Schema::create('attendance_events',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('attendance_entry_id')->constrained('attendance_entries')->restrictOnDelete();$t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();$t->string('action',20);$t->text('before_values')->nullable();$t->text('after_values')->nullable();$t->string('reason')->nullable();$t->timestamps();});
  Schema::create('daily_notifications',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('message');$t->timestamp('read_at')->nullable();$t->timestamps();$t->index(['organization_id','user_id','read_at']);});
  foreach([['attendance.self.view','View own attendance','session'],['attendance.self.clock_in','Clock in','session'],['attendance.self.clock_out','Clock out','session'],['attendance.records.view','View employee attendance','organization'],['attendance.records.correct','Correct employee attendance','organization'],['attendance.history.view','View attendance correction history','organization'],['daily.notifications.view','View own daily HR notifications','session'],['daily.notifications.read','Mark daily HR notifications read','session']] as [$key,$label,$scope]) DB::table('permissions')->insert(['key'=>$key,'label'=>$label,'scope'=>$scope,'created_at'=>now(),'updated_at'=>now()]);
 }
 public function down(): void { DB::table('permissions')->whereIn('key',['attendance.self.view','attendance.self.clock_in','attendance.self.clock_out','attendance.records.view','attendance.records.correct','attendance.history.view','daily.notifications.view','daily.notifications.read'])->delete();Schema::dropIfExists('daily_notifications');Schema::dropIfExists('attendance_events');Schema::dropIfExists('attendance_entries'); }
};
