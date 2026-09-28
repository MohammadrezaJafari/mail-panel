<?php

namespace Tests\Unit;

use App\Mail\Sieve\SieveScriptBuilder;
use App\Models\Mailbox;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SieveScriptBuilderTest extends TestCase
{
    public function test_returns_null_when_no_rules(): void
    {
        $mailbox = new Mailbox(['address' => 'ali@acme.test']);

        $this->assertNull((new SieveScriptBuilder)->build($mailbox));
    }

    public function test_renders_forwarding_with_copy(): void
    {
        $mailbox = new Mailbox([
            'address' => 'ali@acme.test',
            'forwarding_to' => ['other@example.com'],
            'forwarding_keep_copy' => true,
        ]);

        $script = (new SieveScriptBuilder)->build($mailbox);

        $this->assertStringContainsString('redirect "other@example.com";', $script);
        $this->assertStringContainsString('keep;', $script);
    }

    public function test_renders_vacation_with_date_window(): void
    {
        $mailbox = new Mailbox([
            'address' => 'ali@acme.test',
            'auto_reply_enabled' => true,
            'auto_reply_subject' => 'Out of "office"',
            'auto_reply_body' => "I am away.\n.dot line",
            'auto_reply_starts_at' => Carbon::parse('2026-01-01T00:00:00Z'),
            'auto_reply_ends_at' => Carbon::parse('2026-01-10T00:00:00Z'),
        ]);

        $script = (new SieveScriptBuilder)->build($mailbox);

        $this->assertStringContainsString('require ["vacation", "date", "relational"];', $script);
        $this->assertStringContainsString(':subject "Out of \"office\""', $script);
        $this->assertStringContainsString('currentdate :value "ge" "iso8601"', $script);
        $this->assertStringContainsString('..dot line', $script);
    }

    public function test_renders_user_rules(): void
    {
        $mailbox = new Mailbox([
            'address' => 'ali@acme.test',
            'rules' => [
                ['name' => 'Newsletters', 'enabled' => true, 'match' => 'any',
                    'conditions' => [['field' => 'from', 'operator' => 'contains', 'value' => 'news@'], ['field' => 'subject', 'operator' => 'starts', 'value' => '[List]']],
                    'actions' => [['type' => 'move', 'value' => 'Newsletters'], ['type' => 'mark_read'], ['type' => 'stop']]],
                ['name' => 'Off', 'enabled' => false, 'conditions' => [['field' => 'subject', 'value' => 'x']], 'actions' => [['type' => 'discard']]],
            ],
        ]);

        $script = (new SieveScriptBuilder)->build($mailbox);

        $this->assertStringContainsString('require ["fileinto", "imap4flags"];', $script);
        $this->assertStringContainsString('# rule: Newsletters', $script);
        $this->assertStringContainsString('if anyof(address :contains "from" "news@", header :matches "subject" "[List]*") {', $script);
        $this->assertStringContainsString('    fileinto "Newsletters";', $script);
        $this->assertStringContainsString('    addflag "\\Seen";', $script);
        $this->assertStringNotContainsString('discard', $script);
    }
}
