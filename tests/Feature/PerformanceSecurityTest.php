<?php

use App\Models\Post;
use App\Models\SearchSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('sends compatible security headers on public panel and error responses', function (string $path, int $status): void {
    $response = $this->get($path)->assertStatus($status)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    expect($response->headers->get('Cache-Control'))->toContain('no-store', 'private');
    expect($response->headers->has('Content-Security-Policy'))->toBeFalse();
    expect($response->headers->has('X-XSS-Protection'))->toBeFalse();
    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
})->with([['/contacto', 200], ['/admin/login', 200], ['/missing-page', 404]]);

it('enables HSTS only for secure production responses', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    $this->get('https://localhost/up')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    expect($this->get('http://localhost/up')->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('renders safe customizable forbidden and missing pages', function (): void {
    config(['app.debug' => false]);
    $this->actingAs(User::factory()->create())->get('/admin/mail-settings')
        ->assertForbidden()->assertSee('Acceso denegado')->assertDontSee('Stack trace');
    $this->get('/missing-page')->assertNotFound()->assertSee('Página no encontrada')->assertSee('noindex, nofollow', false);
});

it('renders a database-independent 500 and removes SQL bindings from exception reports', function (): void {
    config(['app.debug' => false]);
    Log::shouldReceive('error')->once()->with('database.operation_failed', ['exception' => QueryException::class]);
    Route::get('/test-database-error', function (): never {
        throw new QueryException('mysql', 'insert into contact_messages (message) values (?)', ['private-message@example.test'], new RuntimeException('private-password'));
    });
    DB::enableQueryLog();
    $this->get('/test-database-error')->assertStatus(500)->assertSee('Error interno')
        ->assertDontSee('private-message')->assertDontSee('private-password')->assertDontSee('contact_messages')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
});

it('keeps SMTP diagnostics and secrets out of error responses and logs', function (): void {
    config(['app.debug' => false]);
    Log::shouldReceive('error')->once()->with('mail.transport_failed', ['exception' => TransportException::class]);
    Route::get('/test-mail-error', fn () => throw new TransportException('private-smtp-password recipient@example.test'));
    $this->get('/test-mail-error')->assertStatus(500)->assertSee('Error interno')
        ->assertDontSee('private-smtp-password')->assertDontSee('recipient@example.test');
});

it('does not load unused categories or content columns for sitemap generation', function (): void {
    SearchSetting::factory()->create(['id' => 1, 'indexable' => true]);
    $post = Post::factory()->published()->create();
    app()->instance('env', 'production');
    config(['app.url' => 'https://corporate.example.com']);
    DB::enableQueryLog();
    $this->get('/blog/'.$post->slug)->assertOk();
    expect(collect(DB::getQueryLog())->contains(fn (array $query): bool => str_contains($query['query'], 'post_categories')))->toBeFalse();
    DB::flushQueryLog();
    $this->get('/sitemap.xml')->assertOk()->assertSee('/blog/'.$post->slug);
    $postQuery = collect(DB::getQueryLog())->first(fn (array $query): bool => str_contains($query['query'], 'from "posts"'));
    DB::disableQueryLog();
    expect($postQuery['query'])->not->toContain('select *', '"content"');
});
