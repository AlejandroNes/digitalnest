<?php

use App\ContactMessageStatus;
use App\Models\ContactAssistantSetting;
use App\Models\ContactMessage;
use App\Models\LegalPage;
use App\Models\Service;
use App\Services\ContactAssistant;
use App\Services\ContactMail;
use App\View\Components\ContactForm;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

function assistantPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Ana María O’Neill', 'whatsapp' => '0034 (611) 123-456',
        'email' => 'ana@example.test', 'message' => 'Necesito una propuesta.', 'consent' => true,
        'submission_token' => app(ContactAssistant::class)->configuration()['submission_token'],
    ], $overrides);
}

it('returns only the disabled flag by default and refuses submissions', function (): void {
    $this->getJson('/asistente-contacto')->assertOk()->assertExactJson(['enabled' => false]);
    $this->postJson('/asistente-contacto', [])->assertNotFound();
    $this->assertDatabaseCount('contact_messages', 0);
});

it('honors disabling and invalid destinations on both endpoints', function (array $settings): void {
    ContactAssistantSetting::factory()->create($settings);
    $this->getJson('/asistente-contacto')->assertOk()->assertExactJson(['enabled' => false]);
    $this->postJson('/asistente-contacto', [])->assertNotFound();
    $this->assertDatabaseCount('contact_messages', 0);
})->with([
    [['enabled' => false]], [['enabled' => true, 'destination_whatsapp' => null]],
    [['enabled' => true, 'destination_whatsapp' => 'https://evil.test']],
]);

it('exposes a minimal frontend contract with ordered active services and no destination', function (): void {
    $settings = ContactAssistantSetting::factory()->enabled()->create();
    $second = Service::factory()->create(['sort_order' => 2]);
    $first = Service::factory()->create(['sort_order' => 1]);
    Service::factory()->inactive()->create();
    $this->getJson('/asistente-contacto')->assertOk()->assertJsonPath('email_required', false)
        ->assertJsonPath('services', [['id' => $first->id, 'name' => $first->name], ['id' => $second->id, 'name' => $second->name]])
        ->assertJsonPath('privacy_url', null)->assertJsonMissingPath('destination_whatsapp')
        ->assertDontSee($settings->destination_whatsapp)->assertDontSee(ltrim($settings->destination_whatsapp, '+'));
    expect($settings->toArray())->not->toHaveKey('destination_whatsapp');
});

it('uses the existing published privacy page and saves the consent snapshot', function (): void {
    $this->freezeTime();
    config(['app.url' => 'https://corporate.test']);
    $settings = ContactAssistantSetting::factory()->enabled()->create();
    $privacy = LegalPage::factory()->create(['type' => 'privacy', 'is_published' => true]);
    $this->getJson('/asistente-contacto')->assertOk()->assertJsonPath('privacy_url', 'https://corporate.test/legal/privacy');

    $this->postJson('/asistente-contacto', assistantPayload())->assertAccepted();

    $record = ContactMessage::query()->sole();
    expect($record->consented_at->timestamp)->toBe(now()->timestamp)
        ->and($record->consent_text)->toBe($settings->consent_text)
        ->and($record->privacy_path)->toBe('/legal/privacy')
        ->and($record->privacy_revision)->toBe($privacy->revision);
});

it('saves requests in Contacto and prepares an encoded WhatsApp URL without sending mail', function (): void {
    ContactAssistantSetting::factory()->enabled()->create();
    $service = Service::factory()->create(['name' => 'Consultoría & diseño']);
    $this->mock(ContactMail::class)->shouldNotReceive('notify');
    $data = assistantPayload(['service_id' => $service->id, 'message' => 'Consulta & presupuesto? Sí.', 'source' => 'contact_form', 'status' => 'read', 'whatsapp_status' => 'delivered']);

    $response = $this->postJson('/asistente-contacto', $data)->assertAccepted()->assertJsonPath('whatsapp_status', 'link_prepared');

    $record = ContactMessage::query()->sole();
    expect($record->name)->toBe($data['name'])->and($record->phone)->toBe('+34611123456')
        ->and($record->email)->toBe($data['email'])->and($record->service_id)->toBe($service->id)
        ->and($record->service_name)->toBe($service->name)->and($record->source)->toBe('contact_assistant')
        ->and($record->status)->toBe(ContactMessageStatus::Pending)->and($record->whatsapp_status)->toBe('link_prepared')
        ->and($record->notified_at)->toBeNull()->and($record->notification_attempted_at)->toBeNull();
    $url = $response->json('whatsapp_url');
    expect($url)->toStartWith('https://wa.me/34911234567?text=')->not->toContain('Consulta &');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect($query)->toHaveCount(1)->and($query['text'])->toContain($service->name, $data['message'], $data['name']);
    $this->assertDatabaseCount('contact_messages', 1);
});

it('accepts optional email message and service without inventing data', function (): void {
    ContactAssistantSetting::factory()->enabled()->create();
    $data = assistantPayload();
    unset($data['email'], $data['message']);
    $this->postJson('/asistente-contacto', $data)->assertAccepted();
    $record = ContactMessage::query()->sole();
    expect($record->email)->toBeNull()->and($record->message)->toBe('')->and($record->service_id)->toBeNull();
});

it('requires email when configured', function (): void {
    ContactAssistantSetting::factory()->enabled()->create(['email_required' => true]);
    $this->postJson('/asistente-contacto', assistantPayload(['email' => null]))->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->assertDatabaseCount('contact_messages', 0);
});

it('rejects invalid fields without persistence', function (string $field, mixed $value): void {
    ContactAssistantSetting::factory()->enabled()->create();
    $this->postJson('/asistente-contacto', assistantPayload([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('contact_messages', 0);
})->with([
    ['name', ''], ['name', 'A'], ['name', str_repeat('a', 151)], ['name', '<script>alert(1)</script>'], ['name', "Ana\nNombre"], ['name', '12345'],
    ['whatsapp', ''], ['whatsapp', '611123456'], ['whatsapp', '+012345678'], ['whatsapp', '+1234567'], ['whatsapp', '+1234567890123456'],
    ['whatsapp', '+34abc611123456'], ['whatsapp', 'https://evil.test'], ['whatsapp', ['+34611123456']],
    ['email', 'invalid'], ['email', "ana@example.test\r\nBcc: a@evil.test"], ['email', str_repeat('a', 256)],
    ['message', str_repeat('x', 1001)], ['message', '<img src=x onerror=alert(1)>'], ['message', "text\x00secret"],
    ['consent', false], ['consent', null], ['consent', 'false'], ['website', 'bot.test'],
    ['submission_token', null], ['submission_token', 'forged'], ['submission_token', str_repeat('x', 2001)],
    ['service_id', 99999], ['service_id', '1 OR 1=1'],
]);

it('accepts international numbers and Unicode names without a country restriction', function (string $phone, string $expected, string $name): void {
    ContactAssistantSetting::factory()->enabled()->create();
    $this->postJson('/asistente-contacto', assistantPayload(['whatsapp' => $phone, 'name' => $name]))->assertAccepted();
    expect(ContactMessage::query()->sole()->phone)->toBe($expected);
})->with([
    ['+1 (415) 555-2671', '+14155552671', '李明'],
    ['0044 7700.900-123', '+447700900123', 'José Pérez'],
    ['+591 77777777', '+59177777777', 'Jean-Luc Martin'],
]);

it('rejects inactive services and services disabled after loading the contract', function (): void {
    $settings = ContactAssistantSetting::factory()->enabled()->create();
    $service = Service::factory()->inactive()->create();
    $this->postJson('/asistente-contacto', assistantPayload(['service_id' => $service->id]))->assertUnprocessable()->assertJsonValidationErrors('service_id');
    $settings->update(['services_enabled' => false]);
    $this->getJson('/asistente-contacto')->assertJsonPath('services', []);
    $this->postJson('/asistente-contacto', assistantPayload(['service_id' => $service->id]))->assertUnprocessable()->assertJsonValidationErrors('service_id');
    $this->assertDatabaseCount('contact_messages', 0);
});

it('accepts sites without services and preserves selected names after service deletion', function (): void {
    $settings = ContactAssistantSetting::factory()->enabled()->create(['services_enabled' => false]);
    $this->postJson('/asistente-contacto', assistantPayload())->assertAccepted();
    $settings->update(['services_enabled' => true]);
    $service = Service::factory()->create();
    $this->postJson('/asistente-contacto', assistantPayload(['service_id' => $service->id]))->assertAccepted();
    $record = ContactMessage::query()->where('service_id', $service->id)->sole();
    $service->delete();
    expect($record->fresh()->service_id)->toBeNull()->and($record->fresh()->service_name)->toBe($service->name);
});

it('deduplicates retries and builds the link from the original saved request', function (): void {
    ContactAssistantSetting::factory()->enabled()->create();
    $data = assistantPayload();
    $first = $this->postJson('/asistente-contacto', $data)->assertAccepted();
    $second = $this->postJson('/asistente-contacto', array_replace($data, ['name' => 'Otro Nombre', 'message' => 'Otra consulta']))->assertAccepted();
    expect($second->json('whatsapp_url'))->toBe($first->json('whatsapp_url'));
    $this->assertDatabaseCount('contact_messages', 1);
});

it('rejects cross purpose tokens and leaves traditional Contacto compatible', function (): void {
    ContactAssistantSetting::factory()->enabled()->create();
    $assistant = assistantPayload();
    $traditional = ['name' => 'Ana Ejemplo', 'email' => 'ana@example.test', 'message' => 'Consulta tradicional de contacto.', 'submission_token' => (new ContactForm)->submissionToken()];
    $this->postJson('/asistente-contacto', array_replace($assistant, ['submission_token' => $traditional['submission_token']]))->assertUnprocessable()->assertJsonValidationErrors('submission_token');
    $this->postJson('/contacto', array_replace($traditional, ['submission_token' => $assistant['submission_token']]))->assertUnprocessable()->assertJsonValidationErrors('submission_token');
    $this->postJson('/contacto', array_replace($traditional, ['source' => 'contact_assistant', 'service_id' => 999]))->assertAccepted();
    $record = ContactMessage::query()->sole();
    expect($record->source)->toBe('contact_form')->and($record->service_id)->toBeNull()->and($record->consented_at)->toBeNull();
});

it('rejects expired future and malformed signed assistant tokens', function (string $kind): void {
    $this->freezeTime();
    $settings = ContactAssistantSetting::factory()->enabled()->create();
    $payload = ['key' => (string) Str::uuid(), 'issued_at' => now()->timestamp, 'purpose' => 'contact_assistant', 'context' => app(ContactAssistant::class)->consentContext($settings)];
    $payload = match ($kind) {
        'expired' => array_replace($payload, ['issued_at' => now()->subMinutes(121)->timestamp]),
        'future' => array_replace($payload, ['issued_at' => now()->addMinute()->timestamp]),
        'key' => array_replace($payload, ['key' => 'not-uuid']),
        'json' => null,
    };
    $this->postJson('/asistente-contacto', assistantPayload(['submission_token' => Crypt::encryptString(json_encode($payload))]))
        ->assertUnprocessable()->assertJsonValidationErrors('submission_token');
    $this->assertDatabaseCount('contact_messages', 0);
})->with(['expired', 'future', 'key', 'json']);

it('requires fresh consent after configuration or privacy revisions change', function (string $change): void {
    $settings = ContactAssistantSetting::factory()->enabled()->create();
    $privacy = LegalPage::factory()->create(['type' => 'privacy', 'is_published' => true]);
    $data = assistantPayload();
    if ($change === 'privacy') {
        $privacy->update(['content' => '<p>Nueva política.</p>']);
    } else {
        $settings->update(['consent_text' => 'Texto nuevo de consentimiento.']);
    }
    $this->postJson('/asistente-contacto', $data)->assertUnprocessable()->assertJsonValidationErrors('submission_token');
    $this->assertDatabaseCount('contact_messages', 0);
})->with(['settings', 'privacy']);

it('requires CSRF for assistant submission', function (): void {
    ContactAssistantSetting::factory()->enabled()->create();
    $data = assistantPayload();
    app()->instance('env', 'local');
    $this->postJson('/asistente-contacto', $data)->assertStatus(419);
    $this->assertDatabaseCount('contact_messages', 0);
});

it('shares the minute limit with the traditional contact endpoint', function (): void {
    ContactAssistantSetting::factory()->enabled()->create();
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/asistente-contacto', assistantPayload())->assertAccepted();
    }
    $this->postJson('/contacto', [])->assertTooManyRequests();
    $this->assertDatabaseCount('contact_messages', 3);
});

it('shares the hourly antispam limit after minute windows expire', function (): void {
    $this->freezeTime();
    ContactAssistantSetting::factory()->enabled()->create();
    for ($i = 0; $i < 20; $i++) {
        $this->postJson('/asistente-contacto', assistantPayload())->assertAccepted();
        $this->travel(61)->seconds();
    }
    $this->postJson('/asistente-contacto', assistantPayload())->assertTooManyRequests();
    $this->assertDatabaseCount('contact_messages', 20);
});

it('returns a safe storage failure without logging personal data', function (): void {
    ContactAssistantSetting::factory()->enabled()->create();
    ContactMessage::creating(fn () => throw new QueryException('sqlite', 'INSERT private@example.test', [], new PDOException('secret')));
    Log::shouldReceive('error')->once()->with('contact_assistant.storage_failed', ['exception' => QueryException::class]);
    $this->postJson('/asistente-contacto', assistantPayload())->assertServiceUnavailable()->assertExactJson(['message' => 'No pudimos guardar tu consulta. Inténtalo más tarde.']);
    $this->assertDatabaseCount('contact_messages', 0);
});

it('validates direct service calls before creating requests for other frontends', function (): void {
    ContactAssistantSetting::factory()->enabled()->create();
    $assistant = app(ContactAssistant::class);
    expect(fn () => $assistant->store(assistantPayload(['consent' => false])))
        ->toThrow(ValidationException::class);
    $this->assertDatabaseCount('contact_messages', 0);
});

it('accepts traditional contact tokens issued before the shared token service', function (): void {
    $this->freezeTime();
    $token = Crypt::encryptString(json_encode(['key' => (string) Str::uuid(), 'issued_at' => now()->timestamp]));
    $this->postJson('/contacto', [
        'name' => 'Ana Ejemplo', 'email' => 'ana@example.test',
        'message' => 'Consulta desde un formulario existente.', 'submission_token' => $token,
    ])->assertAccepted();
    $this->assertDatabaseHas('contact_messages', ['source' => 'contact_form', 'email' => 'ana@example.test']);
});
