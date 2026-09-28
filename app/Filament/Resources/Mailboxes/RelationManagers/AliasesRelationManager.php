<?php

namespace App\Filament\Resources\Mailboxes\RelationManagers;

use App\Filament\Support\ProviderErrors;
use App\Models\Alias;
use App\Models\Mailbox;
use App\Services\AliasService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AliasesRelationManager extends RelationManager
{
    protected static string $relationship = 'aliases';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('address')->copyable(),
                TextColumn::make('goto')->label('Delivers to')->listWithLineBreaks(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('created_at')->dateTime()->since(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Add alias')
                    ->icon('heroicon-o-plus')
                    ->schema([
                        TextInput::make('local_part')
                            ->label('Alias address')
                            ->required()
                            ->regex('/^[a-z0-9._+-]+$/i')
                            ->suffix('@'.$this->getOwnerRecord()->domain->name),
                    ])
                    ->action(function (array $data) {
                        /** @var Mailbox $mailbox */
                        $mailbox = $this->getOwnerRecord();
                        ProviderErrors::guard(fn () => app(AliasService::class)->create(
                            $mailbox->domain,
                            ['address' => strtolower($data['local_part']).'@'.$mailbox->domain->name],
                            $mailbox,
                        ));
                    }),
            ])
            ->recordActions([
                DeleteAction::make()->using(fn (Alias $record) => ProviderErrors::guard(fn () => app(AliasService::class)->delete($record))),
            ]);
    }
}
