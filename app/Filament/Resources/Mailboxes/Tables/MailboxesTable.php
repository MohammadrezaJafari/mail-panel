<?php

namespace App\Filament\Resources\Mailboxes\Tables;

use App\Enums\MailboxStatus;
use App\Filament\Support\ProviderErrors;
use App\Models\Mailbox;
use App\Services\MailboxService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class MailboxesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('address')->searchable()->sortable()->weight('bold')->copyable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('domain.name')->label('Domain')->sortable()->toggleable(),
                TextColumn::make('status')->badge(),
                IconColumn::make('is_shared')->label('Shared')->boolean()->toggleable(),
                TextColumn::make('quota_mb')->label('Quota')->formatStateUsing(fn ($state) => Number::fileSize($state * 1048576))->sortable(),
                TextColumn::make('used_bytes')->label('Used')
                    ->formatStateUsing(fn ($state, Mailbox $record) => Number::fileSize($state).' ('.$record->usagePercent().'%)')
                    ->color(fn (Mailbox $record) => $record->usagePercent() > 90 ? 'danger' : ($record->usagePercent() > 75 ? 'warning' : null))
                    ->sortable(),
                TextColumn::make('aliases_count')->counts('aliases')->label('Aliases')->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(MailboxStatus::class),
                SelectFilter::make('domain')->relationship('domain', 'name')->searchable()->preload(),
                TernaryFilter::make('is_shared')->label('Shared'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    Action::make('resetPassword')
                        ->label('Reset password')
                        ->icon('heroicon-o-key')
                        ->schema([
                            TextInput::make('password')
                                ->password()->revealable()->required()->minLength(10)
                                ->default(fn () => Str::password(16, symbols: false))
                                ->helperText('Share this password with the user through a secure channel.'),
                        ])
                        ->action(function (Mailbox $record, array $data) {
                            ProviderErrors::guard(fn () => app(MailboxService::class)->resetPassword($record, $data['password']));
                            Notification::make()->title('Password reset for '.$record->address)->success()->send();
                        }),
                    Action::make('changeQuota')
                        ->label('Change quota')
                        ->icon('heroicon-o-circle-stack')
                        ->schema([
                            TextInput::make('quota_mb')
                                ->label('Quota')->numeric()->minValue(1)->suffix('MB')->required()
                                ->default(fn (Mailbox $record) => $record->quota_mb)
                                ->maxValue(fn (Mailbox $record) => $record->domain->max_quota_mb)
                                ->helperText(fn (Mailbox $record) => "Domain maximum: {$record->domain->max_quota_mb} MB"),
                        ])
                        ->action(fn (Mailbox $record, array $data) => ProviderErrors::guard(fn () => app(MailboxService::class)->setQuota($record, (int) $data['quota_mb']))),
                    Action::make('suspend')
                        ->label('Suspend')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (Mailbox $record) => $record->isActive())
                        ->action(fn (Mailbox $record) => ProviderErrors::guard(fn () => app(MailboxService::class)->suspend($record))),
                    Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->visible(fn (Mailbox $record) => ! $record->isActive())
                        ->action(fn (Mailbox $record) => ProviderErrors::guard(fn () => app(MailboxService::class)->activate($record))),
                    Action::make('syncUsage')
                        ->label('Refresh usage')
                        ->icon('heroicon-o-arrow-path')
                        ->action(fn (Mailbox $record) => ProviderErrors::guard(fn () => app(MailboxService::class)->syncUsage($record))),
                    DeleteAction::make()
                        ->using(fn (Mailbox $record) => ProviderErrors::guard(fn () => app(MailboxService::class)->delete($record))),
                ]),
            ])
            ->defaultSort('address');
    }
}
