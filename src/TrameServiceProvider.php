<?php

namespace Godrade\Trame;

use Godrade\Trame\Blocks\BlockRegistry;
use Godrade\Trame\Console\ClearCacheCommand;
use Godrade\Trame\Console\MakeBlockCommand;
use Godrade\Trame\Console\SourcesCommand;
use Godrade\Trame\Links\LinkResolver;
use Godrade\Trame\Links\PageLinkResolver;
use Godrade\Trame\Media\MediaProvider;
use Godrade\Trame\Support\HtmlSanitizer;
use Godrade\Trame\Support\Localizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->singleton(LinkResolver::class, fn ($app) => $app->make($app['config']->get('trame.links.resolver') ?: PageLinkResolver::class));
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
        $this->registerRateLimiters();
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

        // "trame.advanced" unlocks the advanced mode of the editor (spacing, appearance, per-screen
        // values, every block). Everyone else gets the simple mode. Denied by default.
        if (! Gate::has('trame.advanced')) {
            Gate::define('trame.advanced', fn ($user = null) => false);
        }

        // "trame.view" opens the editor read-only: everything can be tried, nothing is stored.
        // By default only editors can view; define it in the app to open a demo or a review role.
        if (! Gate::has('trame.view')) {
            Gate::define('trame.view', fn ($user = null) => Gate::forUser($user)->allows('trame.edit'));
        }
    }

    /**
     * Previews render a posted document on every edit: editors are not limited,
     * read-only viewers get a per-minute budget (trame.editor.preview.viewer_per_minute).
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('trame-preview', function (Request $request): Limit {
            if (Gate::forUser($request->user())->allows('trame.edit')) {
                return Limit::none();
            }

            return Limit::perMinute((int) config('trame.editor.preview.viewer_per_minute', 120))
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });
    }

    private function registerEditorRoutes(): void
    {
        $config = $this->app['config'];

        if (! $config->get('trame.editor.enabled', true)) {
            return;
        }

        Route::prefix($config->get('trame.editor.prefix', 'trame'))
            ->middleware([...$config->get('trame.editor.middleware', ['web', 'auth']), 'can:trame.view'])
            ->name('trame.')
            ->group(__DIR__.'/../routes/editor.php');
    }
}
