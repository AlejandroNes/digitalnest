<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('default_title')->nullable();
            $table->text('default_description')->nullable();
            $table->foreignId('og_image_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->boolean('indexable')->default(false);
            $table->boolean('analytics_enabled')->default(false);
            $table->string('measurement_id', 32)->nullable();
            $table->string('verification_token')->nullable();
            $table->boolean('cookies_enabled')->default(true);
            $table->text('cookie_message')->nullable();
            $table->unsignedInteger('consent_version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_settings');
    }
};
