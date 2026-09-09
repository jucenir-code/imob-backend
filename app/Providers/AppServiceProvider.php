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
        // Força HTTPS em produção (necessário quando atrás de proxy reverso com SSL)
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
