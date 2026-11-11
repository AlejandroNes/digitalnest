<?php

use App\Services\ApplicationCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table): void {
            foreach (['logo_media_id', 'logo_dark_media_id', 'favicon_media_id'] as $column) {
                $table->foreignId($column)->nullable()->constrained('media')->restrictOnDelete();
            }
        });
        ApplicationCache::forget(ApplicationCache::GENERAL);
    }

    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table): void {
            foreach (['logo_media_id', 'logo_dark_media_id', 'favicon_media_id'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
        });
        ApplicationCache::forget(ApplicationCache::GENERAL);
    }
};
