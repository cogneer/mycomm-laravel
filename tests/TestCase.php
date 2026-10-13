<?php

declare(strict_types=1);

namespace MyComm\Laravel\Tests;

use Illuminate\Support\Facades\Route;
use MyComm\Laravel\MyCommServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const SECRET = '5f1c8a0e9d3b47c2a6e4f0b1d2c3a4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1';

    protected function getPackageProviders($app): array
    {
        return [MyCommServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('mycomm.api_key', 'mc_live_test');
        $app['config']->set('mycomm.webhook.secret', self::SECRET);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    protected function defineRoutes($router): void
    {
        Route::mycommWebhooks();
        Route::middleware('web')->group(fn () => Route::mycommWebhooks('web/webhooks/mycomm')->name('mycomm.webhooks.web'));
    }
}
