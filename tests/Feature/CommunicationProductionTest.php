<?php

namespace Tests\Feature;

use App\Filament\Pages\MailSettings;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\GeneralSetting;
use App\Models\MailSetting;
use App\Models\User;
use App\Services\ContactMail;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\WithCachedConfig;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CommunicationProductionTest extends TestCase
{
    use LazilyRefreshDatabase, WithCachedConfig;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config([
            'contact.smtp_allowed_hosts' => ['smtp.example.test', 'other.example.test'],
            'contact.smtp_port_schemes' => [465 => 'smtps', 587 => 'smtp'],
            'contact.notifications_enabled' => false,
            'contact.recipients' => [],
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => 'smtp.example.test', 'port' => 587, 'scheme' => 'smtp', 'local_domain' => 'site.example.test'],
            'mail.from' => ['address' => 'sender@example.test', 'name' => 'Empresa'],
        ]);
    }

    public static function secureTransports(): array
    {
        return [[true, 465, 'smtps'], [true, 587, 'smtp'], [false, 465, 'smtps'], [false, 587, 'smtp']];
    }

    #[DataProvider('secureTransports')]
    public function test_constructs_native_secure_transport_without_connecting(bool $panel, int $port, string $scheme): void
    {
        config(['mail.mailers.smtp.port' => $port, 'mail.mailers.smtp.scheme' => $scheme, 'mail.mailers.smtp.require_tls' => false, 'mail.mailers.smtp.verify_peer' => false]);
        $settings = MailSetting::factory()->make(['use_panel_smtp' => $panel, 'smtp_host' => 'smtp.example.test', 'smtp_port' => $port, 'smtp_scheme' => $scheme]);

        $configuration = app(ContactMail::class)->transportConfiguration($settings);
        $transport = Mail::build($configuration)->getSymfonyTransport();

        $this->assertTrue($transport->isTlsRequired());
        $this->assertTrue($transport->isAutoTls());
        $this->assertSame('site.example.test', $transport->getLocalDomain());
        $this->assertSame($port === 465, $transport->getStream()->isTLS());
        $this->assertTrue($configuration['verify_peer']);
        $this->assertSame(10, $configuration['timeout']);
    }

    public function test_rejects_port_security_mismatch_before_saving(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        Livewire::test(MailSettings::class)->fillForm(['use_panel_smtp' => true, 'smtp_host' => 'smtp.example.test', 'smtp_port' => 465, 'smtp_scheme' => 'smtp'])
            ->call('save')->assertHasFormErrors(['smtp_scheme']);

        $this->assertDatabaseCount('mail_settings', 0);
    }

    public function test_additional_port_requires_operator_authorization_and_matching_security(): void
    {
        $settings = MailSetting::factory()->make(['use_panel_smtp' => true, 'smtp_host' => 'smtp.example.test', 'smtp_port' => 2525, 'smtp_scheme' => 'smtp']);
        try {
            app(ContactMail::class)->transportConfiguration($settings);
            $this->fail('An unauthorized panel port was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('port', $exception->errors());
        }
        config(['contact.smtp_port_schemes' => [465 => 'smtps', 587 => 'smtp', 2525 => 'smtp']]);
        $this->actingAs(User::factory()->administrator()->create());

        Livewire::test(MailSettings::class)->fillForm(['use_panel_smtp' => true, 'smtp_host' => 'smtp.example.test', 'smtp_port' => 2525, 'smtp_scheme' => 'smtp'])->call('save')->assertHasNoFormErrors();

        $this->assertSame(2525, MailSetting::current()->smtp_port);
        $this->assertTrue(app(ContactMail::class)->transportConfiguration(MailSetting::current())['require_tls']);
    }

    public function test_operator_port_declarations_preserve_secure_defaults_and_ignore_invalid_entries(): void
    {
        $environment = Env::getRepository();
        $previous = $environment->get('CONTACT_SMTP_PORT_SCHEMES');
        try {
            $environment->set('CONTACT_SMTP_PORT_SCHEMES', '2525:smtp,465:smtp,587:smtps,25:plain,0:smtp,65536:smtp,invalid');

            $configuration = require config_path('contact.php');

            $this->assertSame([465 => 'smtps', 587 => 'smtp', 2525 => 'smtp'], $configuration['smtp_port_schemes']);
        } finally {
            if ($previous === null) {
                $environment->clear('CONTACT_SMTP_PORT_SCHEMES');
            } else {
                $environment->set('CONTACT_SMTP_PORT_SCHEMES', $previous);
            }
        }
    }

    public function test_active_notifications_require_effective_recipients(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        Livewire::test(MailSettings::class)->fillForm(['notifications_enabled' => true])->call('save')->assertHasFormErrors(['recipients']);

        $this->assertDatabaseCount('mail_settings', 0);
    }

    public function test_active_notifications_validate_environment_sender_and_transport(): void
    {
        config(['contact.recipients' => ['owner@example.test'], 'mail.from.address' => 'invalid']);
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test(MailSettings::class)->fillForm(['notifications_enabled' => true])->call('save')->assertHasFormErrors(['from_address']);
        config(['mail.from.address' => 'sender@example.test', 'mail.default' => 'log']);

        Livewire::test(MailSettings::class)->fillForm(['notifications_enabled' => true])->call('save')->assertHasFormErrors(['use_panel_smtp']);

        $this->assertDatabaseCount('mail_settings', 0);
    }

    public function test_panel_changes_are_read_on_new_requests_with_cached_configuration(): void
    {
        config(['contact.recipients' => ['fallback@example.test']]);
        $this->actingAs(User::factory()->administrator()->create());
        $message = ContactMessage::factory()->create(['message' => 'Historical original']);
        $original = $message->fresh()->getAttributes();
        Mail::shouldReceive('build')->never();
        $this->assertTrue($this->app->configurationIsCached());
        Livewire::test(MailSettings::class)->fillForm(['notifications_enabled' => true])->call('save')->assertHasNoFormErrors();
        $this->get('/admin/mail-settings')->assertOk()->assertSee('fallback@example.test');

        Livewire::test(MailSettings::class)->fillForm(['recipients' => ['new@example.test'], 'from_address' => 'new-sender@example.test'])->call('save')->assertHasNoFormErrors();

        $this->get('/admin/mail-settings')->assertOk()->assertSee('new@example.test')->assertSee('new-sender@example.test')->assertDontSee('fallback@example.test');
        $this->assertSame($original, $message->fresh()->getAttributes());
        $this->assertSame('sender@example.test', config('mail.from.address'));
    }

    public function test_host_change_cannot_reuse_stored_credentials_without_replacement(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        MailSetting::factory()->create(['use_panel_smtp' => true, 'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'smtp_scheme' => 'smtp', 'smtp_username' => 'old-user', 'smtp_password' => 'old-secret']);
        Livewire::test(MailSettings::class)->fillForm(['smtp_host' => 'other.example.test'])->call('save')->assertHasFormErrors(['smtp_host']);
        $this->assertSame('smtp.example.test', MailSetting::current()->smtp_host);
        $this->assertSame('old-secret', MailSetting::current()->smtp_password);

        Livewire::test(MailSettings::class)->fillForm(['smtp_host' => 'other.example.test', 'smtp_username' => 'new-user', 'smtp_password' => 'new-secret'])->call('save')->assertHasNoFormErrors()
            ->assertSet('data.smtp_username', null)->assertSet('data.smtp_password', null);

        $this->assertSame('other.example.test', MailSetting::current()->smtp_host);
        $this->assertSame('new-secret', MailSetting::current()->smtp_password);
        $this->get('/admin/mail-settings')->assertOk()->assertDontSee('new-secret')->assertDontSee('new-user');
    }

    public function test_database_revocation_blocks_save_and_test_even_with_stale_authenticated_user(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);
        $save = Livewire::test(MailSettings::class);
        $send = Livewire::test(MailSettings::class)->set('testRecipient', 'owner@example.test');
        DB::table('users')->where('id', $admin->id)->update(['role' => UserRole::Editor->value]);
        Mail::shouldReceive('build')->never();

        $save->call('save')->assertForbidden();
        $send->call('sendTest')->assertForbidden();

        $this->assertDatabaseCount('mail_settings', 0);
    }

    public function test_environment_mismatches_and_unsafe_credentials_are_rejected(): void
    {
        config(['mail.mailers.smtp.port' => 587, 'mail.mailers.smtp.scheme' => 'smtps']);
        try {
            app(ContactMail::class)->transportConfiguration(MailSetting::factory()->make());
            $this->fail('The environment security mismatch was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scheme', $exception->errors());
        }
        config(['mail.mailers.smtp.scheme' => 'smtp', 'mail.mailers.smtp.username' => "unsafe\r\nuser", 'mail.mailers.smtp.password' => 'secret']);

        $this->expectException(ValidationException::class);
        app(ContactMail::class)->transportConfiguration(MailSetting::factory()->make());
    }

    public function test_summary_reports_unreadable_credentials_without_exposing_ciphertext(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $settings = MailSetting::factory()->create(['use_panel_smtp' => true, 'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'smtp_scheme' => 'smtp']);
        DB::table('mail_settings')->where('id', $settings->id)->update(['smtp_username' => 'broken-private-ciphertext', 'smtp_password' => 'broken-private-ciphertext']);

        $this->get('/admin/mail-settings')->assertOk()->assertSee('Credenciales ilegibles')->assertDontSee('broken-private-ciphertext');

        $this->assertModelExists($settings);
    }

    public function test_reply_to_uses_visitor_and_preserves_authorized_sender(): void
    {
        $contact = ContactMessage::factory()->make(['email' => 'visitor@example.test']);
        $mail = new ContactMessageReceived($contact, GeneralSetting::factory()->make(), ['address' => 'sender@example.test', 'name' => 'Empresa']);

        $envelope = $mail->envelope();

        $this->assertSame('visitor@example.test', $envelope->replyTo[0]->address);
        $this->assertSame('sender@example.test', $envelope->from->address);
        $contact->email = "visitor@example.test\r\nBcc: attacker@example.test";
        $this->expectException(ValidationException::class);
        $mail->envelope();
    }

    public function test_notification_filters_and_dates_are_independent_of_read_status(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $historical = ContactMessage::factory()->create();
        $attempted = ContactMessage::factory()->create();
        $attempted->notification_attempted_at = now();
        $attempted->save();
        $accepted = ContactMessage::factory()->create();
        $accepted->notification_attempted_at = now();
        $accepted->notified_at = now();
        $accepted->save();

        Livewire::test(ListContactMessages::class)->filterTable('notification', 'unattempted')->assertCanSeeTableRecords([$historical])->assertCanNotSeeTableRecords([$attempted, $accepted]);
        Livewire::test(ListContactMessages::class)->filterTable('notification', 'unconfirmed')->assertCanSeeTableRecords([$attempted])->assertCanNotSeeTableRecords([$historical, $accepted]);
        Livewire::test(ListContactMessages::class)->filterTable('notification', 'accepted')->assertCanSeeTableRecords([$accepted])->assertCanNotSeeTableRecords([$historical, $attempted]);
        Livewire::test(ViewContactMessage::class, ['record' => $accepted->id])->callAction('changeStatus')->assertHasNoErrors();

        $this->assertSame('accepted', $accepted->fresh()->notificationStatus());
        $this->get('/admin/contact-messages/'.$attempted->id)->assertOk()->assertSee('Intentada, sin aceptación confirmada')->assertSee('Intento de notificación');
        $this->get('/admin/mail-settings')->assertOk()->assertSee('Ayuda rápida')->assertSee('Recibidos y Spam');
    }
}
