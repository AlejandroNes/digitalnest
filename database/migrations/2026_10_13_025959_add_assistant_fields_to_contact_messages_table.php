<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('source', 32)->default('contact_form');
            $table->index(['source', 'created_at']);
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->text('consent_text')->nullable();
            $table->string('privacy_path')->nullable();
            $table->unsignedInteger('privacy_revision')->nullable();
            $table->string('whatsapp_status', 32)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('contact_messages')->whereNull('email')->exists()) {
            throw new RuntimeException('No se puede restaurar email obligatorio mientras existan consultas sin correo. Conserva los datos y aplica una migración correctiva.');
        }
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_id');
            $table->dropIndex(['source', 'created_at']);
            $table->dropColumn(['source', 'service_name', 'consented_at', 'consent_text', 'privacy_path', 'privacy_revision', 'whatsapp_status']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
