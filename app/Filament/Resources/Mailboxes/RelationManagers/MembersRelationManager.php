<?php

namespace App\Filament\Resources\Mailboxes\RelationManagers;

use App\Models\Mailbox;
use App\Models\MailboxMember;
use App\Models\User;
use App\Services\MailboxService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Users who may open this mailbox from the portal (owners + shared-mailbox members).
 */
class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Portal access';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('User'),
                TextColumn::make('user.email')->label('Login e-mail')->copyable(),
                TextColumn::make('role')->badge(),
                TextColumn::make('created_at')->since()->label('Added'),
            ])
            ->headerActions([
                Action::make('add')
                    ->label('Grant access')
                    ->icon('heroicon-o-user-plus')
                    ->schema([
                        Select::make('user_id')
                            ->label('User')
                            ->options(fn () => User::where('organization_id', $this->getOwnerRecord()->organization_id)->orderBy('name')->get()->pluck('email', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('role')
                            ->options([MailboxMember::ROLE_OWNER => 'Owner', MailboxMember::ROLE_MEMBER => 'Member'])
                            ->default(MailboxMember::ROLE_MEMBER)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        /** @var Mailbox $mailbox */
                        $mailbox = $this->getOwnerRecord();
                        app(MailboxService::class)->addMember($mailbox, User::findOrFail($data['user_id']), $data['role']);
                    }),
            ])
            ->recordActions([
                DeleteAction::make()->label('Revoke')->using(fn (MailboxMember $record) => app(MailboxService::class)->removeMember($record->mailbox, $record->user)),
            ]);
    }
}
