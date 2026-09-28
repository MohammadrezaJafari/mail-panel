<?php

namespace Tests\Unit;

use App\Mail\Client\SmtpSender;
use App\Models\User;
use Tests\TestCase;

class SmtpSenderTest extends TestCase
{
    public function test_builds_threading_headers(): void
    {
        $user = new User(['name' => 'Ali', 'email' => 'ali@acme.test']);

        $email = (new SmtpSender)->build($user, [
            'to' => ['bob@example.com'],
            'subject' => 'Re: hi',
            'text' => 'hello',
            'in_reply_to' => '<parent@acme.test>',
            'references' => 'root@acme.test <parent@acme.test>',
        ]);

        $this->assertSame('<parent@acme.test>', $email->getHeaders()->get('In-Reply-To')->getBodyAsString());
        $this->assertSame('<root@acme.test> <parent@acme.test>', $email->getHeaders()->get('References')->getBodyAsString());
        $this->assertSame('ali@acme.test', $email->getFrom()[0]->getAddress());
    }
}
