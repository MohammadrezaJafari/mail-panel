<?php

namespace App\Mail\Sieve;

use App\Models\Mailbox;

/**
 * Renders forwarding + vacation rules stored on a Mailbox as a Sieve script.
 * Provider independent: any Sieve-capable backend can consume the output.
 */
class SieveScriptBuilder
{
    public function build(Mailbox $mailbox): ?string
    {
        $forwards = array_filter($mailbox->forwarding_to ?? []);
        $vacation = $mailbox->auto_reply_enabled && filled($mailbox->auto_reply_body);
        $rules = array_values(array_filter($mailbox->rules ?? [], fn ($r) => ($r['enabled'] ?? true) && ! empty($r['conditions']) && ! empty($r['actions'])));

        if (! $forwards && ! $vacation && ! $rules) {
            return null;
        }

        $require = [];
        $lines = [];

        foreach ($rules as $rule) {
            $this->renderRule($rule, $require, $lines);
        }

        if ($forwards) {
            foreach ($forwards as $target) {
                $lines[] = 'redirect '.$this->quote($target).';';
            }
            if ($mailbox->forwarding_keep_copy) {
                $lines[] = 'keep;';
            }
        }

        if ($vacation) {
            $require[] = 'vacation';

            $subject = $mailbox->auto_reply_subject ?: 'Automatic reply';
            $body = 'vacation :days 1 :subject '.$this->quote($subject).' :from '.$this->quote($mailbox->address)
                .' '.$this->multiline($mailbox->auto_reply_body).';';

            $conditions = [];
            if ($mailbox->auto_reply_starts_at) {
                $conditions[] = 'currentdate :value "ge" "iso8601" '.$this->quote($mailbox->auto_reply_starts_at->toIso8601String());
            }
            if ($mailbox->auto_reply_ends_at) {
                $conditions[] = 'currentdate :value "le" "iso8601" '.$this->quote($mailbox->auto_reply_ends_at->toIso8601String());
            }

            if ($conditions) {
                $require[] = 'date';
                $require[] = 'relational';
                $lines[] = 'if allof('.implode(', ', $conditions).') {';
                $lines[] = '    '.$body;
                $lines[] = '}';
            } else {
                $lines[] = $body;
            }
        }

        $header = $require ? 'require ['.implode(', ', array_map($this->quote(...), array_unique($require)))."];\n" : '';

        return $header.implode("\n", $lines)."\n";
    }

    /**
     * @param  array{name?: string, match?: string, conditions: array<int, array{field: string, operator: string, value: string}>, actions: array<int, array{type: string, value?: string}>}  $rule
     */
    protected function renderRule(array $rule, array &$require, array &$lines): void
    {
        $tests = [];
        foreach ($rule['conditions'] as $c) {
            $value = $this->quote((string) ($c['value'] ?? ''));
            $op = $c['operator'] ?? 'contains';
            $matchType = match ($op) {
                'is' => ':is',
                'starts', 'ends', 'not_contains' => ':matches',
                default => ':contains',
            };
            if ($op === 'starts') {
                $value = $this->quote(($c['value'] ?? '').'*');
            } elseif ($op === 'ends') {
                $value = $this->quote('*'.($c['value'] ?? ''));
            }

            $test = match ($c['field'] ?? 'subject') {
                'from' => "address {$matchType} \"from\" {$value}",
                'to' => "address {$matchType} [\"to\", \"cc\"] {$value}",
                'body' => "body :text {$matchType} {$value}",
                'size_over' => 'size :over '.max(0, (int) ($c['value'] ?? 0)).'K',
                'has_attachment' => 'header :contains "Content-Type" "multipart/mixed"',
                default => "header {$matchType} \"subject\" {$value}",
            };
            if (($c['field'] ?? '') === 'body') {
                $require[] = 'body';
            }
            $tests[] = $op === 'not_contains' ? "not {$test}" : $test;
        }

        $condition = count($tests) === 1 ? $tests[0] : (($rule['match'] ?? 'all') === 'any' ? 'anyof' : 'allof').'('.implode(', ', $tests).')';

        $body = [];
        foreach ($rule['actions'] as $a) {
            switch ($a['type'] ?? '') {
                case 'move':
                    $require[] = 'fileinto';
                    $body[] = 'fileinto '.$this->quote((string) $a['value']).';';
                    break;
                case 'flag':
                    $require[] = 'imap4flags';
                    $body[] = 'addflag "\\Flagged";';
                    break;
                case 'mark_read':
                    $require[] = 'imap4flags';
                    $body[] = 'addflag "\\Seen";';
                    break;
                case 'forward':
                    $body[] = 'redirect '.$this->quote((string) $a['value']).';';
                    break;
                case 'discard':
                    $body[] = 'discard;';
                    break;
                case 'stop':
                    $body[] = 'stop;';
                    break;
            }
        }

        if (! $body) {
            return;
        }

        $name = trim((string) ($rule['name'] ?? ''));
        $lines[] = ($name !== '' ? '# rule: '.str_replace(["\r", "\n"], ' ', $name)."\n" : '')."if {$condition} {";
        foreach ($body as $line) {
            $lines[] = '    '.$line;
        }
        $lines[] = '}';
    }

    protected function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    protected function multiline(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        // Sieve multi-line literal: lines beginning with "." must be dot-stuffed.
        $text = preg_replace('/^\./m', '..', $text);

        return "text:\n".$text."\n.\n";
    }
}
