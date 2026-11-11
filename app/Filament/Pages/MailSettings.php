<?php

namespace App\Filament\Pages;

use App\Models\MailSetting;
use App\Models\User;
use App\Services\ContactMail;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MailSettings extends Page
{
    protected static ?string $title = 'Configuración de correo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|\UnitEnum|null $navigationGroup = 'Comunicación';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.mail-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?string $testRecipient = null;

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', MailSetting::class);
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', MailSetting::class);
        $this->fillSettings();
    }

    private function fillSettings(): void
    {
        $settings = MailSetting::current();
        $this->form->model($settings)->fill($settings->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->statePath('data')->model(MailSetting::current())->components([
            Section::make('Notificaciones de contacto')->description('Recibe las consultas por correo. También se guardan en Mensajes de contacto aunque el correo falle.')->schema([
                Select::make('notifications_enabled')->label('Notificaciones')->options([1 => 'Activadas', 0 => 'Desactivadas'])->placeholder('Usar configuración del sitio')->rules(['nullable', 'boolean']),
                TagsInput::make('recipients')->label('Destinatarios')->placeholder('correo@empresa.com')->rules(['nullable', 'array', 'max:10'])
                    ->nestedRecursiveRules(['email:rfc', 'max:255', 'distinct'])->dehydrateStateUsing(fn (?array $state): ?array => $state ?: null)
                    ->helperText('Hasta 10 direcciones. Vacío utiliza los destinatarios preparados por el responsable del sitio.'),
                TextInput::make('from_address')->label('Correo del remitente')->email()->maxLength(255)->helperText('Usa una dirección autorizada por tu proveedor. Vacío utiliza el remitente del sitio.'),
                TextInput::make('from_name')->label('Nombre del remitente')->maxLength(150)->regex('/^[^\r\n\x00]*$/D')->helperText('Vacío utiliza el nombre del remitente del sitio o de la empresa.'),
            ])->columns(['default' => 1, 'md' => 2]),
            Section::make('SMTP')->description('Usa los datos entregados por tu proveedor. Estos ajustes sirven para contacto y pruebas; la recuperación de contraseña se configura por separado.')->schema([
                Toggle::make('use_panel_smtp')->label('Usar SMTP del panel')->required()->live()
                    ->helperText('Desactivado: utiliza el servidor preparado para el sitio. Activado: utiliza los datos de este bloque.'),
                Select::make('smtp_host')->label('Host SMTP permitido')->options(array_combine(config('contact.smtp_allowed_hosts', []), config('contact.smtp_allowed_hosts', [])))
                    ->required(fn (Get $get): bool => (bool) $get('use_panel_smtp'))->rule(Rule::in(config('contact.smtp_allowed_hosts', [])))->disabled(fn (Get $get): bool => ! $get('use_panel_smtp'))->validatedWhenNotDehydrated(false)
                    ->helperText('Si tu servidor no aparece, pide al responsable del sitio que lo habilite.'),
                Select::make('smtp_port')->label('Puerto')->options(array_combine(array_keys(app(ContactMail::class)->portSchemes()), array_keys(app(ContactMail::class)->portSchemes())))->required(fn (Get $get): bool => (bool) $get('use_panel_smtp'))->rule(Rule::in(array_keys(app(ContactMail::class)->portSchemes())))->disabled(fn (Get $get): bool => ! $get('use_panel_smtp'))->validatedWhenNotDehydrated(false),
                Select::make('smtp_scheme')->label('Seguridad')->options(['smtp' => 'STARTTLS obligatorio', 'smtps' => 'TLS implícito'])->required(fn (Get $get): bool => (bool) $get('use_panel_smtp'))->rule(Rule::in(['smtp', 'smtps']))->disabled(fn (Get $get): bool => ! $get('use_panel_smtp'))->validatedWhenNotDehydrated(false),
                TextInput::make('smtp_username')->label('Nuevo usuario SMTP')->password()->autocomplete('new-password')->maxLength(255)->regex('/^[^\r\n\x00]*$/D')
                    ->helperText('Campo de escritura: vacío conserva el valor cifrado actual. Nunca se recupera al formulario.'),
                TextInput::make('smtp_password')->label('Nueva contraseña SMTP')->password()->autocomplete('new-password')->maxLength(1024)
                    ->helperText('Vacío conserva la contraseña cifrada actual. No se muestra la contraseña guardada.'),
                Toggle::make('clear_credentials')->label('Eliminar las credenciales guardadas')->default(false)->dehydrated(false),
            ])->columns(['default' => 1, 'md' => 2]),
        ]);
    }

    public function save(): void
    {
        $settings = MailSetting::current();
        $this->authorizeCurrentUser('update', $settings);
        try {
            $state = $this->form->getState();
        } catch (ValidationException $exception) {
            $this->clearSubmittedCredentials();
            throw $exception;
        }
        foreach (['smtp_username', 'smtp_password'] as $field) {
            if (! filled($state[$field] ?? null)) {
                unset($state[$field]);
            }
        }
        if ($settings->exists && ($state['smtp_host'] ?? $settings->smtp_host) !== $settings->smtp_host
            && (filled($settings->getRawOriginal('smtp_username')) || filled($settings->getRawOriginal('smtp_password')))
            && ! ($this->data['clear_credentials'] ?? false)
            && (! filled($state['smtp_username'] ?? null) || ! filled($state['smtp_password'] ?? null))) {
            $this->clearSubmittedCredentials();
            throw ValidationException::withMessages(['data.smtp_host' => 'Al cambiar de servidor, sustituye usuario y contraseña o elimina explícitamente las credenciales guardadas.']);
        }
        $settings->fill($state);
        if ($this->data['clear_credentials'] ?? false) {
            $settings->smtp_username = null;
            $settings->smtp_password = null;
        }
        if ($settings->use_panel_smtp || $settings->notificationsEnabled()) {
            try {
                app(ContactMail::class)->validateSettings($settings);
            } catch (ValidationException $exception) {
                $messages = [];
                foreach ($exception->errors() as $field => $errors) {
                    $target = match ($field) {
                        'address' => 'from_address', 'name' => 'from_name',
                        'host', 'port', 'scheme', 'username', 'password' => $settings->use_panel_smtp ? 'smtp_'.$field : 'use_panel_smtp',
                        default => $field,
                    };
                    $messages['data.'.$target] = ['Revisa este dato y su configuración efectiva antes de activar las notificaciones.'];
                }
                $this->clearSubmittedCredentials();
                throw ValidationException::withMessages($messages);
            } catch (DecryptException) {
                $this->clearSubmittedCredentials();
                Notification::make()->title('No se pudieron recuperar las credenciales SMTP')->body('Elimina las credenciales guardadas y vuelve a configurarlas.')->danger()->send();

                return;
            } catch (\RuntimeException) {
                $this->clearSubmittedCredentials();
                throw ValidationException::withMessages(['data.use_panel_smtp' => 'El servidor del sitio debe ser SMTP. Configúralo con el responsable del sitio o utiliza un servidor permitido del panel.']);
            }
        }
        $settings->id = 1;
        try {
            $settings->save();
        } catch (QueryException $exception) {
            $this->clearSubmittedCredentials();
            Log::error('mail.settings_storage_failed', ['exception' => $exception::class]);
            Notification::make()->title('No se pudo guardar la configuración de correo')->danger()->send();

            return;
        }
        $this->fillSettings();
        Notification::make()->title('Configuración guardada')->success()->send();
    }

    private function clearSubmittedCredentials(): void
    {
        $this->data['smtp_username'] = null;
        $this->data['smtp_password'] = null;
    }

    private function authorizeCurrentUser(string $ability, MailSetting $settings): void
    {
        $user = User::query()->find(auth()->id());
        abort_unless($user, 403);
        Gate::forUser($user)->authorize($ability, $settings);
    }

    /** @return array<string, mixed> */
    public function savedSummary(): array
    {
        $settings = MailSetting::current();
        $this->authorizeCurrentUser('viewAny', $settings);

        return [
            'enabled' => $settings->notificationsEnabled(),
            'mode' => $settings->use_panel_smtp ? 'Servidor del panel' : 'Servidor del sitio',
            'sender' => $settings->from_address ?: config('mail.from.address'),
            'recipients' => $settings->notificationRecipients(),
            'credentials' => filled($settings->getRawOriginal('smtp_username')) && filled($settings->getRawOriginal('smtp_password')),
            ...app(ContactMail::class)->readiness($settings),
        ];
    }

    public function sendTest(): void
    {
        $this->authorizeCurrentUser('sendTest', MailSetting::current());
        $this->validate(['testRecipient' => 'required|email:rfc|max:255']);
        $key = 'mail-test:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['testRecipient' => 'Espera un minuto antes de enviar otra prueba.']);
        }
        RateLimiter::hit($key, 60);
        if (app(ContactMail::class)->sendTest($this->testRecipient)) {
            Notification::make()->title('Prueba aceptada por el servidor de correo')->body('Comprueba el buzón de destino.')->success()->send();
        } else {
            Notification::make()->title('No se pudo enviar la prueba')->body(app(ContactMail::class)->readiness(MailSetting::current())['ready'] ? 'Sin aceptación confirmada. Revisa los datos del proveedor y la conectividad con el responsable del sitio; indica la hora de la prueba sin compartir contraseñas.' : app(ContactMail::class)->readiness(MailSetting::current())['issue'])->danger()->send();
        }
    }
}
