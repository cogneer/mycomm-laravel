<?php

declare(strict_types=1);

use MyComm\Exceptions\InvalidArgumentException;
use MyComm\Laravel\Facades\MyComm;
use MyComm\Testing\FakeResponse;

it('passes when the key works and a dry run is accepted', function () {
    $fake = MyComm::fake();

    $this->artisan('mycomm:verify', ['--to' => '+923001234567'])
        ->expectsOutputToContain('PASS  key')
        ->expectsOutputToContain('PASS  send')
        ->assertSuccessful();

    $fake->assertCalled('messages.send', fn ($request) => ($request->json['dry_run'] ?? null) === true);
});

it('exits 1 and says why when the key is refused', function () {
    MyComm::fake()->respond('account.get', FakeResponse::error(401, 'UNAUTHENTICATED', 'The API key is not valid.'));

    $this->artisan('mycomm:verify', ['--to' => '+923001234567'])
        ->expectsOutputToContain('FAIL  key: The API key is not valid.')
        ->assertFailed();
});

it('exits 1 when the dry run is refused', function () {
    MyComm::fake()->respond('messages.send', FakeResponse::error(422, 'RECIPIENT_IS_CHANNEL', 'The recipient is the number this message would be sent from.'));

    $this->artisan('mycomm:verify', ['--to' => '+923001234567'])
        ->expectsOutputToContain('FAIL  send: The recipient is the number this message would be sent from.')
        ->assertFailed();
});

it('checks only the key without a number and says how to test a send', function () {
    $fake = MyComm::fake();

    $this->artisan('mycomm:verify')
        ->expectsOutputToContain('Pass --to=')
        ->assertSuccessful();

    $fake->assertNotCalled('messages.send');
});

it('verifies through the facade', function () {
    MyComm::fake();

    expect(MyComm::verify()->ok())->toBeTrue();
});

it('lets the SDK exceptions through the facade unchanged', function () {
    MyComm::fake();

    expect(fn () => MyComm::messages()->send(expires_in: 5))->toThrow(InvalidArgumentException::class);
});

it('validates arguments by default', function () {
    expect(config('mycomm.validate'))->toBeTrue();
});
