# MyComm for Laravel

Laravel integration for the [MyComm PHP SDK](https://packagist.org/packages/mycomm/mycomm-php): configuration from
your `.env`, a facade, a signed webhook route that dispatches Laravel events, and a fake for your tests.

Requires PHP 8.2+ and Laravel 12 or 13.

## Install

```bash
composer require mycomm/mycomm-laravel
```

```dotenv
MYCOMM_API_KEY=mc_live_...
MYCOMM_WEBHOOK_SECRET=...        # only if you receive webhooks
```

The service provider and the `MyComm` facade are discovered automatically. To change other settings
(base URL, retries, timeout, webhook tolerance), publish the config file:

```bash
php artisan vendor:publish --tag=mycomm-config
```

## Sending

Use the facade, or inject `MyComm\Client`: both are the same client.

```php
use MyComm\Laravel\Facades\MyComm;

$message = MyComm::messages()->send(
    to: $order->phone,
    body: "Order {$order->number} has shipped.",
);
```

```php
use MyComm\Client;

final class SendShippingNotice
{
    public function __construct(private readonly Client $mycomm) {}

    public function handle(Order $order): void
    {
        $this->mycomm->messages->send(to: $order->phone, body: "Order {$order->number} has shipped.");
    }
}
```

Every resource of the SDK is a method on the facade (`MyComm::contacts()`, `MyComm::webhookEndpoints()`,
`MyComm::partner()`); what you can call on them is described in the
[SDK's README](https://packagist.org/packages/mycomm/mycomm-php). Partners act for a managed account with
`MyComm::forAccount($accountId)->messages->send(...)`.

In a queued job, give the send an idempotency key derived from your own data, so a retried job cannot send
twice:

```php
use MyComm\RequestOptions;

MyComm::messages()->send(
    to: $order->phone,
    body: "Order {$order->number} has shipped.",
    requestOptions: new RequestOptions(idempotencyKey: "order-shipped-{$order->id}"),
);
```

## Receiving webhooks

Register the receiver in `routes/api.php` or `routes/web.php`:

```php
Route::mycommWebhooks('webhooks/mycomm');
```

Create a webhook endpoint in the MyComm portal that points at that URL, and put its signing secret in
`MYCOMM_WEBHOOK_SECRET`. The route verifies the signature of every request (and answers `400` when it does
not match), skips an event it has already seen, and dispatches the event as a normal Laravel event:

```php
use MyComm\Webhooks\Events\MessageDelivered;
use MyComm\Webhooks\Events\MessageReceived;

final class MarkOrderNotified implements ShouldQueue
{
    public function handle(MessageDelivered $event): void
    {
        Notification::query()
            ->where('mycomm_message_id', $event->data->message->id)
            ->update(['delivered_at' => $event->data->message->delivered_at]);
    }
}
```

Laravel discovers listeners by the type of their `handle()` argument. There is a class for each event type in
`MyComm\Webhooks\Events`. To handle all of them in one place, listen for
`MyComm\Laravel\Events\WebhookReceived` and read its `event` property.

Things to know:

- **Queue your listeners** (`ShouldQueue`). MyComm waits 10 seconds for the answer and retries otherwise.
- **Events can arrive out of order.** Never move a message status backwards.
- **Duplicates** are filtered by event id for 7 days using your default cache store. An event counts as
  handled only when no synchronous listener threw; a queued listener that fails later is retried by your
  queue, not by MyComm. If that store is
  `array` or per-process, switch it or set `MYCOMM_WEBHOOK_DEDUPLICATE=false` and de-duplicate yourself.
- **Rotating the secret:** set `MYCOMM_WEBHOOK_SECRET=new-secret,old-secret` before you rotate, and drop the
  old one a day later.
- The route works inside the `web` group too: it is exempt from CSRF protection, because the signature is
  what authenticates it.

Need the event without the route? `MyComm\Laravel\Http\Middleware\VerifyWebhookSignature` is the middleware
the route uses; behind it, `VerifyWebhookSignature::event($request)` returns the typed event.

## Testing

```php
use MyComm\Http\Request;
use MyComm\Laravel\Facades\MyComm;
use MyComm\Testing\FakeResponse;

it('texts the customer when an order ships', function () {
    $fake = MyComm::fake();

    $order->ship();

    $fake->assertCalled('messages.send', fn (Request $request) => $request->json['to'] === $order->phone);
});

it('copes with a customer who opted out', function () {
    MyComm::fake()->respond('messages.send', FakeResponse::error(422, 'RECIPIENT_OPTED_OUT'));

    $order->ship();

    expect($order->refresh()->notification_skipped)->toBeTrue();
});
```

`MyComm::fake()` replaces the client for the facade and for injection. Nothing reaches the network; every
operation answers with the example of the API reference unless you stub it.

To test your own listener end to end, post a signed delivery to the route:

```php
use MyComm\Testing\FakeWebhook;

$webhook = FakeWebhook::make('message.delivered', ['data' => ['message' => ['id' => $messageId]]], config('mycomm.webhook.secret'));

$this->call('POST', '/webhooks/mycomm', content: $webhook->payload, server: [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_MYCOMM_SIGNATURE' => $webhook->signature,
])->assertNoContent();
```

## License

MIT. See [LICENSE](LICENSE).
