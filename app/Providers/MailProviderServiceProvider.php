<?php

namespace App\Providers;

use App\Mail\Contracts\MailProvider;
use App\Mail\MailProviderManager;
use Illuminate\Support\ServiceProvider;

class MailProviderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MailProviderManager::class);

        $this->app->singleton(MailProvider::class, fn ($app) => $app->make(MailProviderManager::class)->driver());
    }
}
