<?php

namespace App\Filament\Support;

use App\Mail\Exceptions\ProviderException;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

class ProviderErrors
{
    /**
     * Run a service call; on provider failure show a notification and halt the action.
     */
    public static function guard(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (ProviderException $e) {
            Notification::make()
                ->title('Mail provider error')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }
    }
}
