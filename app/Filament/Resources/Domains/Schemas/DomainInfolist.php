<?php

namespace App\Filament\Resources\Domains\Schemas;

use App\Models\Domain;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class DomainInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(4)->schema([
                    TextEntry::make('name')->copyable(),
                    TextEntry::make('organization.name')->label('Organization'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('description')->placeholder('-'),
                ]),
                Section::make('Capacity')->columns(4)->schema([
                    TextEntry::make('mailboxes')->state(fn (Domain $r) => $r->mailboxes()->count()." / {$r->max_mailboxes}"),
                    TextEntry::make('aliases')->state(fn (Domain $r) => $r->aliases()->count()." / {$r->max_aliases}"),
                    TextEntry::make('storage')->state(fn (Domain $r) => number_format($r->mailboxes()->sum('used_bytes') / 1048576).' MB / '.number_format($r->domain_quota_mb).' MB'),
                    TextEntry::make('default_quota_mb')->label('Default / max mailbox quota')->state(fn (Domain $r) => "{$r->default_quota_mb} MB / {$r->max_quota_mb} MB"),
                ]),
                Section::make('DNS verification')
                    ->description(fn (Domain $r) => $r->dns_checked_at ? 'Last checked '.$r->dns_checked_at->diffForHumans() : 'Not checked yet. Use "Verify DNS".')
                    ->schema([
                        TextEntry::make('dns_status')
                            ->hiddenLabel()
                            ->state(fn (Domain $r) => static::renderDns($r->dns_status ?? []))
                            ->html(),
                    ]),
            ]);
    }

    protected static function renderDns(array $status): HtmlString
    {
        if (! $status) {
            return new HtmlString('<span class="text-gray-500">No DNS data yet.</span>');
        }

        $icons = ['ok' => '✅', 'warning' => '⚠️', 'missing' => '❌', 'unknown' => '❔'];
        $rows = '';

        foreach ($status as $record => $info) {
            $icon = $icons[$info['status'] ?? 'unknown'] ?? '❔';
            $found = e(implode("\n", $info['found'] ?? []) ?: '—');
            $expected = e($info['expected'] ?? '');
            $rows .= "<tr class=\"align-top\"><td class=\"py-2 pr-4 font-semibold uppercase\">{$icon} ".strtoupper(e($record)).'</td>'
                ."<td class=\"py-2 pr-4\"><div class=\"text-xs text-gray-500\">Expected</div><code class=\"text-xs break-all\">{$expected}</code></td>"
                ."<td class=\"py-2\"><div class=\"text-xs text-gray-500\">Found</div><pre class=\"text-xs whitespace-pre-wrap break-all\">{$found}</pre></td></tr>";
        }

        return new HtmlString("<table class=\"w-full text-sm\"><tbody>{$rows}</tbody></table>");
    }
}
