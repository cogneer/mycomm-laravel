<?php

declare(strict_types=1);

use MyComm\Client;
use MyComm\Enums\MessageStatus;
use MyComm\Exceptions\MyCommException;
use MyComm\Http\Request;
use MyComm\Laravel\Facades\MyComm;
use MyComm\Laravel\MyCommManager;
use MyComm\Resources\Messages;

it('builds one client from the configuration', function () {
    config(['mycomm.base_url' => 'https://staging.mycomm.test/', 'mycomm.max_retries' => 0]);

    $client = app(Client::class);

    expect($client)->toBe(app(Client::class))
        ->and((fn () => $this->baseUrl)->call($client))->toBe('https://staging.mycomm.test')
        ->and((fn () => $this->maxRetries)->call($client))->toBe(0);
});

it('says what is missing when no key is configured', function () {
    config(['mycomm.api_key' => null]);

    expect(fn () => app(Client::class))->toThrow(MyCommException::class, 'Set MYCOMM_API_KEY');
});

it('reaches every resource through the facade', function () {
    expect(MyComm::messages())->toBeInstanceOf(Messages::class)
        ->and(MyComm::messages())->toBe(app(Client::class)->messages)
        ->and(MyComm::client())->toBe(app(Client::class))
        ->and(app(MyCommManager::class)->contacts())->toBe(app(Client::class)->contacts);

    expect(fn () => MyComm::nothing())->toThrow(BadMethodCallException::class, 'MyComm has no resource named nothing.');
});

it('documents every resource of the client on the facade', function () {
    $documented = [];
    preg_match_all('/@method static \\\\MyComm\\\\Resources\\\\(\w+) (\w+)\(\)/', (string) (new ReflectionClass(MyComm::class))->getDocComment(), $matches, PREG_SET_ORDER);
    foreach ($matches as [, $class, $method]) {
        $documented[$method] = 'MyComm\\Resources\\'.$class;
    }

    $actual = [];
    foreach ((new ReflectionClass(Client::class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
        $actual[$property->getName()] = (string) $property->getType();
    }
    ksort($documented);
    ksort($actual);

    expect($documented)->toBe($actual);
});

it('acts as a managed account through the facade', function () {
    $fake = MyComm::fake();

    MyComm::forAccount('acc-1')->messages->send(to: '+923001234567', body: 'Hi');

    $fake->assertCalled('messages.send', fn (Request $request) => $request->header('MyComm-Account') === 'acc-1');
});

it('swaps in a fake for the facade and for injection alike', function () {
    $fake = MyComm::fake();
    $fake->respond('messages.send', ['status' => 'queued']);

    $viaFacade = MyComm::messages()->send(to: '+923001234567', body: 'Hi');
    $viaContainer = app(Client::class)->messages->send(to: '+923331112222', body: 'Hi');

    expect($viaFacade->status)->toBe(MessageStatus::Queued)
        ->and($viaContainer->status)->toBe(MessageStatus::Queued);
    $fake->assertCalledTimes('messages.send', 2)
        ->assertCalled('messages.send', fn (Request $request) => $request->json['to'] === '+923331112222');
});

it('publishes its configuration', function () {
    $this->artisan('vendor:publish', ['--tag' => 'mycomm-config'])->assertSuccessful();

    expect(config_path('mycomm.php'))->toBeFile();
    unlink(config_path('mycomm.php'));
});

it('reads its settings from the environment', function () {
    $config = require dirname(__DIR__).'/config/mycomm.php';

    expect($config)->toHaveKeys(['api_key', 'base_url', 'max_retries', 'timeout', 'webhook'])
        ->and($config['base_url'])->toBe('https://mycomm.cogneer.tech')
        ->and($config['max_retries'])->toBe(2)
        ->and($config['webhook'])->toHaveKeys(['secret', 'tolerance', 'deduplicate'])
        ->and($config['webhook']['tolerance'])->toBe(300);
});
