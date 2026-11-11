<?php

namespace App\Services;

use App\LegalPageType;
use App\Models\LegalPage;
use App\Models\SearchSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CookieConsent
{
    public const NAME = 'site_consent';

    public const DAYS = 180;

    /** @return array{statistics: bool, marketing: bool, preferences: bool, version: int, policy_revision: int, decided_at: int}|null */
    public function decision(Request $request, ?SearchSetting $settings = null): ?array
    {
        $cookie = $request->cookie(self::NAME);
        $data = is_string($cookie) ? json_decode($cookie, true) : null;
        $settings ??= SearchSetting::current();
        if (! is_array($data) || ! is_bool($data['statistics'] ?? null)
            || (isset($data['marketing']) && ! is_bool($data['marketing']))
            || (isset($data['preferences']) && ! is_bool($data['preferences']))
            || ($data['version'] ?? null) !== $settings->consent_version
            || ($data['policy_revision'] ?? null) !== $this->policyRevision()
            || ! is_int($data['decided_at'] ?? null) || $data['decided_at'] > now()->timestamp
            || $data['decided_at'] < now()->subDays(self::DAYS)->timestamp) {
            return null;
        }

        return [...$data, 'marketing' => $data['marketing'] ?? false, 'preferences' => $data['preferences'] ?? false];
    }

    public function allowsStatistics(Request $request, ?SearchSetting $settings = null): bool
    {
        $settings ??= SearchSetting::current();

        return $settings->cookies_enabled && $settings->analytics_enabled && $settings->validMeasurementId()
            && ($this->decision($request, $settings)['statistics'] ?? false);
    }

    public function policyRevision(): int
    {
        return Cache::memo()->remember(ApplicationCache::PRIVACY_REVISION, 300, fn (): int => LegalPage::query()->published()->where('type', LegalPageType::Privacy)->value('revision') ?? 0);
    }

    public function allowsPurpose(Request $request, string $purpose, ?SearchSetting $settings = null): bool
    {
        if ($purpose === 'necessary') {
            return true;
        }
        if (! in_array($purpose, ['statistics', 'marketing', 'preferences'], true)) {
            return false;
        }
        $settings ??= SearchSetting::current();

        return $settings->cookies_enabled && ($this->decision($request, $settings)[$purpose] ?? false);
    }

    /** @return array{statistics: bool, marketing: bool, preferences: bool, version: int, policy_revision: int, decided_at: int} */
    public function payload(bool $statistics, bool $marketing = false, bool $preferences = false): array
    {
        return ['statistics' => $statistics, 'marketing' => $marketing, 'preferences' => $preferences, 'version' => SearchSetting::current()->consent_version, 'policy_revision' => $this->policyRevision(), 'decided_at' => now()->timestamp];
    }
}
