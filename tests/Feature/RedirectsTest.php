<?php

use App\Models\Post;
use App\Models\Redirect;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config(['app.url' => 'https://corporate.test']);
});

it('redirects unknown URLs with supported codes without leaking query parameters', function (int $code): void {
    Redirect::factory()->create(['source' => '/old', 'destination' => '/blog/new', 'status_code' => $code]);
    $this->get('/old?secret=test')->assertStatus($code)->assertRedirect('https://corporate.test/blog/new');
    $this->head('/old')->assertStatus($code);
    $this->post('/old')->assertNotFound();
})->with([301, 302]);

it('normalizes trailing slashes and rejects duplicate sources', function (): void {
    Redirect::factory()->create(['source' => '/old/', 'destination' => '/new/']);
    $this->get('/old/')->assertRedirect('https://corporate.test/new');
    expect(fn () => Redirect::factory()->create(['source' => '/old']))->toThrow(QueryException::class);
});

it('rejects unsafe internal and external destinations', function (string $path): void {
    expect(fn () => Redirect::factory()->create(['destination' => $path]))->toThrow(ValidationException::class);
})->with(['https://evil.test', '//evil.test', '/admin', '/admin/login', '/api/users', '/livewire/update', '/up', '/cookies/consent', '/build/app', '/storage/file', '/vendor', '/.env', '/foo/../bar', '/%2f%2fevil', '/safe?next=evil', '/safe#fragment', '/safe\\evil', '/safe\nHeader']);

it('rejects reserved existing and malformed source paths', function (string $path): void {
    expect(fn () => Redirect::factory()->create(['source' => $path]))->toThrow(ValidationException::class);
})->with(['/', '/admin/users', '/up', '/robots.txt', '/sitemap.xml', '/cookies/consent', '/build/asset', '/legal/privacy', 'old', '//evil.test']);

it('protects existing public content even if private and new content added after redirect creation', function (): void {
    $post = Post::factory()->create(['slug' => 'existing']);
    expect(fn () => Redirect::factory()->create(['source' => '/blog/existing']))->toThrow(ValidationException::class);
    Redirect::factory()->create(['source' => '/blog/new-content', 'destination' => '/']);
    $post = Post::factory()->published()->create(['slug' => 'new-content']);
    $this->get('/blog/'.$post->slug)->assertOk();
});

it('rejects self redirects and cycles on create update and activation', function (): void {
    expect(fn () => Redirect::factory()->create(['source' => '/self', 'destination' => '/self']))->toThrow(ValidationException::class);
    $a = Redirect::factory()->create(['source' => '/a', 'destination' => '/b']);
    $b = Redirect::factory()->create(['source' => '/b', 'destination' => '/c']);
    expect(fn () => $b->update(['destination' => '/a']))->toThrow(ValidationException::class);
    expect(fn () => Redirect::factory()->create(['source' => '/c', 'destination' => '/a']))->toThrow(ValidationException::class);
    $c = Redirect::factory()->create(['source' => '/c', 'destination' => '/a', 'is_active' => false]);
    expect(fn () => $c->update(['is_active' => true]))->toThrow(ValidationException::class);
    $this->get('/a')->assertRedirect('https://corporate.test/c');
});

it('fails safely for corrupt loops and unsafe rows bypassing model validation', function (): void {
    DB::table('redirects')->insert([
        ['source' => '/a', 'destination' => '/b', 'is_active' => true, 'status_code' => 301],
        ['source' => '/b', 'destination' => '/a', 'is_active' => true, 'status_code' => 301],
        ['source' => '/unsafe', 'destination' => '//evil.test', 'is_active' => true, 'status_code' => 301],
        ['source' => '/admin/login', 'destination' => '/', 'is_active' => true, 'status_code' => 301],
    ]);
    $this->get('/a')->assertNotFound();
    $this->get('/unsafe')->assertNotFound();
    $this->get('/admin/login')->assertOk();
});

it('ignores inactive redirects and rejects unsupported status codes', function (): void {
    Redirect::factory()->create(['source' => '/inactive', 'is_active' => false]);
    $this->get('/inactive')->assertNotFound();
    expect(fn () => Redirect::factory()->create(['status_code' => 307]))->toThrow(ValidationException::class);
});
