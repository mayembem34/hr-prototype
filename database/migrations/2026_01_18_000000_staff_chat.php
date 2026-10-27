<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('staff_chat_messages',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('sender_id')->constrained('users')->restrictOnDelete();$t->foreignId('recipient_id')->nullable()->constrained('users')->restrictOnDelete();$t->text('body');$t->timestamps();$t->index(['organization_id','recipient_id','id'],'chat_org_recipient_idx');$t->index(['organization_id','sender_id','recipient_id'],'chat_participants_idx');});
  foreach([['chat.general.view','View organization staff chat'],['chat.general.send','Send organization staff chat messages'],['chat.direct.view','View direct staff messages'],['chat.direct.send','Send direct staff messages']] as [$key,$label]) DB::table('permissions')->insert(['key'=>$key,'label'=>$label,'scope'=>'organization','created_at'=>now(),'updated_at'=>now()]);
 }
 public function down(): void {DB::table('permissions')->whereIn('key',['chat.general.view','chat.general.send','chat.direct.view','chat.direct.send'])->delete();Schema::dropIfExists('staff_chat_messages');}
};
