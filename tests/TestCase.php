<?php

namespace Godrade\Trame\Tests;

use Godrade\Trame\TrameServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [TrameServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('trame.locales', ['fr', 'en']);
        $app['config']->set('trame.fallback_locale', 'fr');
        $app['config']->set('trame.editor.preview.vite', []);
        $app['config']->set('trame.blocks.discover.path', __DIR__.'/Fixtures/Blocks');
        $app['config']->set('trame.blocks.discover.namespace', 'Godrade\\Trame\\Tests\\Fixtures\\Blocks');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
    }
}
