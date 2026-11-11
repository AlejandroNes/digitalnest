<?php

use App\Http\Controllers\ContactAssistantController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\ApplySiteSettings;
use Illuminate\Support\Facades\Route;

Route::middleware(ApplySiteSettings::class)->group(function (): void {
    Route::view('/', 'welcome');
    Route::get('/asistente-contacto', [ContactAssistantController::class, 'show'])->middleware('throttle:60,1')->name('contact-assistant.show');
    Route::post('/asistente-contacto', [ContactAssistantController::class, 'store'])->middleware('throttle:contact')->name('contact-assistant.store');
    Route::get('/contacto', [ContactMessageController::class, 'create'])->name('contact.create');
    Route::post('/contacto', [ContactMessageController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
    Route::get('/blog/{slug}', [PublicContentController::class, 'post'])->name('posts.show');
    Route::get('/servicios/{slug}', [PublicContentController::class, 'service'])->name('services.show');
    Route::get('/legal/{type}', [PublicContentController::class, 'legal'])->whereIn('type', ['terms', 'privacy'])->name('legal.show');
    Route::get('/proyectos', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/proyectos/{slug}', [ProjectController::class, 'show'])->name('projects.show');
});
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/sitemaps/{page}.xml', SitemapController::class)->whereNumber('page')->name('sitemap.part');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::post('/cookies/consent', CookieConsentController::class)->middleware('throttle:30,1')->name('cookies.store');
