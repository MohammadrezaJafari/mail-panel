<?php

namespace App\Filament\Widgets;

use App\Mail\Contracts\MailProvider;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class MailHealthWidget extends Widget
{
    protected string $view = 'filament.widgets.mail-health';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected function getViewData(): array
    {
        $provider = app(MailProvider::class);

        $health = Cache::remember('mail-health', 55, fn () => $provider->health());

        return [
            'provider' => $provider->name(),
            'health' => $health,
        ];
    }
}
