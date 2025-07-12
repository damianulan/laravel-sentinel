<?php

namespace Sentinel;

use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Support\Facades\Blade;
use Sentinel\Console\Commands\AssignRca;

/**
 * @author Damian Ułan <damian.ulan@protonmail.com>
 * @copyright 2025 damianulan
 * @package Sentinel
 * @license MIT
 */
class SentinelServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sentinel.php', 'sentinel');
    }

    /**
     * When this method is apply we have all laravel providers and methods available
     */
    public function boot(): void
    {

        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'sentinel');

        $this->publishes([
            __DIR__ . '/../lang'                   => $this->app->langPath('vendor/sentinel'),
        ], 'sentinel-langs');

        $this->publishes([
            __DIR__ . '/../config/sentinel.php'      => config_path('sentinel.php'),
        ], 'sentinel-config');

        $this->publishes([
            __DIR__ . '/../stubs'                  => base_path('stubs'),
            __DIR__ . '/../config/sentinel.php'      => config_path('sentinel.php'),
        ], 'sentinel');

        $this->registerBladeDirectives();
        $this->registerCommands();
    }

    public function registerBladeDirectives(): void {}

    public function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AssignRca::class,
            ]);
        }
    }
}
