<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) { $table->boolean('is_platform_admin')->default(false); });
        Schema::create('organizations', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('logo_path')->nullable();
            $table->string('primary_color', 7)->default('#2459a6'); $table->string('accent_color', 7)->default('#12856f'); $table->timestamps();
        });
        Schema::create('organization_user', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->boolean('is_admin')->default(false); $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });
        Schema::create('units', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->string('name'); $table->timestamps(); $table->index(['organization_id', 'parent_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('units'); Schema::dropIfExists('organization_user'); Schema::dropIfExists('organizations');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_platform_admin'));
    }
};
