<?php

use App\ContactMessageStatus;
use App\Models\ContactAssistantSetting;
use App\Models\ContactMessage;
use App\Models\LegalPage;
use App\Models\Service;
use App\Services\ContactAssistant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

function assistantDocument(string $html): DOMXPath
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($document);
}

it('renders no widget or widget assets when disabled or WhatsApp is unavailable', function (array $settings): void {
    ContactAssistantSetting::factory()->create($settings);
    $this->get('/contacto')->assertOk()->assertDontSee('data-contact-assistant', false)->assertDontSee('resources/js/contact-assistant.js', false)
        ->assertDontSee('contact-assistant-', false);
})->with([
    [['enabled' => false]], [['enabled' => true, 'destination_whatsapp' => null]],
    [['enabled' => true, 'destination_whatsapp' => 'https://evil.test']],
]);

it('keeps public pages available before the assistant migration is applied', function (): void {
    $migration = require database_path('migrations/2026_10_13_025958_create_contact_assistant_settings_table.php');
    try {
        $migration->down();
        $this->get('/contacto')->assertOk()->assertDontSee('data-contact-assistant', false);
        $this->getJson('/asistente-contacto')->assertOk()->assertExactJson(['enabled' => false]);
        $this->postJson('/asistente-contacto', [])->assertNotFound();
    } finally {
        $migration->up();
    }
});

it('renders a reusable guided dialog with safe labels consent and active services', function (): void {
    $settings = ContactAssistantSetting::factory()->enabled()->create();
    $service = Service::factory()->create(['name' => 'Consultoría disponible']);
    Service::factory()->inactive()->create(['name' => 'Servicio oculto']);
    LegalPage::factory()->create(['type' => 'privacy', 'is_published' => true]);
    $response = $this->get('/contacto')->assertOk()->assertSee('data-contact-assistant', false)
        ->assertSee('Asistente virtual')->assertSee('Consultoría disponible')->assertDontSee('Servicio oculto')
        ->assertDontSee($settings->destination_whatsapp)->assertSee('confirma', false);
    $xpath = assistantDocument($response->getContent());
    $steps = [];
    foreach ($xpath->query('//form[@data-assistant-form]//*[@data-step]') as $step) {
        $steps[] = $step->getAttribute('data-step');
    }
    expect($steps)->toBe(['name', 'service_id', 'whatsapp', 'email', 'message', 'consent', 'summary']);
    expect($xpath->query('//dialog[@aria-labelledby="contact-assistant-title"]')->length)->toBe(1)
        ->and($xpath->query('//input[@name="consent"][@checked]')->length)->toBe(0)
        ->and($xpath->query('//input[@name="consent"][@required]')->length)->toBe(1)
        ->and($xpath->query('//form[@data-assistant-form]//input[@name="_token"]')->length)->toBe(1)
        ->and($xpath->query('//select[@name="service_id"]/option[@value="'.$service->id.'"]')->length)->toBe(1)
        ->and($xpath->query('//button[@data-service-value="'.$service->id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-assistant-history][@aria-label="Conversación"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-assistant-announcement][@aria-live="polite"]')->length)->toBe(1);
});

it('omits the service step and reflects configurable email requirements', function (): void {
    ContactAssistantSetting::factory()->enabled()->create(['services_enabled' => false, 'email_required' => true]);
    $xpath = assistantDocument($this->get('/contacto')->assertOk()->getContent());
    expect($xpath->query('//form[@data-assistant-form]//select[@name="service_id"]')->length)->toBe(0)
        ->and($xpath->query('//form[@data-assistant-form]//input[@name="email"][@required]')->length)->toBe(1);
});

it('renders only one widget per page and accepts caller theme attributes', function (): void {
    ContactAssistantSetting::factory()->enabled()->create();
    $html = Blade::render('<x-contact-assistant style="--contact-assistant-accent: #123456" /><x-contact-assistant />');
    $xpath = assistantDocument($html);
    expect($xpath->query('//*[@data-contact-assistant]')->length)->toBe(1);
    expect(rtrim($xpath->query('//*[@data-contact-assistant]')->item(0)->getAttribute('style'), ';'))->toContain('--contact-assistant-accent: #123456');
});

it('escapes configured text and service names rather than injecting HTML', function (): void {
    ContactAssistantSetting::factory()->enabled()->create(['name' => '<script>alert(1)</script>', 'welcome_message' => 'Hola <img src=x onerror=alert(2)>']);
    Service::factory()->create(['name' => '<svg onload=alert(3)>']);
    $this->get('/contacto')->assertOk()->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x onerror=alert(2)>', false)
        ->assertDontSee('<svg onload=alert(3)>', false);
});

it('integrates the component on home contact and content views without creating abandoned leads', function (): void {
    ContactAssistantSetting::factory()->enabled()->create();
    $service = Service::factory()->create();
    foreach (['/', '/contacto', '/servicios/'.$service->slug] as $path) {
        $this->get($path)->assertOk()->assertSee('data-contact-assistant', false)->assertSee('data-cookie-banner', false);
    }
    $this->assertDatabaseCount('contact_messages', 0);
});

it('submits the rendered form through Contacto and encodes readable WhatsApp text', function (): void {
    ContactAssistantSetting::factory()->enabled()->create();
    $service = Service::factory()->create(['name' => 'Diseño & desarrollo']);
    $html = $this->get('/contacto')->assertOk()->getContent();
    $xpath = assistantDocument($html);
    $token = $xpath->query('//form[@data-assistant-form]//input[@name="submission_token"]')->item(0)->getAttribute('value');
    $payload = ['name' => 'Ana Ejemplo', 'whatsapp' => '+34 (611) 123-456', 'email' => 'ana@example.test',
        'service_id' => $service->id, 'message' => 'Precio & plazo? Sí.', 'consent' => true, 'submission_token' => $token];

    $response = $this->postJson('/asistente-contacto', $payload)->assertAccepted()->assertJsonPath('whatsapp_status', 'link_prepared');

    parse_str(parse_url($response->json('whatsapp_url'), PHP_URL_QUERY), $query);
    expect(parse_url($response->json('whatsapp_url'), PHP_URL_HOST))->toBe('wa.me');
    expect($query['text'])->toBe("Hola, quiero solicitar información.\n\n*Nombre:* Ana Ejemplo\n*WhatsApp:* +34611123456\n*Correo:* ana@example.test\n*Servicio:* Diseño & desarrollo\n*Mensaje:* Precio & plazo? Sí.");
    $record = ContactMessage::query()->sole();
    expect($record->source)->toBe('contact_assistant')->and($record->status)->toBe(ContactMessageStatus::Pending)
        ->and($record->notified_at)->toBeNull()->and($record->service_id)->toBe($service->id);
    $this->postJson('/asistente-contacto', $payload)->assertAccepted()->assertJsonPath('whatsapp_url', $response->json('whatsapp_url'));
    $this->assertDatabaseCount('contact_messages', 1);
});

it('omits empty optional fields from the WhatsApp message', function (): void {
    ContactAssistantSetting::factory()->enabled()->create();
    $xpath = assistantDocument($this->get('/contacto')->assertOk()->getContent());
    $token = $xpath->query('//form[@data-assistant-form]//input[@name="submission_token"]')->item(0)->getAttribute('value');
    $response = $this->postJson('/asistente-contacto', ['name' => 'Ana Ejemplo', 'whatsapp' => '+34611123456', 'consent' => true, 'submission_token' => $token])->assertAccepted();
    parse_str(parse_url($response->json('whatsapp_url'), PHP_URL_QUERY), $query);
    expect($query['text'])->not->toContain('*Correo:*', '*Servicio:*', '*Mensaje:*');
});

it('executes client validation duplicate prevention CSRF retries and URL protection', function (): void {
    $process = new Process(['node', base_path('tests/Js/contact-assistant.mjs')]);
    $process->setTimeout(20);
    $process->mustRun();
    expect($process->getOutput())->toContain('scenarios passed');
});

it('renders a browser review fixture with synthetic data when explicitly requested', function (): void {
    $browserDatabase = getenv('CONTACT_ASSISTANT_BROWSER_DB');
    if ($browserDatabase) {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame($browserDatabase, config('database.connections.sqlite.database'));
        $this->assertSame('contact-assistant-browser.sqlite', basename($browserDatabase));
        $this->assertStringStartsWith('/private/tmp/contact-assistant-', realpath(dirname($browserDatabase)));
    }
    ContactAssistantSetting::factory()->enabled()->create(['name' => 'Asistente de Contacto']);
    Service::factory()->create(['name' => 'Consultoría']);
    LegalPage::factory()->create(['type' => 'privacy', 'is_published' => true]);
    if ($directory = getenv('CONTACT_ASSISTANT_PREVIEW_DIR')) {
        Vite::useHotFile($directory.'/absent-hot-file');
        Vite::createAssetPathsUsing(fn (string $path): string => '/'.$path);
    }
    $response = $this->get('/contacto')->assertOk()->assertSee('data-contact-assistant', false);
    if ($directory) {
        file_put_contents($directory.'/index.html', $response->getContent());
    }
    if ($browserDatabase) {
        DB::commit();
    }
});

it('drives the rendered client flow back corrections server errors retry and blocked popup fallback', function (bool $servicesEnabled, bool $emailRequired, bool $hasServices): void {
    ContactAssistantSetting::factory()->enabled()->create(['services_enabled' => $servicesEnabled, 'email_required' => $emailRequired]);
    $service = $hasServices ? Service::factory()->create(['name' => 'Consultoría']) : null;
    $html = $this->get('/contacto')->assertOk()->getContent();
    $process = new Process(['node', base_path('tests/Js/contact-assistant-flow.mjs')]);
    $refreshToken = app(ContactAssistant::class)->configuration()['submission_token'];
    $process->setInput($html.'<input data-refresh-token value="'.e($refreshToken).'">');
    $process->setTimeout(20);
    $process->mustRun();
    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    expect($result['completed'])->toBeTrue()->and($result['calls'])->toBe(3);
    $this->postJson('/asistente-contacto', $result['payload'])->assertAccepted()->assertJsonPath('whatsapp_status', 'link_prepared');
    $this->assertDatabaseHas('contact_messages', ['name' => 'Ana Ejemplo', 'phone' => '+34611123456', 'service_id' => $servicesEnabled ? $service?->id : null, 'source' => 'contact_assistant']);
})->with([
    'services and optional email' => [true, false, true],
    'services disabled and required email' => [false, true, true],
    'general consultation with empty catalog' => [true, false, false],
]);
