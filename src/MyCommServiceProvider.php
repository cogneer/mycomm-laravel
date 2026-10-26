<?php

declare(strict_types=1);

namespace MyComm\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use MyComm\Client;
use MyComm\Exceptions\MyCommException;
use MyComm\Laravel\Console\VerifyCommand;
use MyComm\Laravel\Http\Controllers\WebhookController;
use MyComm\Laravel\Http\Middleware\VerifyWebhookSignature;
use MyComm\Laravel\Notifications\MyCommChannel;

final class MyCommServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mycomm.php', 'mycomm');

        $this->app->singleton(Client::class, static function (Application $app): Client {
            /** @var array<string, mixed> $config */
            $config = $app->make('config')->get('mycomm');
            $key = $config['api_key'] ?? null;
            if (! is_string($key) || trim($key) === '') {
                throw new MyCommException('Set MYCOMM_API_KEY in your environment (or mycomm.api_key in config) to use MyComm.');
            }

            return new Client(
                apiKey: $key,
                baseUrl: (string) ($config['base_url'] ?? Client::DEFAULT_BASE_URL),
                maxRetries: (int) ($config['max_retries'] ?? 2),
                timeout: (float) ($config['timeout'] ?? 30.0),
                validate: (bool) ($config['validate'] ?? true),
            );
        });

        $this->app->bind(MyCommManager::class, static fn (Application $app): MyCommManager => new MyCommManager($app->make(Client::class)));
    }

    public function boot(): void
    {
        $this->commands([VerifyCommand::class]);

        // `via()` may return 'mycomm' once the notifications component is loaded.
        $this->callAfterResolving(ChannelManager::class, function (ChannelManager $manager, Application $app): void {
            $manager->extend('mycomm', fn (): MyCommChannel => $app->make(MyCommChannel::class));
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/mycomm.php' => $this->app->configPath('mycomm.php')], 'mycomm-config');
        }

        // Route::mycommWebhooks('webhooks/mycomm') registers the signed receiver.
        Route::macro('mycommWebhooks', function (string $uri = 'webhooks/mycomm'): RouteInstance {
            return Route::post($uri, WebhookController::class)
                ->middleware(VerifyWebhookSignature::class)
                // MyComm cannot send a CSRF token; the signature is what authenticates the request.
                ->withoutMiddleware([
                    'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
                    'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
                    'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
                ])
                ->name('mycomm.webhooks');
        });
    }
}
