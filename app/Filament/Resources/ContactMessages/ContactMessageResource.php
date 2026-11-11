<?php

namespace App\Filament\Resources\ContactMessages;

use App\ContactMessageStatus;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactMessage;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $modelLabel = 'mensaje de contacto';

    protected static ?string $pluralModelLabel = 'Mensajes de contacto';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|\UnitEnum|null $navigationGroup = 'Comunicación';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        if (! Gate::allows('viewAny', ContactMessage::class)) {
            return null;
        }
        $count = ContactMessage::query()->pending()->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Mensajes pendientes de responder';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            TextEntry::make('source')->label('Origen')->formatStateUsing(fn (string $state): string => ContactMessage::sourceLabels()[$state] ?? $state)->badge(),
            TextEntry::make('service_name')->label('Servicio de interés')->placeholder('Sin servicio'),
            TextEntry::make('whatsapp_status')->label('WhatsApp')->formatStateUsing(fn (): string => 'Enlace preparado; apertura y recepción sin confirmar')->placeholder('No aplica'),
            TextEntry::make('consented_at')->label('Consentimiento recibido')->dateTime()->placeholder('No registrado'),
            TextEntry::make('consent_text')->label('Texto aceptado')->placeholder('No registrado'),
            TextEntry::make('privacy_path')->label('Política al enviar')->placeholder('Sin enlace'),
            TextEntry::make('privacy_revision')->label('Revisión de privacidad')->placeholder('No registrada'),
            TextEntry::make('name')->label('Nombre completo'),
            TextEntry::make('email')->label('Correo electrónico')->placeholder('Sin correo'),
            TextEntry::make('phone')->label('Teléfono / WhatsApp')->placeholder('Sin teléfono'),
            TextEntry::make('subject')->label('Asunto')->placeholder('Sin asunto'),
            TextEntry::make('message')->label('Mensaje')->columnSpanFull()->extraAttributes(['style' => 'white-space:pre-wrap;overflow-wrap:anywhere;']),
            RepeatableEntry::make('assistant_answers')->label('Preguntas y respuestas al enviar')
                ->schema([TextEntry::make('question')->label('Pregunta'), TextEntry::make('display_value')->label('Respuesta')->placeholder('Omitido')->extraAttributes(['style' => 'white-space:pre-wrap;overflow-wrap:anywhere;'])])
                ->visible(fn (ContactMessage $record): bool => filled($record->assistant_answers))->columnSpanFull(),
            TextEntry::make('status')->label('Lectura')->badge(),
            TextEntry::make('notification')->label('Notificación por correo')->state(fn (ContactMessage $record): string => $record->notificationLabel())->badge(),
            TextEntry::make('notification_attempted_at')->label('Intento de notificación')->dateTime()->placeholder('Sin intento registrado'),
            TextEntry::make('notified_at')->label('Aceptación por el servidor')->dateTime()->placeholder('Sin aceptación confirmada')->helperText('La aceptación no confirma recepción en el buzón. Lectura no significa respuesta al visitante.'),
            TextEntry::make('created_at')->label('Recibido')->dateTime(),
        ]);
    }

    public static function statusAction(): Action
    {
        return Action::make('changeStatus')->label(fn (ContactMessage $record): string => $record->status === ContactMessageStatus::Pending ? 'Marcar como leído' : 'Marcar como pendiente')
            ->visible(fn (ContactMessage $record): bool => Gate::allows('update', $record))
            ->action(function (ContactMessage $record): void {
                $user = User::query()->find(auth()->id());
                abort_unless($user, 403);
                Gate::forUser($user)->authorize('update', $record);
                $record->markAs($record->status === ContactMessageStatus::Pending ? ContactMessageStatus::Read : ContactMessageStatus::Pending);
            });
    }

    public static function table(Table $table): Table
    {
        return $table->stackedOnMobile()->columns([
            TextColumn::make('source')->label('Origen')->formatStateUsing(fn (string $state): string => ContactMessage::sourceLabels()[$state] ?? $state)->wrap(),
            TextColumn::make('service_name')->label('Servicio')->toggleable(isToggledHiddenByDefault: true)->wrap(),
            TextColumn::make('name')->wrap()->label('Nombre')->searchable(),
            TextColumn::make('email')->wrap()->label('Correo')->searchable(),
            TextColumn::make('subject')->wrap()->label('Asunto')->searchable()->limit(60),
            TextColumn::make('status')->wrap()->label('Lectura')->badge(),
            TextColumn::make('notification')->label('Notificación')->state(fn (ContactMessage $record): string => $record->notificationLabel())->wrap(),
            TextColumn::make('created_at')->wrap()->label('Recibido')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('source')->label('Origen')->options(ContactMessage::sourceLabels()),
            SelectFilter::make('status')->label('Estado')->options(ContactMessageStatus::class),
            SelectFilter::make('notification')->label('Notificación por correo')->options(ContactMessage::notificationLabels())
                ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    'accepted' => $query->whereNotNull('notified_at'),
                    'unconfirmed' => $query->whereNotNull('notification_attempted_at')->whereNull('notified_at'),
                    'unattempted' => $query->whereNull('notification_attempted_at')->whereNull('notified_at'),
                    default => $query,
                }),
            Filter::make('received')->label('Fecha de recepción')->schema([
                DatePicker::make('from')->label('Desde'),
                DatePicker::make('until')->label('Hasta')->afterOrEqual('from'),
            ])->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
        ])->recordActions([ViewAction::make(), static::statusAction(), DeleteAction::make()])->defaultSort('created_at', 'desc')->paginationPageOptions([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return ['index' => ListContactMessages::route('/'), 'view' => ViewContactMessage::route('/{record}')];
    }
}
