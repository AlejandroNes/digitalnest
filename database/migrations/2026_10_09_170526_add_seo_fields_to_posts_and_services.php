<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['posts', 'services'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->foreignId('seo_image_id')->nullable()->constrained('media')->restrictOnDelete();
                $table->string('seo_canonical')->nullable();
                $table->boolean('seo_noindex')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['posts', 'services'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropIndex(['seo_noindex']);
                $table->dropConstrainedForeignId('seo_image_id');
                $table->dropColumn(['seo_title', 'seo_description', 'seo_canonical', 'seo_noindex']);
            });
        }
    }
};
