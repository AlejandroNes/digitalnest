<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_settings', function (Blueprint $table): void {
            $table->boolean('verification_enabled')->default(false);
            $table->boolean('tag_manager_enabled')->default(false);
            $table->string('tag_manager_id', 32)->nullable();
            $table->boolean('sitemap_enabled')->default(true);
        });
        DB::table('search_settings')->whereNotNull('verification_token')->where('verification_token', '<>', '')
            ->update(['verification_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('search_settings', function (Blueprint $table): void {
            $table->dropColumn(['verification_enabled', 'tag_manager_enabled', 'tag_manager_id', 'sitemap_enabled']);
        });
    }
};
