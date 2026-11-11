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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 200);
            $table->string('type', 32)->index();
            $table->string('format', 16)->default('text');
            $table->longText('content');
            $table->longText('usage')->nullable();
            $table->longText('example_output')->nullable();
            $table->json('variables')->nullable();

            $table->date('tested_at')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);

            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->foreignId('seo_image_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->string('seo_canonical')->nullable();
            $table->boolean('seo_noindex')->default(false)->index();

            $table->timestamps();
            $table->index(['status', 'published_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
