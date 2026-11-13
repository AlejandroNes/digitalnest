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
        Schema::create('rankings', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('slug')->unique();
            $table->foreignId('tool_category_id')->nullable()->constrained('tool_categories')->nullOnDelete();
            $table->text('intro')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->boolean('seo_noindex')->default(false);
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        Schema::create('ranking_tool', function (Blueprint $table) {
            $table->foreignId('ranking_id')->constrained('rankings')->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('verdict', 160)->nullable();
            $table->string('best_for', 120)->nullable();
            $table->primary(['ranking_id', 'tool_id']);
            $table->index('tool_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ranking_tool');
        Schema::dropIfExists('rankings');
    }
};
