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
        Schema::create('tool_tool_category', function (Blueprint $table) {
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->foreignId('tool_category_id')->constrained('tool_categories')->cascadeOnDelete();
            $table->primary(['tool_id', 'tool_category_id']);
            $table->index('tool_category_id');
        });

        Schema::create('tool_alternatives', function (Blueprint $table) {
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->foreignId('alternative_id')->constrained('tools')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['tool_id', 'alternative_id']);
            $table->index('alternative_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_alternatives');
        Schema::dropIfExists('tool_tool_category');
    }
};
