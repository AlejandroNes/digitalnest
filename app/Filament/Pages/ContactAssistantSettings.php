<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\MediaPicker;
use App\Models\ContactAssistantSetting;
use App\Models\User;
use App\Rules\InternationalPhone;
use App\Services\ContactAssistant;
use App\Services\ContactAssistantFields;
use App\Services\InternalUrl;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class ContactAssistantSettings extends Page
{
    protected static ?string $title = 'Asistente de Contacto';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|\UnitEnum|null $navigationGroup = 'Comunicación';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.contact-assistant-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    #[Locked]
    public string $previewHtml = '';

    #[Locked]
    public int $previewRevision = 0;

    #[Locked]
    public string $settingsRevision = '';

    public static function canAccess(): bool
    {
        $user = auth()->id() ? User::query()->find(auth()->id()) : null;

        return $user && Gate::forUser($user)->allows('viewAny', ContactAssistantSetting::class);
    }

    public function mount(): void
    {
        $this->authorizeViewer();
        $settings = ContactAssistantSetting::current();
        $this->fillSettings($settings);
    }

    public function form(Schema $schema): Schema
    {
        $plainText = 'not_regex:/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u';
        $user = $this->authorizeViewer();
        $settings = ContactAssistantSetting::current();
        $canUpdate = Gate::forUser($user)->allows('update', $settings);
        $conversationReady = ContactAssistantSetting::supportsConversation();

        return $schema->columns(1)->statePath('data')->model($settings)->components([
            Section::make('Estado y destino')
                ->description('Activa el chat y define dónde recibirás las consultas.')
                ->disabled(! $canUpdate)
                ->schema([
                    Toggle::make('enabled')->label('Activar asistente')->required()->live(),
                    TextInput::make('destination_whatsapp')->label('WhatsApp de destino')->type('tel')->inputMode('tel')->maxLength(40)->rules([new InternationalPhone])
                        ->required(fn (Get $get): bool => (bool) $get('enabled'))
                        ->helperText('Incluye + o 00 y el código de país, por ejemplo +591 77777777.'),
                ])->columns(['default' => 1, 'md' => 2]),
            Section::make('Identidad y mensajes')
                ->description('Personaliza cómo se presenta el asistente y los mensajes de inicio y cierre.')
                ->disabled(! $canUpdate)
                ->schema([
                    TextInput::make('name')->label('Nombre visible')->required()->maxLength(100)->rules([$plainText, 'not_regex:/[\r\n]/']),
                    TextInput::make('button_label')->label('Etiqueta del botón de WhatsApp')->required()->maxLength(100)->rules([$plainText, 'not_regex:/[\r\n]/'])
                        ->helperText('Se muestra al apuntar al botón y al usar un lector de pantalla.'),
                    MediaPicker::make('avatar_media_id')->label('Foto del asistente')->imageContext('assistant_avatar')
                        ->disabled(! $conversationReady)
                        ->dehydrated($conversationReady)
                        ->helperText('PNG, JPG o WebP, máximo 2 MB. Mantén el rostro centrado.')->columnSpanFull(),
                    Textarea::make('welcome_message')->label('Bienvenida')->rows(2)->required()->maxLength(1000)->rules([$plainText]),
                    Textarea::make('form_intro')->label('Introducción de la conversación')->rows(2)->required()->maxLength(1000)->rules([$plainText]),
                    Textarea::make('final_message')->label('Mensaje final')->rows(2)->required()->maxLength(1000)->rules([$plainText])->columnSpanFull(),
                ])->columns(['default' => 1, 'md' => 2]),
            Section::make('Preguntas y orden')
                ->description('Arrastra las preguntas o usa las flechas. Nombre y WhatsApp son obligatorios. El consentimiento y el resumen siempre van al final.')
                ->disabled(! $canUpdate)
                ->schema([
                    Toggle::make('email_required')->label('Correo electrónico obligatorio')->required()->helperText('Activa siempre la pregunta de correo. Si es opcional, puedes ocultarla.'),
                    Toggle::make('services_enabled')->label('Mostrar selector de servicios')->required()->helperText('Muestra los servicios activos del catálogo. La elección es opcional.'),
                    Repeater::make('field_schema')->label('Campos del chat')->maxItems(20)->minItems(5)
                        ->disabled(! $conversationReady)
                        ->defaultItems(0)->addActionLabel('Añadir pregunta')->reorderableWithButtons()->collapsible()->collapsed()
                        ->collapseAllAction(fn (Action $action): Action => $action->label('Cerrar todas'))
                        ->expandAllAction(fn (Action $action): Action => $action->label('Abrir todas'))
                        ->collapseAction(fn (Action $action): Action => $action->label('Cerrar pregunta'))
                        ->expandAction(fn (Action $action): Action => $action->label('Editar pregunta'))
                        ->moveUpAction(fn (Action $action): Action => $action->label('Mover arriba'))
                        ->moveDownAction(fn (Action $action): Action => $action->label('Mover abajo'))
                        ->reorderAction(fn (Action $action): Action => $action->label('Arrastrar para ordenar'))
                        ->itemLabel(fn (array $state): string => is_string($state['label'] ?? null) ? $state['label'] : 'Nueva pregunta')
                        ->dehydrated($conversationReady)
                        ->deleteAction(fn (Action $action): Action => $action->label('Eliminar pregunta')->disabled(fn (array $arguments, Repeater $component): bool => isset(ContactAssistantFields::BUILTINS[$component->getRawItemState($arguments['item'])['id'] ?? ''])))
                        ->schema([
                            Hidden::make('id')->default(fn (): string => 'f_'.Str::lower(Str::random(16))),
                            Select::make('type')->label('Tipo')->options(fn (Get $get): array => $get('id') === 'service_id'
                                ? ['service' => 'Servicios del catálogo'] : ContactAssistantFields::TYPES)
                                ->default('text')->required()->live()
                                ->afterStateUpdated(function (Set $set, string $state): void {
                                    $set('max_length', $state === 'email' ? 255 : 1000);
                                    $set('min_length', 0);
                                    foreach (['min_value', 'max_value', 'min_date', 'max_date'] as $attribute) {
                                        $set($attribute, null);
                                    }
                                })
                                ->disabled(fn (Get $get): bool => isset(ContactAssistantFields::BUILTINS[$get('id') ?? '']))->dehydrated(),
                            TextInput::make('label')->label('Etiqueta para el resumen')->required()->maxLength(100)->live(onBlur: true)->rules([$plainText, 'not_regex:/[\r\n]/']),
                            TextInput::make('question')->label('Pregunta del asistente')->required()->maxLength(200)->rules([$plainText]),
                            Textarea::make('help')->label('Ayuda (opcional)')->maxLength(300)->rules([$plainText])->columnSpanFull(),
                            Toggle::make('enabled')->label('Mostrar pregunta')->default(true)->required()
                                ->disabled(fn (Get $get): bool => in_array($get('id'), ['name', 'whatsapp', 'service_id'], true))->dehydrated(),
                            Toggle::make('required')->label('Respuesta obligatoria')->default(false)->required()
                                ->disabled(fn (Get $get): bool => in_array($get('id'), ['name', 'whatsapp', 'email', 'service_id'], true))->dehydrated()
                                ->helperText(fn (Get $get): ?string => $get('id') === 'email' ? 'Usa la opción «Correo electrónico obligatorio» de esta sección.' : null),
                            TextInput::make('min_length')->label('Mínimo de caracteres')->numeric()->minValue(fn (Get $get): int => $get('id') === 'name' ? 2 : 0)->maxValue(1000)->default(0)
                                ->visible(fn (Get $get): bool => in_array($get('type'), ['text', 'textarea'], true)),
                            TextInput::make('max_length')->label('Máximo de caracteres')->numeric()->minValue(1)->maxValue(fn (Get $get): int => $get('id') === 'name' ? 150 : ($get('type') === 'email' ? 255 : 1000))->default(1000)
                                ->visible(fn (Get $get): bool => in_array($get('type'), ['text', 'textarea', 'email'], true)),
                            TextInput::make('min_value')->label('Número mínimo')->numeric()->minValue(-100000000000)->maxValue(100000000000)
                                ->visible(fn (Get $get): bool => $get('type') === 'number'),
                            TextInput::make('max_value')->label('Número máximo')->numeric()->minValue(-100000000000)->maxValue(100000000000)
                                ->visible(fn (Get $get): bool => $get('type') === 'number'),
                            DatePicker::make('min_date')->label('Fecha mínima')->native()->visible(fn (Get $get): bool => $get('type') === 'date'),
                            DatePicker::make('max_date')->label('Fecha máxima')->native()->visible(fn (Get $get): bool => $get('type') === 'date'),
                            Repeater::make('options')->label('Opciones')->defaultItems(0)->minItems(1)->maxItems(20)->addActionLabel('Añadir opción')
                                ->deleteAction(fn (Action $action): Action => $action->label('Eliminar opción'))
                                ->reorderAction(fn (Action $action): Action => $action->label('Ordenar opciones'))
                                ->visible(fn (Get $get): bool => in_array($get('type'), ['choice', 'multiple'], true))
                                ->schema([
                                    Hidden::make('value')->default(fn (): string => 'o_'.Str::lower(Str::random(12))),
                                    TextInput::make('label')->label('Texto del botón')->required()->maxLength(100)->rules([$plainText, 'not_regex:/[\r\n]/']),
                                ])->columnSpanFull(),
                        ])->columns(['default' => 1, 'md' => 2])->columnSpanFull(),
                ])->columns(['default' => 1, 'md' => 2]),
            Section::make('Privacidad y consentimiento')
                ->disabled(! $canUpdate)
                ->schema([
                    Textarea::make('consent_text')->label('Texto de consentimiento')->required()->maxLength(1000)->rules([$plainText])
                        ->helperText('La casilla será obligatoria y sin marcar inicialmente.'),
                    TextInput::make('privacy_path')->label('Enlace a la Política de Privacidad')->maxLength(255)
                        ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (! InternalUrl::path($value) || InternalUrl::protectedPath($value)) {
                                $fail('Utiliza una ruta pública interna, por ejemplo /legal/privacy.');
                            }
                        }])->helperText('Ruta interna opcional. /legal/privacy se muestra solo si la Política de Privacidad está publicada.'),
                ])->columns(['default' => 1, 'md' => 2]),
        ]);
    }

    public function save(): void
    {
        $user = auth()->id() ? User::query()->find(auth()->id()) : null;
        abort_unless($user, 403);
        Gate::forUser($user)->authorize('viewAny', ContactAssistantSetting::class);
        $settings = ContactAssistantSetting::current();
        Gate::forUser($user)->authorize('update', $settings);
        $data = $this->form->getState();
        if (ContactAssistantSetting::supportsConversation()) {
            $data['field_schema'] = $this->validatedFields($data['field_schema'] ?? []);
        }
        $data['destination_whatsapp'] = InternationalPhone::normalize($data['destination_whatsapp'] ?? null);
        $settings = DB::transaction(function () use ($data, $user): ContactAssistantSetting {
            $actor = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('viewAny', ContactAssistantSetting::class);
            $current = ContactAssistantSetting::query()->lockForUpdate()->find(1) ?? new ContactAssistantSetting;
            Gate::forUser($actor)->authorize('update', $current);
            if (! hash_equals($this->settingsRevision, $this->revision($current))) {
                throw ValidationException::withMessages(['data' => 'La configuración cambió en otra sesión. Recarga los ajustes guardados antes de volver a guardar.']);
            }
            $this->prepareAvatar($data['avatar_media_id'] ?? null);
            $current->fill($data);
            $current->id = 1;
            $current->save();

            return $current;
        }, 3);
        $this->form->model($settings);
        $this->fillSettings($settings);
        $this->previewHtml = '';
        Notification::make()->title('Configuración guardada')->success()->send();
    }

    /** @param array<mixed> $fields
     * @return list<array<string, mixed>>
     */
    private function validatedFields(array $fields): array
    {
        try {
            return ContactAssistantFields::validateConfiguration($fields);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['data.field_schema' => array_merge(...array_values($exception->errors()))]);
        }
    }

    private function fillSettings(ContactAssistantSetting $settings): void
    {
        $this->settingsRevision = $this->revision($settings);
        $this->form->fill([
            ...$settings->attributesToArray(), 'destination_whatsapp' => $settings->destination_whatsapp,
            'field_schema' => ContactAssistantFields::fields($settings, false),
        ]);
    }

    private function revision(ContactAssistantSetting $settings): string
    {
        return hash('sha256', json_encode([$settings->exists, $settings->getAttributes(), ContactAssistantSetting::supportsConversation()], JSON_THROW_ON_ERROR));
    }

    private function authorizeViewer(): User
    {
        $user = auth()->id() ? User::query()->find(auth()->id()) : null;
        abort_unless($user, 403);
        Gate::forUser($user)->authorize('viewAny', ContactAssistantSetting::class);

        return $user;
    }

    public function reloadSettings(): void
    {
        $this->authorizeViewer();
        $this->fillSettings(ContactAssistantSetting::current());
        $this->previewHtml = '';
        $this->resetValidation();
    }

    public function closePreview(): void
    {
        $this->authorizeViewer();
        $this->previewHtml = '';
    }

    protected function getViewData(): array
    {
        $user = $this->authorizeViewer();
        $settings = ContactAssistantSetting::current();

        return [
            'canUpdate' => Gate::forUser($user)->allows('update', $settings),
            'conversationReady' => ContactAssistantSetting::supportsConversation(),
            'isPubliclyAvailable' => $settings->enabled && InternationalPhone::normalize($settings->destination_whatsapp)
                && ($settings->field_schema === null || ContactAssistantSetting::supportsConversation()),
            'activeQuestions' => count(ContactAssistantFields::fields($settings)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('previewChat')->label('Simular conversación')->icon(Heroicon::OutlinedPlay)
            ->action(fn () => $this->preview())];
    }

    public function preview(): void
    {
        $user = auth()->id() ? User::query()->find(auth()->id()) : null;
        abort_unless($user, 403);
        Gate::forUser($user)->authorize('viewAny', ContactAssistantSetting::class);
        $settings = ContactAssistantSetting::current();
        if (Gate::forUser($user)->allows('update', $settings)) {
            $data = $this->form->getState();
            if (ContactAssistantSetting::supportsConversation()) {
                $data['field_schema'] = $this->validatedFields($data['field_schema'] ?? []);
                $this->prepareAvatar($data['avatar_media_id'] ?? null);
            }
            $settings = new ContactAssistantSetting($data);
        }
        $configuration = app(ContactAssistant::class)->configuration($settings, preview: true);
        $this->previewRevision++;
        $this->previewHtml = view('filament.pages.contact-assistant-preview', compact('configuration'))->render();
    }

    private function prepareAvatar(mixed $id): void
    {
        if (! $id) {
            return;
        }
        try {
            $media = Media::query()->findOrFail($id);
            $media->setCustomProperty('contact_assistant_avatar', true)->save();
            app(FileManipulator::class)->createDerivedFiles($media, ['contact-avatar']);
            if (! $media->fresh()->hasGeneratedConversion('contact-avatar')) {
                throw new \RuntimeException('Avatar conversion unavailable');
            }
        } catch (Throwable $exception) {
            Log::warning('contact_assistant.avatar_failed', ['exception' => $exception::class]);
            throw ValidationException::withMessages(['data.avatar_media_id' => 'No pudimos preparar la foto. Revisa la imagen de la biblioteca.']);
        }
    }
}
