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
        Schema::table('contact_assistant_settings', function (Blueprint $table) {
            $table->json('field_schema')->nullable();
            $table->foreignId('avatar_media_id')->nullable()->constrained('media')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $query = DB::table('contact_assistant_settings')->whereNotNull('field_schema');
        $query->orWhereNotNull('avatar_media_id');
        if ($query->exists()) {
            throw new RuntimeException('No se puede retirar la configuración mientras haya preguntas o una foto guardadas.');
        }
        Schema::table('contact_assistant_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avatar_media_id');
            $table->dropColumn('field_schema');
        });
    }
};
