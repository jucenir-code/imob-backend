<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \App\Models\Property::observe(\App\Observers\WebPushObserver::class);
        \App\Models\DealMessage::observe(\App\Observers\WebPushObserver::class);
        // Força HTTPS em produção (necessário quando atrás de proxy reverso com SSL)
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
