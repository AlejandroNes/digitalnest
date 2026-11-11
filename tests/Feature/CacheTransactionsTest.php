<?php

use App\Models\GeneralSetting;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Services\ApplicationCache;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, DatabaseMigrations::class);

it('discards values cached before commit or from rolled back changes', function (): void {
    $settings = GeneralSetting::factory()->create(['id' => 1, 'company_name' => 'Inicial']);
    DB::beginTransaction();
    $settings->update(['company_name' => 'Confirmada']);
    Cache::memo()->put(ApplicationCache::GENERAL, ['company_name' => 'Valor anterior'], 300);
    DB::commit();
    expect(GeneralSetting::current(false)->company_name)->toBe('Confirmada');
    DB::beginTransaction();
    $settings->update(['company_name' => 'No confirmada']);
    expect(GeneralSetting::current(false)->company_name)->toBe('No confirmada');
    DB::rollBack();
    expect(GeneralSetting::current(false)->company_name)->toBe('Confirmada');
});

it('invalidates shared logo references after commit and rollback', function (): void {
    Storage::fake('public');
    $first = MediaAsset::factory()->create()->addMedia(UploadedFile::fake()->image('first.png'))->toMediaCollection('images');
    $second = MediaAsset::factory()->create()->addMedia(UploadedFile::fake()->image('second.png'))->toMediaCollection('images');
    $settings = GeneralSetting::factory()->create(['id' => 1, 'logo_media_id' => $first->id]);
    expect(GeneralSetting::current(false)->imageUrl('logo'))->toBe($first->getUrl());
    DB::beginTransaction();
    $settings->update(['logo_media_id' => $second->id]);
    expect(GeneralSetting::current(false)->imageUrl('logo'))->toBe($second->getUrl());
    DB::rollBack();
    expect(GeneralSetting::current(false)->imageUrl('logo'))->toBe($first->getUrl());
    DB::beginTransaction();
    $settings->refresh()->update(['logo_media_id' => $second->id]);
    DB::commit();
    expect(GeneralSetting::current(false)->imageUrl('logo'))->toBe($second->getUrl());
});

it('discards featured lists populated inside committed or rolled back transactions', function (): void {
    $first = Project::factory()->published()->featured()->create(['sort_order' => 1]);
    expect(Project::featuredPublished(7)->modelKeys())->toBe([$first->id]);
    DB::beginTransaction();
    $second = Project::factory()->published()->featured()->create(['sort_order' => 0]);
    expect(Project::featuredPublished(7)->modelKeys())->toBe([$second->id, $first->id]);

    DB::rollBack();

    expect(Project::featuredPublished(7)->modelKeys())->toBe([$first->id]);
    DB::beginTransaction();
    $first->update(['status' => 'draft']);
    expect(Project::featuredPublished(7))->toBeEmpty();
    DB::commit();
    expect(Project::featuredPublished(7))->toBeEmpty();
});
