<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('notifications_enabled')->nullable();
            $table->json('recipients')->nullable();
            $table->string('from_address')->nullable();
            $table->string('from_name', 150)->nullable();
            $table->boolean('use_panel_smtp')->default(false);
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_scheme', 10)->nullable();
            $table->text('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
