<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable()->since()->tooltip(fn (AuditLog $r) => $r->created_at->toDateTimeString()),
                TextColumn::make('action')->badge()->searchable()
                    ->color(fn ($state) => match (true) {
                        str_ends_with($state, 'deleted'), str_ends_with($state, 'suspended'), str_ends_with($state, 'failed') => 'danger',
                        str_ends_with($state, 'created'), str_ends_with($state, 'activated') => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('subject_label')->label('Subject')->searchable()->placeholder('—'),
                TextColumn::make('actor.email')->label('Actor')->placeholder('system')->searchable(),
                TextColumn::make('organization.name')->label('Organization')->toggleable(),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')),
                SelectFilter::make('actor')->relationship('actor', 'email')->searchable(),
                Filter::make('today')->query(fn (Builder $q) => $q->whereDate('created_at', today())),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }
}
