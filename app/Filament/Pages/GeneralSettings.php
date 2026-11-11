<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\MediaPicker;
use App\Models\GeneralSetting;
use App\Models\User;
use App\Services\ApplicationCache;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GeneralSettings extends Page
{
    protected static ?string $title = 'Configuración general';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración y Sistema';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.general-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', GeneralSetting::class);
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', GeneralSetting::class);
        $this->form->fill(GeneralSetting::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->statePath('data')->model(GeneralSetting::current())->components([
            Section::make('Identidad')->schema([
                TextInput::make('company_name')->label('Nombre de la empresa')->required()->maxLength(255),
                Textarea::make('description')->label('Descripción')->maxLength(2000),
                $this->imageField('logo_media_id', 'Logo'),
                $this->imageField('logo_dark_media_id', 'Logo para fondo oscuro'),
                $this->imageField('favicon_media_id', 'Favicon')->imageContext('favicon')
                    ->helperText('Imagen PNG, máximo 256 KB.'),
            ])->columns(['default' => 1, 'md' => 2]),
            Section::make('Contacto')->schema([
                TextInput::make('email')->label('Correo público')->email()->maxLength(255),
                TextInput::make('phone')->label('Teléfono')->tel()->maxLength(40),
                TextInput::make('whatsapp')->label('WhatsApp')->maxLength(20)
                    ->regex('/^\\+?[1-9][0-9]{6,14}$/')->helperText('Número internacional con código de país.'),
                Textarea::make('address')->label('Dirección')->maxLength(1000),
                Repeater::make('social_links')->label('Redes sociales')->schema([
                    TextInput::make('label')->label('Nombre')->required()->maxLength(80),
                    TextInput::make('url')->label('URL')->required()->url()->maxLength(255)->rules(['url:http,https']),
                ])->columns(['default' => 1, 'md' => 2])->maxItems(10)->defaultItems(0)->columnSpanFull(),
            ])->columns(['default' => 1, 'md' => 2]),
            Section::make('Preferencias')->schema([
                Select::make('locale')->label('Idioma')->options(['es' => 'Español', 'en' => 'English'])
                    ->required()->rule(Rule::in(['es', 'en'])),
                Select::make('timezone')->label('Zona horaria')->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                    ->searchable()->required()->rules(['timezone']),
                Toggle::make('maintenance_enabled')->label('Sitio público en mantenimiento')->required()
                    ->helperText('El panel permanece disponible. El sitio público responderá con estado 503.'),
            ])->columns(['default' => 1, 'md' => 2]),
        ]);
    }

    public function save(): void
    {
        $settings = GeneralSetting::current();
        $user = auth()->id() ? User::query()->find(auth()->id()) : null;
        abort_unless($user, 403);
        Gate::forUser($user)->authorize('update', $settings);
        $state = $this->form->getState();
        foreach (['logo', 'logo_dark', 'favicon'] as $attribute) {
            if ($settings->getAttribute($attribute.'_media_id') && empty($state[$attribute.'_media_id'])) {
                $settings->setAttribute($attribute, null);
            }
        }
        $settings->fill($state);
        $settings->id = 1;
        $settings->save();
        $this->form->model($settings)->fill($settings->attributesToArray());
        Notification::make()->title('Configuración guardada')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('clearApplicationCache')->label('Limpiar caché del sitio')
            ->requiresConfirmation()->modalDescription('Solo se limpia la configuración pública y la revisión de privacidad. No se eliminan sesiones ni límites de envío.')
            ->visible(fn (): bool => Gate::allows('update', GeneralSetting::current(false)))
            ->action(fn () => $this->clearApplicationCache())];
    }

    public function clearApplicationCache(): void
    {
        Gate::authorize('update', GeneralSetting::current(false));
        ApplicationCache::clear();
        Notification::make()->title('Caché del sitio limpiada')->success()->send();
    }

    protected function imageField(string $name, string $label): MediaPicker
    {
        return MediaPicker::make($name)->label($label)->imageContext('logo')
            ->disabled(fn (): bool => ! GeneralSetting::hasMediaReferences())
            ->dehydrated(fn (): bool => GeneralSetting::hasMediaReferences())
            ->helperText(fn (): string => GeneralSetting::hasMediaReferences()
                ? 'Elige desde la biblioteca. PNG, JPG o WebP, máximo 2 MB. Las rutas antiguas se conservan hasta elegir una imagen.'
                : 'La selección requiere la migración de identidad multimedia. La configuración y las rutas anteriores se conservan.');
    }
}
