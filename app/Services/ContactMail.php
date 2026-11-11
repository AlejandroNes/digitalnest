<?php

namespace App\Services;

use App\Mail\ContactMessageReceived;
use App\Mail\SmtpTestMessage;
use App\Models\ContactMessage;
use App\Models\GeneralSetting;
use App\Models\MailSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ContactMail
{
    public function notify(ContactMessage $message): bool
    {
        try {
            $settings = MailSetting::current();
            if (! $settings->notificationsEnabled()) {
                return false;
            }
            $claimed = ContactMessage::query()->whereKey($message->id)->whereNull('notification_attempted_at')->update(['notification_attempted_at' => now()]);
            if (! $claimed) {
                return false;
            }
            $recipients = $settings->notificationRecipients();
            Validator::make(['recipients' => $recipients], ['recipients' => 'required|array|min:1|max:10', 'recipients.*' => 'required|email:rfc|max:255|distinct'])->validate();
            $sender = $this->sender($settings);
            Mail::build($this->transportConfiguration($settings))->to($recipients)->send(new ContactMessageReceived($message, GeneralSetting::current(false), $sender));
            ContactMessage::query()->whereKey($message->id)->update(['notified_at' => now()]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('contact.notification_failed', ['contact_message_id' => $message->id, 'exception' => $exception::class]);

            return false;
        }
    }

    public function sendTest(string $recipient): bool
    {
        try {
            Validator::make(['recipient' => $recipient], ['recipient' => 'required|email:rfc|max:255'])->validate();
            $settings = MailSetting::current();
            Mail::build($this->transportConfiguration($settings))->to($recipient)->send(new SmtpTestMessage(GeneralSetting::current(false), $this->sender($settings)));

            return true;
        } catch (Throwable $exception) {
            Log::warning('mail.test_failed', ['exception' => $exception::class]);

            return false;
        }
    }

    /** @return array<string, mixed> */
    public function transportConfiguration(MailSetting $settings): array
    {
        if ($settings->use_panel_smtp) {
            $configuration = [
                'transport' => 'smtp', 'host' => $settings->smtp_host, 'port' => $settings->smtp_port,
                'scheme' => $settings->smtp_scheme, 'username' => $settings->smtp_username, 'password' => $settings->smtp_password,
                'require_tls' => true, 'auto_tls' => true, 'verify_peer' => true,
            ];
            Validator::make($configuration, [
                'host' => ['required', 'string', 'max:255', Rule::in(config('contact.smtp_allowed_hosts', []))],
                'port' => ['required', 'integer', Rule::in(array_keys($this->portSchemes()))],
                'scheme' => ['required', Rule::in(['smtp', 'smtps'])],
                'username' => ['nullable', 'string', 'max:255', 'required_with:password', 'not_regex:/[\r\n\x00]/'],
                'password' => ['nullable', 'string', 'max:1024', 'required_with:username'],
            ])->validate();
        } else {
            if (config('mail.default') !== 'smtp') {
                throw new RuntimeException('El correo de contacto requiere el transporte SMTP.');
            }
            $configuration = config('mail.mailers.smtp', []);
            if (! empty($configuration['url'])) {
                $configuration = array_merge($configuration, (new ConfigurationUrlParser)->parseConfiguration($configuration));
                if (isset($configuration['driver'])) {
                    if (! in_array($configuration['driver'], ['smtp', 'smtps'], true)) {
                        throw new RuntimeException('El transporte configurado no es SMTP.');
                    }
                    $configuration['scheme'] = $configuration['driver'];
                }
            }
            if (($configuration['transport'] ?? 'smtp') !== 'smtp') {
                throw new RuntimeException('El transporte configurado no es SMTP.');
            }
            $configuration['transport'] = 'smtp';
            unset($configuration['url'], $configuration['driver']);
        }
        $port = (int) ($configuration['port'] ?? 0);
        $scheme = $configuration['scheme'] ?? ($port === 465 ? 'smtps' : 'smtp');
        $expectedScheme = $settings->use_panel_smtp ? ($this->portSchemes()[$port] ?? null) : ($this->portSchemes()[$port] ?? $scheme);
        Validator::make(array_replace($configuration, ['scheme' => $scheme]), [
            'host' => ['required', 'string', 'max:255', 'not_regex:/[\\r\\n\\x00\\s]/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'scheme' => ['required', Rule::in(['smtp', 'smtps']), Rule::in([$expectedScheme])],
            'username' => ['nullable', 'string', 'max:255', 'required_with:password', 'not_regex:/[\r\n\x00]/'],
            'password' => ['nullable', 'string', 'max:1024', 'required_with:username'],
        ])->validate();
        $configuration['scheme'] = $scheme;
        $configuration['require_tls'] = true;
        $configuration['auto_tls'] = true;
        $configuration['verify_peer'] = true;
        $configuration['local_domain'] = config('mail.mailers.smtp.local_domain');
        $configuration['timeout'] = config('contact.smtp_timeout', 10);

        return $configuration;
    }

    /** @return array<int, string> */
    public function portSchemes(): array
    {
        return config('contact.smtp_port_schemes', [465 => 'smtps', 587 => 'smtp']);
    }

    public function validateSettings(MailSetting $settings): void
    {
        if ($settings->notificationsEnabled()) {
            Validator::make(['recipients' => $settings->notificationRecipients()], [
                'recipients' => 'required|array|min:1|max:10',
                'recipients.*' => 'required|email:rfc|max:255|distinct',
            ])->validate();
            $this->sender($settings);
        }
        if ($settings->use_panel_smtp || $settings->notificationsEnabled()) {
            $this->transportConfiguration($settings);
        }
    }

    /** @return array{ready: bool, issue: string} */
    public function readiness(MailSetting $settings): array
    {
        try {
            $this->sender($settings);
            $this->transportConfiguration($settings);
            $this->validateSettings($settings);

            return ['ready' => true, 'issue' => 'Datos completos. Confirma la recepción con un correo de prueba.'];
        } catch (DecryptException) {
            return ['ready' => false, 'issue' => 'Credenciales ilegibles. Elimínalas y vuelve a configurarlas.'];
        } catch (Throwable) {
            return ['ready' => false, 'issue' => 'Configuración incompleta o incompatible. Revisa remitente, destinatarios y servidor con el responsable del sitio.'];
        }
    }

    /** @return array{address: string, name: string} */
    public function sender(MailSetting $settings): array
    {
        $sender = ['address' => $settings->from_address ?: config('mail.from.address'), 'name' => $settings->from_name ?: config('mail.from.name') ?: GeneralSetting::current(false)->company_name];
        Validator::make($sender, ['address' => 'required|email:rfc|max:255', 'name' => ['required', 'string', 'max:150', 'not_regex:/[\r\n\x00]/']])->validate();

        return $sender;
    }
}
