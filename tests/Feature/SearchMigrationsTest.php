<?php

use App\Models\Post;
use App\Models\SearchSetting;
use App\Models\Service;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('reverses phase three migrations without deleting earlier content', function (): void {
    $post = Post::factory()->create(['title' => 'Contenido anterior']);
    $service = Service::factory()->create(['name' => 'Servicio anterior']);
    $paths = [
        '2026_10_09_170524_create_search_settings_table.php',
        '2026_10_09_170525_create_redirects_table.php',
        '2026_10_09_170526_add_seo_fields_to_posts_and_services.php',
    ];
    $migrations = array_map(fn (string $path) => require database_path('migrations/'.$path), $paths);
    $reversed = [];
    try {
        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
            $reversed[] = $migration;
        }
        expect(Schema::hasTable('search_settings'))->toBeFalse();
        expect(Schema::hasTable('redirects'))->toBeFalse();
        expect(Schema::hasColumn('posts', 'seo_title'))->toBeFalse();
        expect(Schema::hasColumn('services', 'seo_image_id'))->toBeFalse();
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'Contenido anterior']);
        $this->assertDatabaseHas('services', ['id' => $service->id, 'name' => 'Servicio anterior']);
    } finally {
        foreach (array_reverse($reversed) as $migration) {
            $migration->up();
        }
    }
    expect(Schema::hasColumn('posts', 'seo_title'))->toBeTrue();
    expect(Schema::hasTable('search_settings'))->toBeTrue();
});

it('preserves existing verification and content when the integration migrations are reversed and reapplied', function (): void {
    $post = Post::factory()->create(['title' => 'Contenido conservado']);
    $settings = SearchSetting::factory()->create(['id' => 1, 'verification_token' => 'existing-token', 'indexable' => true]);
    $settingsMigration = require database_path('migrations/2026_10_14_035507_add_integrations_to_search_settings_table.php');
    $scriptsMigration = require database_path('migrations/2026_10_14_035508_create_custom_scripts_table.php');
    try {
        $scriptsMigration->down();
        $settingsMigration->down();
        expect(Schema::hasTable('custom_scripts'))->toBeFalse();
        expect(Schema::hasColumn('search_settings', 'sitemap_enabled'))->toBeFalse();
        $this->assertDatabaseHas('search_settings', ['id' => $settings->id, 'verification_token' => 'existing-token', 'indexable' => true]);
    } finally {
        if (! Schema::hasColumn('search_settings', 'sitemap_enabled')) {
            $settingsMigration->up();
        }
        if (! Schema::hasTable('custom_scripts')) {
            $scriptsMigration->up();
        }
    }
    $this->assertDatabaseHas('search_settings', ['id' => 1, 'verification_enabled' => true, 'tag_manager_enabled' => false, 'sitemap_enabled' => true]);
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'Contenido conservado']);
});
