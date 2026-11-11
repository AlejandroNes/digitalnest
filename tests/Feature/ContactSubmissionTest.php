<?php

use App\ContactMessageStatus;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\MailSetting;
use App\Services\ContactMail;
use App\View\Components\ContactForm;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config(['app.url' => 'https://corporate.test', 'mail.default' => 'smtp']);
});

function validContactPayload(array $overrides = []): array
{
    return array_replace(['name' => 'Ana Ejemplo', 'email' => 'ana@example.test', 'phone' => '+591 77777777', 'subject' => 'Consulta', 'message' => 'Quisiera información sobre sus servicios.', 'submission_token' => (new ContactForm)->submissionToken()], $overrides);
}

it('renders a reusable unstyled form with CSRF labels honeypot and an opaque token', function (): void {
    $this->get('/contacto')->assertOk()->assertSee('name="_token"', false)->assertSee('name="submission_token"', false)
        ->assertSee('name="website"', false)->assertSee('Nombre completo')->assertSee('action="/contacto"', false);
    $this->assertDatabaseCount('contact_messages', 0);
});

it('persists valid input with pending status and returns no personal data or delivery claim', function (): void {
    $this->postJson('/contacto', validContactPayload(['status' => 'read', 'notified_at' => now()]))->assertAccepted()->assertExactJson(['message' => 'Tu consulta se ha guardado correctamente.']);
    $record = ContactMessage::query()->sole();
    expect($record->name)->toBe('Ana Ejemplo');
    expect($record->email)->toBe('ana@example.test');
    expect($record->phone)->toBe('+591 77777777');
    expect($record->subject)->toBe('Consulta');
    expect($record->status)->toBe(ContactMessageStatus::Pending);
    expect($record->notified_at)->toBeNull();
});

it('accepts omitted optional fields and redirects safely for Blade frontends', function (): void {
    $data = validContactPayload(['return_to' => '/servicios/consultoria']);
    unset($data['phone'], $data['subject']);
    $this->post('/contacto', $data)->assertRedirect('https://corporate.test/servicios/consultoria')->assertSessionHas('contact_success');
    $record = ContactMessage::query()->sole();
    expect($record->phone)->toBeNull();
    expect($record->subject)->toBeNull();
});

it('rejects invalid input without storing messages', function (string $field, mixed $value): void {
    $this->postJson('/contacto', validContactPayload([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors([$field]);
    $this->assertDatabaseCount('contact_messages', 0);
})->with([
    ['name', ''], ['name', 'A'], ['name', str_repeat('x', 151)], ['name', '<script>alert(1)</script>'], ['name', "Ana\r\nBcc: other@example.test"],
    ['email', 'bad'], ['email', "ana@example.test\r\nBcc: other@example.test"], ['email', str_repeat('a', 250).'@example.test'],
    ['phone', 'javascript:alert(1)'], ['phone', str_repeat('1', 41)],
    ['subject', str_repeat('x', 201)], ['subject', "Hola\r\nBcc: evil@example.test"],
    ['message', 'short'], ['message', str_repeat('x', 10001)], ['message', "Mensaje con\x00control"],
    ['submission_token', 'forged'], ['submission_token', null], ['return_to', '//evil.test'], ['return_to', '/admin'],
]);

it('rejects expired future and malformed signed tokens', function (array $payload): void {
    $token = Crypt::encryptString(json_encode($payload));
    $this->postJson('/contacto', validContactPayload(['submission_token' => $token]))->assertUnprocessable()->assertJsonValidationErrors('submission_token');
    $this->assertDatabaseCount('contact_messages', 0);
})->with([
    fn (): array => ['key' => (string) Str::uuid(), 'issued_at' => now()->subMinutes(121)->timestamp],
    fn (): array => ['key' => (string) Str::uuid(), 'issued_at' => now()->addMinute()->timestamp],
    fn (): array => ['key' => 'not-a-uuid', 'issued_at' => now()->timestamp],
]);

it('rejects honeypot submissions without notifications or persistence', function (): void {
    $this->mock(ContactMail::class)->shouldNotReceive('notify');
    $this->postJson('/contacto', validContactPayload(['website' => 'https://bot.test']))->assertUnprocessable();
    $this->assertDatabaseCount('contact_messages', 0);
});

it('requires CSRF and limits repeated submissions', function (): void {
    app()->instance('env', 'local');
    $this->post('/contacto', validContactPayload())->assertStatus(419);
    $this->assertDatabaseCount('contact_messages', 0);
});

it('limits contact requests per minute', function (): void {
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/contacto', validContactPayload())->assertAccepted();
    }
    $this->postJson('/contacto', validContactPayload())->assertTooManyRequests();
    $this->assertDatabaseCount('contact_messages', 3);
});

it('limits contact requests per hour even when the minute window expires', function (): void {
    $this->freezeTime();
    for ($i = 0; $i < 20; $i++) {
        $this->postJson('/contacto', validContactPayload())->assertAccepted();
        $this->travel(61)->seconds();
    }
    $this->postJson('/contacto', validContactPayload())->assertTooManyRequests();
    $this->assertDatabaseCount('contact_messages', 20);
});

it('deduplicates retries and preserves the original payload', function (): void {
    $data = validContactPayload();
    $this->postJson('/contacto', $data)->assertAccepted();
    $this->postJson('/contacto', array_replace($data, ['message' => 'Contenido diferente que no debe sustituir el original.']))->assertAccepted();
    $this->assertDatabaseCount('contact_messages', 1);
    expect(ContactMessage::query()->sole()->message)->toBe($data['message']);
});

it('saves before notifying and sends at most once when a visitor retries', function (): void {
    MailSetting::factory()->create(['notifications_enabled' => true]);
    $fake = Mail::fake();
    $manager = Mockery::mock(MailManager::class);
    $manager->shouldReceive('build')->once()->andReturnUsing(function () use ($fake) {
        expect(ContactMessage::query()->count())->toBe(1);
        expect(ContactMessage::query()->sole()->notification_attempted_at)->not->toBeNull();

        return $fake;
    });
    Mail::swap($manager);
    $data = validContactPayload();
    $this->postJson('/contacto', $data)->assertAccepted();
    $this->postJson('/contacto', $data)->assertAccepted();
    $fake->assertSent(ContactMessageReceived::class, 1);
    expect(ContactMessage::query()->sole()->notified_at)->not->toBeNull();
});

it('conserves the message and logs no sensitive exception text when SMTP fails', function (): void {
    MailSetting::factory()->create(['notifications_enabled' => true]);
    Mail::shouldReceive('build')->once()->andThrow(new RuntimeException('password=very-secret recipient=ana@example.test message=private'));
    Log::shouldReceive('warning')->once()->with('contact.notification_failed', ['contact_message_id' => 1, 'exception' => RuntimeException::class]);
    $data = validContactPayload();
    $this->postJson('/contacto', $data)->assertAccepted()->assertExactJson(['message' => 'Tu consulta se ha guardado correctamente.']);
    $this->postJson('/contacto', $data)->assertAccepted();
    $this->assertDatabaseCount('contact_messages', 1);
    expect(ContactMessage::query()->sole()->notified_at)->toBeNull();
});

it('returns a safe storage failure without attempting mail or logging bindings', function (): void {
    ContactMessage::creating(fn () => throw new QueryException('sqlite', 'INSERT private@example.test secret-message', [], new PDOException('private-error')));
    $this->mock(ContactMail::class)->shouldNotReceive('notify');
    Log::shouldReceive('error')->once()->with('contact.storage_failed', ['exception' => QueryException::class]);
    $this->postJson('/contacto', validContactPayload())->assertServiceUnavailable()->assertExactJson(['message' => 'No pudimos guardar tu consulta. Inténtalo más tarde.']);
    $this->assertDatabaseCount('contact_messages', 0);
});
