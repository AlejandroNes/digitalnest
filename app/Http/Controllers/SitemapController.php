<?php

namespace App\Http\Controllers;

use App\Models\SearchSetting;
use App\Services\Sitemap;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(Sitemap $sitemap, ?string $page = null): Response
    {
        abort_if(SearchSetting::current()->sitemap_enabled === false, 404);
        abort_if($page !== null && (strlen($page) > 5 || ! ctype_digit($page)), 404);

        return response($sitemap->xml($page === null ? null : (int) $page), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'no-store',
        ]);
    }
}
