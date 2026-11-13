<?php

namespace App\Filament\Resources\Rankings\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ToolsRelationManager extends RelationManager
{
    protected static string $relationship = 'tools';

    protected static ?string $title = 'Apps del ranking';

    protected static ?string $modelLabel = 'app';

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::pivotFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('position')->label('#'),
                TextColumn::make('name')->label('App')->weight('medium'),
                TextColumn::make('best_for')->label('Mejor para')->placeholder('—'),
                TextColumn::make('verdict')->label('Veredicto')->placeholder('—')->wrap(),
            ])
            ->headerActions([
                AttachAction::make()->label('Añadir app')->preloadRecordSelect()
                    ->authorize(fn (): bool => $this->canManageRanking())
                    ->schema(fn (AttachAction $action): array => [$action->getRecordSelect(), ...self::pivotFields()]),
            ])
            ->recordActions([
                EditAction::make()->authorize(fn (): bool => $this->canManageRanking()),
                DetachAction::make()->label('Quitar')->authorize(fn (): bool => $this->canManageRanking()),
            ]);
    }

    /**
     * Ranking entries are pivot data, so they follow the ranking's update permission instead of the tool policy.
     */
    protected function canReorder(): bool
    {
        return $this->canManageRanking();
    }

    private function canManageRanking(): bool
    {
        return ! $this->isReadOnly() && Gate::allows('update', $this->getOwnerRecord());
    }

    /**
     * @return array<int, TextInput>
     */
    private static function pivotFields(): array
    {
        return [
            TextInput::make('position')->label('Posición')->integer()->minValue(1)->maxValue(999)->required(),
            TextInput::make('best_for')->label('Mejor para')->maxLength(120),
            TextInput::make('verdict')->label('Veredicto')->maxLength(160),
        ];
    }
}
