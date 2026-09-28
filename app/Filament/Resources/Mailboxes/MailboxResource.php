<?php

namespace App\Filament\Resources\Mailboxes;

use App\Filament\Concerns\ScopesToOrganization;
use App\Filament\Resources\Mailboxes\Pages\CreateMailbox;
use App\Filament\Resources\Mailboxes\Pages\EditMailbox;
use App\Filament\Resources\Mailboxes\Pages\ListMailboxes;
use App\Filament\Resources\Mailboxes\Pages\ViewMailbox;
use App\Filament\Resources\Mailboxes\RelationManagers\AliasesRelationManager;
use App\Filament\Resources\Mailboxes\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Mailboxes\Schemas\MailboxForm;
use App\Filament\Resources\Mailboxes\Schemas\MailboxInfolist;
use App\Filament\Resources\Mailboxes\Tables\MailboxesTable;
use App\Models\Mailbox;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MailboxResource extends Resource
{
    use ScopesToOrganization;

    protected static ?string $model = Mailbox::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Mail';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'address';

    public static function form(Schema $schema): Schema
    {
        return MailboxForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MailboxInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MailboxesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AliasesRelationManager::class,
            MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMailboxes::route('/'),
            'create' => CreateMailbox::route('/create'),
            'view' => ViewMailbox::route('/{record}'),
            'edit' => EditMailbox::route('/{record}/edit'),
        ];
    }
}
