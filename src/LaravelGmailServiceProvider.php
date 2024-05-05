<?php

namespace Localtools\LaravelGmail;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class LaravelGmailServiceProvider extends ServiceProvider
{

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/config/gmail.php' => App::make('path.config') . '/gmail.php',]);
    }

    public function register(): void
    {

        $this->mergeConfigFrom(__DIR__ . '/config/gmail.php', 'gmail');

        $this->app->bind('laravelgmail', function ($app) {
            return new LaravelGmailClass($app['config']);
        });
    }
}
