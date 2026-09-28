<?php

namespace App\Filament\Resources\Domains;

use App\Filament\Concerns\ScopesToOrganization;
use App\Filament\Resources\Domains\Pages\CreateDomain;
use App\Filament\Resources\Domains\Pages\EditDomain;
use App\Filament\Resources\Domains\Pages\ListDomains;
use App\Filament\Resources\Domains\Pages\ViewDomain;
use App\Filament\Resources\Domains\RelationManagers\AliasesRelationManager;
use App\Filament\Resources\Domains\RelationManagers\MailboxesRelationManager;
use App\Filament\Resources\Domains\Schemas\DomainForm;
use App\Filament\Resources\Domains\Schemas\DomainInfolist;
use App\Filament\Resources\Domains\Tables\DomainsTable;
use App\Models\Domain;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DomainResource extends Resource
{
    use ScopesToOrganization;

    protected static ?string $model = Domain::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Mail';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DomainForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DomainInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DomainsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MailboxesRelationManager::class,
            AliasesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDomains::route('/'),
            'create' => CreateDomain::route('/create'),
            'view' => ViewDomain::route('/{record}'),
            'edit' => EditDomain::route('/{record}/edit'),
        ];
    }
}
