<?php

namespace App\Filament\Resources\Organizations\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('domains_count')->counts('domains')->label('Domains')->sortable(),
                TextColumn::make('mailboxes_count')->counts('mailboxes')->label('Mailboxes')->sortable(),
                TextColumn::make('aliases_count')->counts('aliases')->label('Aliases')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended']),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('name');
    }
}
