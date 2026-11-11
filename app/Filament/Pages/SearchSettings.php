<?php

namespace App\Filament\Pages;

use App\Filament\Support\ContentFields;
use App\Livewire\CustomScriptManager;
use App\Models\CustomScript;
use App\Models\SearchSetting;
use App\Models\User;
use App\Services\InternalUrl;
use App\Services\SiteIndexing;
use App\Services\Sitemap;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class SearchSettings extends Page
{
    protected static ?string $title = 'SEO, analítica y cookies';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|\UnitEnum|null $navigationGroup = 'SEO y Analítica';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.search-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', SearchSetting::class);
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', SearchSetting::class);
        $settings = SearchSetting::current();
        $this->form->fill([...$settings->attributesToArray(),
            'verification_enabled' => $settings->verification_enabled ?? ($settings->validVerificationToken() !== null),
            'tag_manager_enabled' => $settings->tag_manager_enabled ?? false,
            'sitemap_enabled' => $settings->sitemap_enabled ?? true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $disabled = fn (): bool => ! SearchSetting::supportsIntegrations() || ! Gate::allows('update', SearchSetting::current());

        return $schema->columns(1)->statePath('data')->model(SearchSetting::current())->components([
            Tabs::make('SEO')->contained(false)->persistTabInQueryString('seccion')->tabs([
                Tab::make('SEO general')->icon(Heroicon::OutlinedMagnifyingGlass)->schema([
                    Form::make([
                        Section::make('Configuración global')->schema([
                            TextInput::make('default_title')->label('Título por defecto')->maxLength(255),
                            Textarea::make('default_description')->label('Descripción por defecto')->maxLength(320),
                            ContentFields::image('og_image_id', 'ogImage')->label('Imagen Open Graph por defecto')->imageContext('seo'),
                            Toggle::make('indexable')->label('Permitir indexación')->required()
                                ->helperText('Actívalo al publicar. En desarrollo, staging o dominios locales se mantiene noindex aunque este interruptor esté activo.'),
                        ])->columns(['default' => 1, 'md' => 2])->disabled($disabled),
                    ])->livewireSubmitHandler('save')->footer([$this->saveButton('saveGeneral')]),
                ]),
                Tab::make('Integraciones y scripts')->icon(Heroicon::OutlinedCodeBracket)->schema([
                    Form::make([
                        Section::make('Google Search Console')->schema([
                            Toggle::make('verification_enabled')->label('Activar verificación')->required(),
                            TextInput::make('verification_token')->label('Código de verificación')->maxLength(255)
                                ->regex('/^[A-Za-z0-9_-]{1,255}$/D')->requiredIf('verification_enabled', true)
                                ->helperText('Search Console → Verificación de propiedad → Etiqueta HTML. Copia únicamente el valor content, sin pegar la etiqueta.'),
                        ])->columns(['default' => 1, 'md' => 2])->disabled($disabled),
                        Section::make('Google Tag Manager')->schema([
                            Toggle::make('tag_manager_enabled')->label('Activar Tag Manager')->required(),
                            TextInput::make('tag_manager_id')->label('ID del contenedor')->placeholder('GTM-XXXXXXX')->maxLength(32)
                                ->regex('/^GTM-[A-Z0-9]{4,20}$/D')->requiredIf('tag_manager_enabled', true)
                                ->helperText('Copia el ID de tu contenedor web en Tag Manager. Solo se carga tras aceptar analítica o publicidad. Configura también las comprobaciones de consentimiento de las etiquetas dentro de GTM.'),
                        ])->columns(['default' => 1, 'md' => 2])->disabled($disabled),
                        Section::make('Google Analytics 4')->schema([
                            Toggle::make('analytics_enabled')->label('Activar Analytics 4 directo')->required()
                                ->helperText('Con Tag Manager activo, configura GA4 dentro del contenedor: la carga directa se omite para evitar duplicados.'),
                            TextInput::make('measurement_id')->label('ID de medición')->maxLength(32)
                                ->regex('/^G-[A-Z0-9]{4,20}$/D')->requiredIf('analytics_enabled', true)->placeholder('G-XXXXXXXXXX')
                                ->helperText('Analytics → Administrar → Flujos de datos → Web → ID de medición. Requiere consentimiento estadístico.'),
                        ])->columns(['default' => 1, 'md' => 2])->disabled($disabled),
                        Section::make('Privacidad y consentimiento')->schema([
                            Toggle::make('cookies_enabled')->label('Mostrar preferencias de cookies')->required()
                                ->helperText('Si lo desactivas, tampoco se cargan las integraciones opcionales.'),
                            Textarea::make('cookie_message')->label('Mensaje del aviso')->maxLength(1000)
                                ->helperText('Texto plano. Explica analítica, publicidad y widgets utilizados en la Política de Privacidad. Los cambios renuevan el consentimiento.'),
                        ])->columns(['default' => 1, 'md' => 2])->disabled($disabled),
                    ])->livewireSubmitHandler('save')->footer([$this->saveButton('saveIntegrations')]),
                    Section::make('Código personalizado')->schema([
                        Livewire::make(CustomScriptManager::class)->key('seo-custom-scripts'),
                    ])->visible(fn (): bool => SearchSetting::supportsIntegrations() && Gate::allows('viewAny', CustomScript::class)),
                ]),
                Tab::make('Sitemap XML')->icon(Heroicon::OutlinedMap)->schema([
                    Form::make([
                        Section::make('Sitemap XML')->schema([
                            Toggle::make('sitemap_enabled')->label('Activar sitemap')->required()
                                ->helperText('Incluye las páginas públicas indexables. El portafolio aparece como una única página, sin URLs individuales de proyectos.'),
                        ])->disabled($disabled),
                    ])->livewireSubmitHandler('save')->footer([$this->saveButton('saveSitemap')]),
                    View::make('filament.pages.seo-crawl-status')->viewData(fn (): array => $this->crawlStatus('sitemap')),
                ]),
                Tab::make('Robots.txt')->icon(Heroicon::OutlinedDocumentText)->schema([
                    View::make('filament.pages.seo-crawl-status')->viewData(fn (): array => $this->crawlStatus('robots')),
                ]),
            ]),
        ]);
    }

    private function saveButton(string $name): Actions
    {
        return Actions::make([
            Action::make($name)->label('Guardar configuración')->submit('save')
                ->disabled(fn (): bool => ! SearchSetting::supportsIntegrations() || ! Gate::allows('update', SearchSetting::current())),
        ]);
    }

    /** @return array{kind: string, url: string, active: bool, message: string, updated: ?string, sitemapUrl: ?string} */
    public function crawlStatus(string $kind): array
    {
        $settings = SearchSetting::current();
        $indexing = SiteIndexing::allows($settings);
        $active = $kind === 'robots' || $settings->sitemap_enabled !== false;
        $message = ! SiteIndexing::environmentAllows() ? 'Este entorno o dominio no es público: el rastreo y la indexación están bloqueados.'
            : (! $indexing ? 'La indexación del sitio está desactivada o el sitio está en mantenimiento.' : 'El rastreo de las páginas públicas está permitido.');
        if ($kind === 'sitemap' && $settings->sitemap_enabled === false) {
            $message = 'El sitemap está desactivado. Robots.txt no publicará su referencia.';
        }
        $updated = $kind === 'sitemap' && $active && $indexing ? app(Sitemap::class)->manifest()['generated_at'] : null;

        return [
            'kind' => $kind, 'url' => InternalUrl::absolute($kind === 'sitemap' ? '/sitemap.xml' : '/robots.txt'),
            'active' => $active, 'message' => $message, 'updated' => $updated,
            'sitemapUrl' => $kind === 'robots' && $indexing && $settings->sitemap_enabled !== false ? InternalUrl::absolute('/sitemap.xml') : null,
        ];
    }

    public function save(): void
    {
        $settings = SearchSetting::current();
        $user = auth()->id() ? User::query()->find(auth()->id()) : null;
        abort_unless($user, 403);
        Gate::forUser($user)->authorize('update', $settings);
        if (! SearchSetting::supportsIntegrations()) {
            Notification::make()->title('La ampliación SEO requiere sus migraciones')->warning()->send();

            return;
        }
        $settings->fill($this->form->getState());
        $settings->id = 1;
        $settings->save();
        $this->form->model($settings)->fill($settings->attributesToArray());
        Notification::make()->title('Configuración guardada')->success()->send();
    }
}
