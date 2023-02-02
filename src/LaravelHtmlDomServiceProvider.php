<?php

namespace Toxageek\LaravelHtmlDom;

use Illuminate\Support\ServiceProvider;

class LaravelHtmlDomServiceProvider extends ServiceProvider
{

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-html-dom.php' => config_path('laravel-html-dom.php'),
        ], 'config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-html-dom.php', 'laravel-html-dom'
        );
    }
}
