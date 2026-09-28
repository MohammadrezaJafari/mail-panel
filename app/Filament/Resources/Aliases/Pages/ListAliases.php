<?php

namespace App\Filament\Resources\Aliases\Pages;

use App\Filament\Resources\Aliases\AliasResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAliases extends ListRecords
{
    protected static string $resource = AliasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
