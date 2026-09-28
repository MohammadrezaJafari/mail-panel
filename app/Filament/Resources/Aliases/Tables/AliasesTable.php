<?php

namespace App\Filament\Resources\Aliases\Tables;

use App\Filament\Support\ProviderErrors;
use App\Models\Alias;
use App\Services\AliasService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AliasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('address')->searchable()->sortable()->weight('bold')->copyable(),
                TextColumn::make('goto')->label('Delivers to')->listWithLineBreaks()->limitList(3)->searchable(),
                TextColumn::make('domain.name')->label('Domain')->sortable()->toggleable(),
                TextColumn::make('mailbox.address')->label('Owner mailbox')->placeholder('—')->toggleable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('domain')->relationship('domain', 'name')->searchable()->preload(),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->using(fn (Alias $record) => ProviderErrors::guard(fn () => app(AliasService::class)->delete($record))),
            ])
            ->defaultSort('address');
    }
}
