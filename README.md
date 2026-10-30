# MyComm for Laravel

Laravel integration for the [MyComm PHP SDK](https://packagist.org/packages/mycomm/mycomm-php): configuration from
your `.env`, a facade, a notification channel, a signed webhook route that dispatches Laravel events, an
`artisan mycomm:verify` command, and a fake for your tests.

Requires PHP 8.2+ and Laravel 12 or 13. The guide for this package, with the API documentation, is at
[mycomm.cogneer.tech/docs/guides/laravel](https://mycomm.cogneer.tech/docs/guides/laravel).

## Install

```bash
composer require mycomm/mycomm-laravel
```

Release status: `1.0.x` is published on Packagist. `2.0.0` (lines replace inboxes, for `mycomm/mycomm-php` 2.0) is not
published yet; until it is, `composer require` installs `1.0.x`.

```dotenv
MYCOMM_API_KEY=mc_live_...
MYCOMM_WEBHOOK_SECRET=...        # only if you receive webhooks
```

The service provider and the `MyComm` facade are discovered automatically. Every setting has an environment
variable; publish the config file (`php artisan vendor:publish --tag=mycomm-config`) only to change something
else.

| Environment variable | Default | What it does |
|---|---|---|
| `MYCOMM_API_KEY` | none, required | An account key (`mc_live_...`) or a partner key (`mc_partner_...`) |
| `MYCOMM_BASE_URL` | `https://mycomm.cogneer.tech` | Origin of the API, without `/v1`; change it only to reach staging |
| `MYCOMM_MAX_RETRIES` | `2` | Retries after a network error, a 429 or a 5xx. Sends carry an idempotency key, so a retry never sends twice |
| `MYCOMM_TIMEOUT` | `30` | Seconds per attempt |
| `MYCOMM_VALIDATE` | `true` | Check arguments against the API's documented rules before sending |
| `MYCOMM_WEBHOOK_SECRET` | none | Signing secret of your webhook endpoint; `new,old` while rotating |
| `MYCOMM_WEBHOOK_TOLERANCE` | `300` | Seconds a webhook signature stays acceptable |
| `MYCOMM_WEBHOOK_DEDUPLICATE` | `true` | Acknowledge an event id seen in the last 7 days without dispatching it again |

Check the setup once it is in place:

```bash
php artisan mycomm:verify --to=+923001234567
```

Add `--line=<line id>` when the key can use several lines. A partner key (`mc_partner_...`) is checked on the partner
API, and its send is dry-run through the line given with `--line`, which it needs.

It checks the key and dry-runs a message to that number, sends nothing, prints each check as `PASS` or `FAIL` with the
API's own message, and exits with code 1 when one fails, so it can sit in a deploy script. See
[Verify your integration](https://mycomm.cogneer.tech/docs/guides/verify-your-integration).

## Sending

Use the facade, or inject `MyComm\Client`: both are the same client. The facade calls a resource as a method
(`MyComm::messages()->send(...)`); the injected client has the resource as a property
(`$client->messages->send(...)`).

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
[SDK's README](https://packagist.org/packages/mycomm/mycomm-php). With a partner key, send as one of your lines
with `MyComm::forLine($lineId)->messages->send(...)`; `MyComm::partner()->lines` creates and follows the lines.

## Connecting your user's phone

The Blade component renders the [`@mycomm/connect`](https://www.npmjs.com/package/@mycomm/connect) widget with a hosted
link made for this page view:

```blade
<x-mycomm-connect :line="$tenant->mycomm_line_id" purpose="connect" />
```

It creates the link when the page renders, with `return_url` set to the current URL and `allowed_origins` to this
page's origin, so the hosted page returns here and reports its outcome only to this page. `purpose` is `connect`
(default), `add_channel`, `replace_device` (with `:replaces-device="$deviceId"`, the phone being replaced), `manage` or
`claim`. A partner key needs `:line`, one of its lines; an account key may leave it out when the key
reaches a single line. Every other attribute (`mode`, `label`, `height`, `timeout`, `class`) goes to the element.

A new link is made on every render, so mind the API's limits: at most 20 open links per line and 60 new ones per line per
hour, and `return_url` must be `https`. When MyComm refuses the link or cannot be reached, the page still renders: the
element comes without a `url`, so the widget shows its own `missing-url` error, and the reason is logged as a warning
("MyComm did not create the connect link: ..."). A missing `:line` with a partner key is a mistake in your template and
still throws.

Load the widget's script on the page (see its README for the pinned CDN tag with its integrity hash), and listen for
`mycomm-connect:completed`; confirm on your server with `MyComm::partner()->lines->status($lineId)` or the `line.*`
webhooks. The link is a credential for the line: the component never caches or logs it, and neither should you.

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

## Notifications

Send a Laravel notification as a text message with the `mycomm` channel:

```php
use Illuminate\Notifications\Notification;
use MyComm\Laravel\Notifications\MyCommMessage;

final class OrderShipped extends Notification
{
    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mycomm'];
    }

    public function toMyComm(object $notifiable): MyCommMessage
    {
        return MyCommMessage::create("Order {$this->order->number} has shipped.")
            ->category('transactional')
            ->referenceId("order-shipped-{$this->order->id}");
    }
}

$customer->notify(new OrderShipped($order));
```

The number comes from `routeNotificationForMyComm()` on the notifiable, else its `phone` attribute; a notifiable with no
number is skipped. `MyCommMessage` also has `contactId()`, `line()`, `template()`, `lane()`, `metadata()`, `sendAfter()`,
`expiresIn()` and `dryRun()`; it takes the same values as `messages->send()`.

The notification's id is sent as the `Idempotency-Key`, so a queued notification that is retried cannot send twice. When
MyComm refuses or cannot be reached, the SDK exception reaches your queue as usual (to retry or fail the job) after a
`MyComm\Laravel\Events\MyCommNotificationFailed` event is dispatched; its `summary` is a line fit for a log.

## Handling errors

Every exception is one of the SDK's, unchanged by the facade:

```php
use MyComm\Exceptions\ApiException;
use MyComm\Exceptions\InvalidArgumentException;

try {
    MyComm::messages()->send(to: $number, body: $text, category: 'marketing');
} catch (InvalidArgumentException $e) {
    // refused before sending: $e->field, $e->rule ("expires_in must be between 60 and 604800 (got 30).")
} catch (ApiException $e) {
    Log::warning($e->summary());           // code, status, message, details and request id in one line
    $e->code();                            // ErrorCode::RecipientOptedOut, or null for a newer code
    $e->docUrl();                          // the error reference page for the code
}
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
- **Duplicates** are filtered by event id for 7 days using your default cache store. If that store is
  `array` or per-process, switch it or set `MYCOMM_WEBHOOK_DEDUPLICATE=false` and de-duplicate yourself.
- **Make listeners idempotent.** An event is dispatched at least once, never zero times. When a synchronous
  listener throws, the route answers with an error, MyComm delivers the event again, and every listener
  runs again, including those that had succeeded. Queued listeners avoid this: a job that fails is
  retried by your queue alone.
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

`MyComm::fake()` replaces the client for the facade and for injection, `<x-mycomm-connect>` included (its link is
`partner.lines.links.create`, or `lines.links.create` with an account key). Nothing reaches the network; every
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
