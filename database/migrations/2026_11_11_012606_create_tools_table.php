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
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline', 160);
            $table->foreignId('logo_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->foreignId('image_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->foreignId('tool_category_id')->constrained('tool_categories')->restrictOnDelete();
            $table->string('website_url', 2048);
            $table->string('affiliate_url', 2048)->nullable();

            $table->text('best_for')->nullable();
            $table->text('not_for')->nullable();
            $table->longText('review')->nullable();
            $table->json('pros')->nullable();
            $table->json('cons')->nullable();

            $table->string('pricing_model', 32)->index();
            $table->boolean('has_free_plan')->default(false)->index();
            $table->string('free_plan_notes')->nullable();
            $table->decimal('starting_price_usd', 8, 2)->nullable();
            $table->boolean('requires_card')->default(false);
            $table->string('spanish_support', 32)->index();
            $table->boolean('latam_payments')->nullable();
            $table->json('platforms')->nullable();
            $table->text('privacy_notes')->nullable();
            $table->string('deal_notes')->nullable();
            $table->date('deal_expires_at')->nullable();

            $table->date('tested_at')->nullable();
            $table->date('reviewed_at')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
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
        Schema::dropIfExists('tools');
    }
};
