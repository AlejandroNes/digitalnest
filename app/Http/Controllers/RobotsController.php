<?php

namespace App\Http\Controllers;

use App\Models\SearchSetting;
use App\Services\InternalUrl;
use App\Services\SiteIndexing;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $settings = SearchSetting::current();
        $text = "User-agent: *\n";
        if (! SiteIndexing::allows($settings)) {
            $text .= "Disallow: /\n";
        } else {
            foreach (['admin', 'api', 'livewire', 'cookies', 'up'] as $path) {
                $text .= "Disallow: /{$path}\n";
            }
            if ($settings->sitemap_enabled !== false) {
                $text .= 'Sitemap: '.InternalUrl::absolute('/sitemap.xml')."\n";
            }
        }

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
