<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->string('type', 16)->index();
            $table->string('title', 160);
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('code', 64)->nullable();
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->decimal('original_price', 10, 2)->nullable();
            $table->decimal('deal_price', 10, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->string('url', 2048)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_exclusive')->default(false);
            $table->boolean('is_featured')->default(false)->index();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at', 'expires_at']);
            $table->index(['tool_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
