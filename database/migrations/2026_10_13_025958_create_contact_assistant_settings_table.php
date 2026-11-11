<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_assistant_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('name', 100);
            $table->text('welcome_message');
            $table->text('form_intro');
            $table->string('destination_whatsapp', 16)->nullable();
            $table->text('final_message');
            $table->string('button_label', 100);
            $table->boolean('email_required')->default(false);
            $table->boolean('services_enabled')->default(true);
            $table->text('consent_text');
            $table->string('privacy_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_assistant_settings');
    }
};
