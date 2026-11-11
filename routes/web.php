<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactAssistantController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\WorkflowController;
use App\Http\Middleware\ApplySiteSettings;
use Illuminate\Support\Facades\Route;

Route::middleware(ApplySiteSettings::class)->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/asistente-contacto', [ContactAssistantController::class, 'show'])->middleware('throttle:60,1')->name('contact-assistant.show');
    Route::post('/asistente-contacto', [ContactAssistantController::class, 'store'])->middleware('throttle:contact')->name('contact-assistant.store');
    Route::get('/contacto', [ContactMessageController::class, 'create'])->name('contact.create');
    Route::post('/contacto', [ContactMessageController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
    Route::get('/blog', [BlogController::class, 'index'])->name('posts.index');
    Route::get('/blog/categoria/{slug}', [BlogController::class, 'category'])->name('posts.category');
    Route::get('/blog/{slug}', [PublicContentController::class, 'post'])->name('posts.show');
    Route::get('/servicios/{slug}', [PublicContentController::class, 'service'])->name('services.show');
    Route::get('/legal/{type}', [PublicContentController::class, 'legal'])->whereIn('type', ['terms', 'privacy'])->name('legal.show');
    Route::get('/proyectos', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/proyectos/{slug}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/herramientas', [ToolController::class, 'index'])->name('tools.index');
    Route::get('/herramientas/categoria/{slug}', [ToolController::class, 'category'])->name('tools.category');
    Route::get('/herramientas/{slug}', [ToolController::class, 'show'])->name('tools.show');
    Route::get('/herramientas/{slug}/alternativas', [ToolController::class, 'alternatives'])->name('tools.alternatives');
    Route::get('/flujos', [WorkflowController::class, 'index'])->name('workflows.index');
    Route::get('/flujos/{slug}', [WorkflowController::class, 'show'])->name('workflows.show');
    Route::get('/plantillas', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('/plantillas/{slug}', [TemplateController::class, 'show'])->name('templates.show');
    Route::get('/plantillas/{slug}/descargar', [TemplateController::class, 'download'])->middleware('throttle:60,1')->name('templates.download');
});
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/sitemaps/{page}.xml', SitemapController::class)->whereNumber('page')->name('sitemap.part');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::post('/cookies/consent', CookieConsentController::class)->middleware('throttle:30,1')->name('cookies.store');
