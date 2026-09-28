<?php

namespace App\Filament\Resources\Domains\RelationManagers;

use App\Filament\Resources\Aliases\AliasResource;
use App\Filament\Resources\Aliases\Tables\AliasesTable;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class AliasesRelationManager extends RelationManager
{
    protected static string $relationship = 'aliases';

    public function table(Table $table): Table
    {
        return AliasesTable::configure($table)
            ->headerActions([
                Action::make('create')
                    ->label('New alias')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => AliasResource::getUrl('create', ['domain' => $this->getOwnerRecord()->getKey()])),
            ]);
    }
}
