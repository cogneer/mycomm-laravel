<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use MyComm\Exceptions\MyCommException;
use MyComm\Laravel\Events\WebhookReceived;
use MyComm\Laravel\Tests\TestCase;
use MyComm\Testing\FakeWebhook;
use MyComm\Webhooks\Events\MessageDelivered;
use MyComm\Webhooks\Events\MessageReceived;
use MyComm\Webhooks\UnknownEvent;
use MyComm\Webhooks\Webhook;

/**
 * Posts a body to the receiver exactly as given, so the signature is checked against the raw bytes.
 */
function deliver(mixed $test, string $payload, ?string $signature, string $uri = '/webhooks/mycomm'): TestResponse
{
    $server = ['CONTENT_TYPE' => 'application/json'];
    if ($signature !== null) {
        $server['HTTP_MYCOMM_SIGNATURE'] = $signature;
    }

    return $test->call('POST', $uri, [], [], [], $server, $payload);
}

it('dispatches the typed event of a signed delivery', function () {
    Event::fake([MessageDelivered::class, WebhookReceived::class]);
    $webhook = FakeWebhook::make('message.delivered', ['data' => ['message' => ['status' => 'delivered']]], TestCase::SECRET);

    deliver($this, $webhook->payload, $webhook->signature)->assertNoContent();

    Event::assertDispatched(MessageDelivered::class, fn (MessageDelivered $event) => $event->data->message->status === 'delivered');
    Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $received) => $received->event instanceof MessageDelivered);
});

it('refuses a delivery it cannot verify', function (?string $signature) {
    Event::fake();
    $webhook = FakeWebhook::make('message.delivered', secret: TestCase::SECRET);

    deliver($this, $webhook->payload, $signature)->assertStatus(400);

    Event::assertNotDispatched(MessageDelivered::class);
    Event::assertNotDispatched(WebhookReceived::class);
})->with([
    'no header' => [null],
    'another secret' => [fn () => Webhook::sign(FakeWebhook::make('message.delivered')->payload, 'not-the-secret')],
    'an old timestamp' => [fn () => FakeWebhook::make('message.delivered', secret: TestCase::SECRET, timestamp: time() - 3600)->signature],
]);

it('refuses a body that was altered after signing', function () {
    Event::fake([WebhookReceived::class]);
    $webhook = FakeWebhook::make('message.delivered', secret: TestCase::SECRET);

    deliver($this, str_replace('message.delivered', 'message.failed', $webhook->payload), $webhook->signature)->assertStatus(400);

    Event::assertNotDispatched(WebhookReceived::class);
});

it('dispatches a repeated delivery once', function () {
    Event::fake([MessageReceived::class]);
    $webhook = FakeWebhook::make('message.received', secret: TestCase::SECRET);

    deliver($this, $webhook->payload, $webhook->signature)->assertNoContent();
    deliver($this, $webhook->payload, Webhook::sign($webhook->payload, TestCase::SECRET))->assertNoContent();

    Event::assertDispatchedTimes(MessageReceived::class, 1);
});

it('dispatches every delivery when de-duplication is off', function () {
    config(['mycomm.webhook.deduplicate' => false]);
    Event::fake([MessageReceived::class]);
    $webhook = FakeWebhook::make('message.received', secret: TestCase::SECRET);

    deliver($this, $webhook->payload, $webhook->signature);
    deliver($this, $webhook->payload, $webhook->signature);

    Event::assertDispatchedTimes(MessageReceived::class, 2);
});

it('accepts both secrets while one is rotated out', function () {
    config(['mycomm.webhook.secret' => 'new-secret, '.TestCase::SECRET]);
    Event::fake([MessageDelivered::class]);
    $old = FakeWebhook::make('message.delivered', ['id' => '01923f88-0000-7000-8000-000000000001'], TestCase::SECRET);
    $new = FakeWebhook::make('message.delivered', ['id' => '01923f88-0000-7000-8000-000000000002'], 'new-secret');

    deliver($this, $old->payload, $old->signature)->assertNoContent();
    deliver($this, $new->payload, $new->signature)->assertNoContent();

    Event::assertDispatchedTimes(MessageDelivered::class, 2);
});

it('acknowledges an event type it does not know', function () {
    Event::fake([WebhookReceived::class]);
    $payload = (string) json_encode(['type' => 'campaign.finished', 'data' => ['campaign_id' => 'c-1']] + json_decode(FakeWebhook::make('ping')->payload, true));

    deliver($this, $payload, Webhook::sign($payload, TestCase::SECRET))->assertNoContent();

    Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $received) => $received->event instanceof UnknownEvent && $received->event->type === 'campaign.finished');
});

it('works inside the web middleware group, without a CSRF token', function () {
    Event::fake([MessageDelivered::class]);
    $webhook = FakeWebhook::make('message.delivered', secret: TestCase::SECRET);

    deliver($this, $webhook->payload, $webhook->signature, '/web/webhooks/mycomm')->assertNoContent();

    Event::assertDispatched(MessageDelivered::class);
});

it('says what is missing when no signing secret is configured', function () {
    config(['mycomm.webhook.secret' => null]);
    $webhook = FakeWebhook::make('ping');

    $this->withoutExceptionHandling();

    expect(fn () => deliver($this, $webhook->payload, $webhook->signature))->toThrow(MyCommException::class, 'Set MYCOMM_WEBHOOK_SECRET');
});

it('names its route', function () {
    expect(route('mycomm.webhooks', absolute: false))->toBe('/webhooks/mycomm');
});
