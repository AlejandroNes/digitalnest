<?php

use App\Mail\ContactMessageReceived;
use App\Mail\SmtpTestMessage;
use App\Models\ContactMessage;
use App\Models\GeneralSetting;
use App\Models\MailSetting;
use App\Services\ContactMail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config(['mail.default' => 'smtp', 'mail.from' => ['address' => 'env@example.test', 'name' => 'Nombre entorno'], 'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => 'env.example.test', 'port' => 2525, 'username' => 'env-user', 'password' => 'env-password'], 'contact.smtp_allowed_hosts' => ['smtp.example.test'], 'contact.notifications_enabled' => true, 'contact.recipients' => ['env-owner@example.test']]);
});

it('defines panel and environment precedence without changing the global mail configuration', function (): void {
    $service = app(ContactMail::class);
    $settings = MailSetting::current();
    expect($settings->notificationsEnabled())->toBeTrue();
    expect($settings->notificationRecipients())->toBe(['env-owner@example.test']);
    expect($service->sender($settings))->toBe(['address' => 'env@example.test', 'name' => 'Nombre entorno']);
    expect($service->transportConfiguration($settings)['host'])->toBe('env.example.test');
    $settings = MailSetting::factory()->create(['notifications_enabled' => false, 'recipients' => ['panel-owner@example.test'], 'from_address' => 'panel@example.test', 'from_name' => 'Panel', 'use_panel_smtp' => true, 'smtp_host' => 'smtp.example.test', 'smtp_port' => 465, 'smtp_scheme' => 'smtps', 'smtp_username' => 'panel-user', 'smtp_password' => 'panel-password']);
    expect($settings->notificationsEnabled())->toBeFalse();
    expect($settings->notificationRecipients())->toBe(['panel-owner@example.test']);
    expect($service->sender($settings))->toBe(['address' => 'panel@example.test', 'name' => 'Panel']);
    $transport = $service->transportConfiguration($settings);
    expect($transport['host'])->toBe('smtp.example.test');
    expect($transport['password'])->toBe('panel-password');
    expect($transport['require_tls'])->toBeTrue();
    expect($transport['verify_peer'])->toBeTrue();
    expect($transport['timeout'])->toBe(10);
    expect(config('mail.mailers.smtp.password'))->toBe('env-password');
});

it('supports MAIL_URL while bounding connection time', function (): void {
    config(['mail.mailers.smtp.url' => 'smtp://url-user:url-pass@smtp-url.example.test:587']);
    $configuration = app(ContactMail::class)->transportConfiguration(MailSetting::current());
    expect($configuration['host'])->toBe('smtp-url.example.test');
    expect($configuration['username'])->toBe('url-user');
    expect($configuration['password'])->toBe('url-pass');
    expect($configuration['timeout'])->toBe(10);
});

it('never sends contact contents through log or failover mailers', function (string $mailer): void {
    config(['mail.default' => $mailer]);
    $record = ContactMessage::factory()->create();
    Mail::shouldReceive('build')->never();
    Log::shouldReceive('warning')->once()->with('contact.notification_failed', ['contact_message_id' => $record->id, 'exception' => RuntimeException::class]);
    expect(app(ContactMail::class)->notify($record))->toBeFalse();
    $this->assertModelExists($record);
})->with(['log', 'failover']);

it('does not send when disabled and never retries an already attempted notification', function (): void {
    Mail::shouldReceive('build')->never();
    $settings = MailSetting::factory()->create(['notifications_enabled' => false]);
    $message = ContactMessage::factory()->create();
    expect(app(ContactMail::class)->notify($message))->toBeFalse();
    expect($message->fresh()->notification_attempted_at)->toBeNull();
    $settings->update(['notifications_enabled' => true]);
    $message->notification_attempted_at = now();
    $message->save();
    expect(app(ContactMail::class)->notify($message))->toBeFalse();
});

it('sends an SMTP test with a reusable mailable and no visitor data', function (): void {
    $fake = Mail::fake();
    $manager = Mockery::mock(MailManager::class);
    $manager->shouldReceive('build')->once()->andReturn($fake);
    Mail::swap($manager);
    expect(app(ContactMail::class)->sendTest('owner@example.test'))->toBeTrue();
    $fake->assertSent(SmtpTestMessage::class, fn (SmtpTestMessage $mail): bool => $mail->hasTo('owner@example.test') && $mail->sender['address'] === 'env@example.test');
    $this->assertDatabaseCount('contact_messages', 0);
});

it('logs only technical classes for failed test sends', function (): void {
    Mail::shouldReceive('build')->once()->andThrow(new RuntimeException('password=secret user=private@example.test'));
    Log::shouldReceive('warning')->once()->with('mail.test_failed', ['exception' => RuntimeException::class]);
    expect(app(ContactMail::class)->sendTest('owner@example.test'))->toBeFalse();
});

it('renders responsive HTML and text templates with escaped content and general identity', function (): void {
    $identity = GeneralSetting::factory()->make(['company_name' => 'Empresa & ejemplo', 'logo' => 'site-identity/logo.png']);
    $record = ContactMessage::factory()->make(['name' => 'Ana', 'message' => '<script>alert(1)</script>\nSegunda línea']);
    $mail = new ContactMessageReceived($record, $identity, ['address' => 'sender@example.test', 'name' => 'Empresa']);
    $mail->assertSeeInHtml('Empresa & ejemplo')->assertSeeInHtml('<script>alert(1)</script>')->assertDontSeeInHtml('<script>', false)->assertSeeInHtml('site-identity/logo.png')->assertSeeInText('Nueva consulta de contacto')->assertSeeInText('Ana');
    expect($mail->content()->text)->toBe('mail.contact-message-text');
    expect($mail->envelope()->subject)->toBe('Nueva consulta de contacto');
    $test = new SmtpTestMessage($identity, ['address' => 'sender@example.test', 'name' => 'Empresa']);
    $test->assertSeeInHtml('Prueba de configuración SMTP')->assertSeeInText('Prueba de configuración SMTP');
});

it('rejects invalid persisted SMTP configuration before attempting any network call', function (): void {
    $settings = MailSetting::factory()->create(['notifications_enabled' => true, 'use_panel_smtp' => true, 'smtp_host' => 'unapproved.test', 'smtp_port' => 587, 'smtp_scheme' => 'smtp']);
    Mail::shouldReceive('build')->never();
    Log::shouldReceive('warning')->once();
    $message = ContactMessage::factory()->create();
    expect(app(ContactMail::class)->notify($message))->toBeFalse();
    expect($message->fresh()->notification_attempted_at)->not->toBeNull();
    expect($message->fresh()->notified_at)->toBeNull();
});
