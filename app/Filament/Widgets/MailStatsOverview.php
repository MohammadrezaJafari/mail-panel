<?php

namespace App\Filament\Widgets;

use App\Enums\MailboxStatus;
use App\Models\Alias;
use App\Models\Domain;
use App\Models\Mailbox;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class MailStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $user = Auth::user();
        $scope = fn ($query) => $user?->isSuperAdmin() ? $query : $query->where('organization_id', $user?->organization_id);

        $mailboxes = $scope(Mailbox::query());
        $used = (clone $mailboxes)->sum('used_bytes');
        $quota = (clone $mailboxes)->sum('quota_mb') * 1048576;

        return [
            Stat::make('Domains', $scope(Domain::query())->count())
                ->description($scope(Domain::query())->where('status', 'active')->count().' active')
                ->icon('heroicon-o-globe-alt'),
            Stat::make('Mailboxes', (clone $mailboxes)->count())
                ->description((clone $mailboxes)->where('status', MailboxStatus::Suspended)->count().' suspended')
                ->icon('heroicon-o-inbox'),
            Stat::make('Aliases', $scope(Alias::query())->count())
                ->icon('heroicon-o-at-symbol'),
            Stat::make('Storage', Number::fileSize($used))
                ->description('of '.Number::fileSize($quota).' allocated')
                ->icon('heroicon-o-circle-stack')
                ->color($quota > 0 && $used / $quota > 0.85 ? 'danger' : 'success'),
        ];
    }
}
