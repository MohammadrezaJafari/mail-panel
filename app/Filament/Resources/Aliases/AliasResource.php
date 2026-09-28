<?php

namespace App\Filament\Resources\Aliases;

use App\Filament\Concerns\ScopesToOrganization;
use App\Filament\Resources\Aliases\Pages\CreateAlias;
use App\Filament\Resources\Aliases\Pages\EditAlias;
use App\Filament\Resources\Aliases\Pages\ListAliases;
use App\Filament\Resources\Aliases\Schemas\AliasForm;
use App\Filament\Resources\Aliases\Tables\AliasesTable;
use App\Models\Alias;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AliasResource extends Resource
{
    use ScopesToOrganization;

    protected static ?string $model = Alias::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAtSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'Mail';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'address';

    public static function form(Schema $schema): Schema
    {
        return AliasForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AliasesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAliases::route('/'),
            'create' => CreateAlias::route('/create'),
            'edit' => EditAlias::route('/{record}/edit'),
        ];
    }
}
