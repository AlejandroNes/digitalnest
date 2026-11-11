<?php

namespace App\Services;

use App\Models\CustomScript;
use App\Models\SearchSetting;
use App\Rules\ScriptMarkup;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class SiteIntegrations
{
    public function isPublic(Request $request): bool
    {
        return $request->isMethod('GET') && ! InternalUrl::protectedPath('/'.ltrim($request->path(), '/'))
            && ! $request->routeIs('filament.*', '*livewire.update');
    }

    public function nonce(Request $request): ?string
    {
        $nonce = $request->attributes->get('csp_nonce');

        return is_string($nonce) && preg_match('~^[A-Za-z0-9+/_=-]{1,128}$~D', $nonce) ? $nonce : null;
    }

    public function tagManagerAllowed(Request $request, SearchSetting $settings): bool
    {
        $consent = app(CookieConsent::class);

        return $settings->usesTagManager() && ($consent->allowsPurpose($request, 'statistics', $settings)
            || $consent->allowsPurpose($request, 'marketing', $settings));
    }

    public function customMarkup(Request $request, string $position, SearchSetting $settings): string
    {
        if (! $this->isPublic($request) || ! isset(CustomScript::POSITIONS[$position]) || ! SearchSetting::supportsIntegrations()) {
            return '';
        }
        $scripts = Cache::memo()->remember(ApplicationCache::SCRIPTS, 300, fn () => CustomScript::query()->active()
            ->select(['id', 'code', 'position', 'purpose'])->orderBy('id')->get()
            ->map(fn (CustomScript $script): array => $script->getAttributes())->all());
        $output = '';
        $used = $request->attributes->get('integration_script_sources', []);
        foreach ($scripts as $attributes) {
            $script = new CustomScript;
            $script->setRawAttributes($attributes, true);
            if ($script->position !== $position || ! app(CookieConsent::class)->allowsPurpose($request, $script->purpose, $settings)) {
                continue;
            }
            try {
                $code = $script->code;
            } catch (DecryptException) {
                continue;
            }
            if (Validator::make(['code' => $code], ['code' => new ScriptMarkup($script->purpose, $position)])->fails()) {
                continue;
            }
            $root = ScriptMarkup::parse($code);
            if (! $root) {
                continue;
            }
            foreach (iterator_to_array($root->getElementsByTagName('script')) as $element) {
                if ($element->hasAttribute('src')) {
                    $source = $element->getAttribute('src');
                    if (isset($used[$source])) {
                        $element->parentNode->removeChild($element);

                        continue;
                    }
                    $used[$source] = true;
                }
                if ($nonce = $this->nonce($request)) {
                    $element->setAttribute('nonce', $nonce);
                }
            }
            foreach ($root->childNodes as $node) {
                $output .= $root->ownerDocument->saveHTML($node);
            }
        }
        $request->attributes->set('integration_script_sources', $used);

        return $output;
    }
}
