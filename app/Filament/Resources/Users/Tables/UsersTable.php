<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable()->copyable(),
                TextColumn::make('role')->badge(),
                TextColumn::make('organization.name')->label('Organization')->placeholder('—')->toggleable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('last_login_at')->since()->placeholder('never')->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->options(UserRole::class),
                SelectFilter::make('organization')->relationship('organization', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('name');
    }
}
