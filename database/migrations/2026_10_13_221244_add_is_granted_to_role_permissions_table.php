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
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->boolean('is_granted')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('role_permissions')->where('is_granted', false)->exists()) {
            throw new RuntimeException('No se puede revertir esta migración mientras existan permisos denegados explícitamente: la versión anterior los convertiría en permisos concedidos.');
        }
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->dropColumn('is_granted');
        });
    }
};
