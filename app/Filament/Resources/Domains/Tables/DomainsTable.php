<?php

namespace App\Filament\Resources\Domains\Tables;

use App\Enums\DomainStatus;
use App\Filament\Support\ProviderErrors;
use App\Models\Domain;
use App\Services\DomainService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DomainsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('organization.name')->label('Organization')->sortable()->toggleable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('mailboxes_count')->counts('mailboxes')->label('Mailboxes')->sortable(),
                TextColumn::make('aliases_count')->counts('aliases')->label('Aliases')->sortable(),
                TextColumn::make('dns')
                    ->label('DNS')
                    ->state(fn (Domain $r) => $r->dns_status ? collect($r->dns_status)->map(fn ($v, $k) => strtoupper($k).' '.match ($v['status'] ?? '') {
                        'ok' => '✓', 'warning' => '⚠', 'missing' => '✗', default => '?'
                    })->implode('  ') : '—')
                    ->tooltip(fn (Domain $r) => $r->dns_checked_at ? 'Checked '.$r->dns_checked_at->diffForHumans() : null),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(DomainStatus::class),
                SelectFilter::make('organization')->relationship('organization', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('verifyDns')
                    ->label('Verify DNS')
                    ->icon('heroicon-o-shield-check')
                    ->action(function (Domain $record) {
                        $status = ProviderErrors::guard(fn () => app(DomainService::class)->verifyDns($record));
                        $summary = collect($status)->map(fn ($v, $k) => strtoupper($k).': '.$v['status'])->implode(', ');
                        Notification::make()->title('DNS checked')->body($summary)->info()->send();
                    }),
                Action::make('toggle')
                    ->label(fn (Domain $r) => $r->isActive() ? 'Disable' : 'Activate')
                    ->icon(fn (Domain $r) => $r->isActive() ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color(fn (Domain $r) => $r->isActive() ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Domain $record) => ProviderErrors::guard(fn () => app(DomainService::class)->setStatus(
                        $record, $record->isActive() ? DomainStatus::Disabled : DomainStatus::Active,
                    ))),
                DeleteAction::make()
                    ->using(fn (Domain $record) => ProviderErrors::guard(fn () => app(DomainService::class)->delete($record))),
            ])
            ->defaultSort('name');
    }
}
