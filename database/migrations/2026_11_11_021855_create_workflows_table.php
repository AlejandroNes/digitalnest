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
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 200);
            $table->text('goal');
            $table->longText('intro')->nullable();
            $table->longText('result')->nullable();
            $table->foreignId('image_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->foreignId('tool_category_id')->constrained('tool_categories')->restrictOnDelete();
            $table->json('professions')->nullable();
            $table->string('difficulty', 16)->index();
            $table->unsignedSmallInteger('time_minutes');
            $table->decimal('cost_usd', 8, 2)->nullable();

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
            $table->index(['tool_category_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
