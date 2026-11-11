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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('content')->nullable();
            $table->foreignId('image_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->foreignId('project_category_id')->nullable()->constrained('project_categories')->restrictOnDelete();
            $table->string('client')->nullable();
            $table->string('location')->nullable();
            $table->date('completed_at')->nullable();
            $table->string('external_url', 2048)->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();

            // SEO metadata
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->foreignId('seo_image_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->string('seo_canonical')->nullable();
            $table->boolean('seo_noindex')->default(false)->index();

            $table->timestamps();
            $table->index(['status', 'is_featured', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
