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
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->json('assistant_answers')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $query = DB::table('contact_messages')->whereNotNull('assistant_answers');
        if ($query->exists()) {
            throw new RuntimeException('No se pueden eliminar las respuestas históricas de las consultas.');
        }
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropColumn('assistant_answers');
        });
    }
};
