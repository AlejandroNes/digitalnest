<?php

use App\Http\Controllers\AppProfileController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactAssistantController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SavedController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\ApplySiteSettings;
use App\Http\Middleware\EnsureCorporateModulesEnabled;
use Illuminate\Support\Facades\Route;

Route::middleware(ApplySiteSettings::class)->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/explorar', [SectorController::class, 'index'])->name('explore');
    Route::get('/sector/{slug}', [SectorController::class, 'show'])->name('sectors.show');
    Route::get('/app/{slug}', [AppProfileController::class, 'show'])->name('apps.show');
    Route::get('/ofertas', [DealController::class, 'index'])->name('deals.index');
    Route::get('/oferta/{slug}', [DealController::class, 'show'])->name('deals.show');
    Route::get('/top', [RankingController::class, 'index'])->name('rankings.index');
    Route::get('/top/{slug}', [RankingController::class, 'show'])->name('rankings.show');
    Route::get('/guardados', [SavedController::class, 'index'])->name('saved.index');
    Route::get('/guardados/tarjetas', [SavedController::class, 'cards'])->middleware('throttle:60,1')->name('saved.cards');
    Route::view('/offline', 'nest.offline')->name('offline');
    Route::get('/go/{slug}', [DealController::class, 'go'])->middleware('throttle:60,1')->name('deals.go');
    Route::get('/asistente-contacto', [ContactAssistantController::class, 'show'])->middleware('throttle:60,1')->name('contact-assistant.show');
    Route::post('/asistente-contacto', [ContactAssistantController::class, 'store'])->middleware('throttle:contact')->name('contact-assistant.store');
    Route::get('/contacto', [ContactMessageController::class, 'create'])->name('contact.create');
    Route::post('/contacto', [ContactMessageController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
    Route::get('/buscar', SearchController::class)->middleware('throttle:60,1')->name('search');
    Route::view('/como-probamos', 'public.pages.method')->name('pages.method');
    Route::view('/afiliados', 'public.pages.affiliates')->name('pages.affiliates');
    Route::view('/sobre-digitalnest', 'public.pages.about')->name('pages.about');
    Route::get('/blog', [BlogController::class, 'index'])->name('posts.index');
    Route::get('/blog/categoria/{slug}', [BlogController::class, 'category'])->name('posts.category');
    Route::get('/blog/{slug}', [PublicContentController::class, 'post'])->name('posts.show');
    Route::get('/servicios/{slug}', [PublicContentController::class, 'service'])->middleware(EnsureCorporateModulesEnabled::class)->name('services.show');
    Route::get('/legal/{type}', [PublicContentController::class, 'legal'])->whereIn('type', ['terms', 'privacy'])->name('legal.show');
    Route::get('/proyectos', [ProjectController::class, 'index'])->middleware(EnsureCorporateModulesEnabled::class)->name('projects.index');
    Route::get('/proyectos/{slug}', [ProjectController::class, 'show'])->middleware(EnsureCorporateModulesEnabled::class)->name('projects.show');
});
/*
 * Permanent redirects from the previous directory site (tools, workflows and templates) to the deals app.
 * Workflows and templates stay in the panel but are no longer public.
 */
Route::permanentRedirect('/herramientas', '/explorar');
Route::get('/herramientas/categoria/{slug}', fn (string $slug) => redirect()->route('sectors.show', ['slug' => $slug], 301));
Route::get('/herramientas/{slug}/alternativas', fn (string $slug) => redirect()->route('apps.show', ['slug' => $slug], 301));
Route::get('/herramientas/{slug}', fn (string $slug) => redirect()->route('apps.show', ['slug' => $slug], 301));
Route::permanentRedirect('/flujos', '/explorar');
Route::permanentRedirect('/flujos/{slug}', '/explorar');
Route::permanentRedirect('/plantillas', '/explorar');
Route::permanentRedirect('/plantillas/{slug}', '/explorar');
Route::permanentRedirect('/plantillas/{slug}/descargar', '/explorar');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/sitemaps/{page}.xml', SitemapController::class)->whereNumber('page')->name('sitemap.part');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::post('/cookies/consent', CookieConsentController::class)->middleware('throttle:30,1')->name('cookies.store');

if (app()->environment('local', 'testing')) {
    Route::view('/_diseno', 'nest.design')->middleware(ApplySiteSettings::class)->name('design.preview');
}
