<?php

namespace App\Mail;

use App\Mail\Contracts\MailProvider;
use App\Mail\Providers\MailcowProvider;
use App\Mail\Providers\NullProvider;
use App\Mail\Sieve\SieveScriptBuilder;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

class MailProviderManager
{
    /** @var array<string, MailProvider> */
    protected array $resolved = [];

    public function __construct(protected Application $app) {}

    public function driver(?string $name = null): MailProvider
    {
        $name ??= (string) config('mailprovider.default');

        return $this->resolved[$name] ??= $this->resolve($name);
    }

    protected function resolve(string $name): MailProvider
    {
        $class = config("mailprovider.drivers.{$name}");

        if (! $class) {
            throw new InvalidArgumentException("Mail provider [{$name}] is not configured.");
        }

        return match ($class) {
            MailcowProvider::class => new MailcowProvider((array) config('mailprovider.mailcow'), $this->app->make(SieveScriptBuilder::class)),
            NullProvider::class => new NullProvider,
            default => $this->app->make($class),
        };
    }
}
