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

        if (! $forwards && ! $vacation) {
            return null;
        }

        $require = [];
        $lines = [];

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
