<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate legacy role string to 'admin'
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            DB::table('users')->where('role', 'administrator')->update(['role' => 'admin']);
        }

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('label', 128);
            $table->string('module', 64)->index();
            $table->string('action', 32);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->string('role', 32)->index();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role', 'permission_id']);
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['user_id', 'permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            DB::table('users')->where('role', 'admin')->update(['role' => 'administrator']);
        }
    }
};
