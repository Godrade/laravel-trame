<?php

namespace Godrade\Trame;

use Godrade\Trame\Blocks\BlockRegistry;
use Godrade\Trame\Console\ClearCacheCommand;
use Godrade\Trame\Console\MakeBlockCommand;
use Godrade\Trame\Console\SourcesCommand;
use Godrade\Trame\Media\MediaProvider;
use Godrade\Trame\Support\HtmlSanitizer;
use Godrade\Trame\Support\Localizer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TrameServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/trame.php', 'trame');

        $this->app->singleton(Localizer::class, fn ($app) => new Localizer($app['config']));
        $this->app->singleton(BlockRegistry::class, fn ($app) => new BlockRegistry($app, $app['config']));
        $this->app->singleton(HtmlSanitizer::class);
        $this->app->singleton(MediaProvider::class, fn ($app) => $app->make($app['config']->get('trame.media.provider')));
        $this->app->singleton(Trame::class);
        $this->app->alias(Trame::class, 'trame');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'trame');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'trame');

        if ($this->app['config']->get('trame.run_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->registerGates();
        $this->registerEditorRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeBlockCommand::class,
                SourcesCommand::class,
                ClearCacheCommand::class,
            ]);

            $this->publishes([__DIR__.'/../config/trame.php' => config_path('trame.php')], 'trame-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/trame')], 'trame-views');
            $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/trame')], 'trame-lang');
            $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'trame-migrations');
        }
    }

    private function registerGates(): void
    {
        // Deny by default: the host application must define "trame.edit".
        // Application providers boot after package providers, so a
        // Gate::define('trame.edit', ...) in the app overrides this one.
        if (! Gate::has('trame.edit')) {
            Gate::define('trame.edit', fn ($user = null) => false);
        }
    }

    private function registerEditorRoutes(): void
    {
        $config = $this->app['config'];

        if (! $config->get('trame.editor.enabled', true)) {
            return;
        }

        Route::prefix($config->get('trame.editor.prefix', 'trame'))
            ->middleware([...$config->get('trame.editor.middleware', ['web', 'auth']), 'can:trame.edit'])
            ->name('trame.')
            ->group(__DIR__.'/../routes/editor.php');
    }
}
