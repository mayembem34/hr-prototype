<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable(); $table->string('address', 500)->nullable();
            $table->string('emergency_contact_name')->nullable(); $table->string('emergency_contact_phone',80)->nullable();
        });
        foreach ([['profile.self.view','View your own employee profile','session'],['profile.self.update','Complete and update your own employee profile','session'],['members.accounts.create','Create user accounts','privileged']] as [$key,$label,$scope]) DB::table('permissions')->insert(['key'=>$key,'label'=>$label,'scope'=>$scope,'created_at'=>now(),'updated_at'=>now()]);
        foreach (\App\Models\Organization::all() as $org) app(\App\Services\DefaultHrRole::class)->createIfMissing($org);
    }
    public function down(): void {
        DB::table('permissions')->whereIn('key',['profile.self.view','profile.self.update','members.accounts.create'])->delete();
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn(['date_of_birth','address','emergency_contact_name','emergency_contact_phone']));
    }
};
