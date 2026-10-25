<?php

declare(strict_types=1);

namespace MyComm\Laravel\Notifications;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;
use MyComm\Client;
use MyComm\Exceptions\MyCommException;
use MyComm\Laravel\Events\MyCommNotificationFailed;
use MyComm\Models\SendMessageDryRunResponse;
use MyComm\Models\SendMessageResponse;
use MyComm\RequestOptions;

/**
 * Sends a notification as a text message. Add `'mycomm'` to the notification's `via()` and a `toMyComm()` method that
 * returns a `MyCommMessage`.
 *
 * The notification's id is the `Idempotency-Key`, so a queued notification that is retried cannot send twice. A failure
 * throws the SDK's exception (so the queue retries or fails it as usual) after `MyCommNotificationFailed` is dispatched
 * with a line fit for a log.
 */
final class MyCommChannel
{
    public function __construct(private readonly Client $client, private readonly Dispatcher $events) {}

    public function send(object $notifiable, Notification $notification): SendMessageResponse|SendMessageDryRunResponse|null
    {
        if (! method_exists($notification, 'toMyComm')) {
            throw new InvalidArgumentException(sprintf('%s needs a toMyComm($notifiable) method returning a %s to be sent through MyComm.', $notification::class, MyCommMessage::class));
        }

        $message = $notification->toMyComm($notifiable);
        if (! $message instanceof MyCommMessage) {
            throw new InvalidArgumentException(sprintf('%s::toMyComm() must return a %s.', $notification::class, MyCommMessage::class));
        }

        $arguments = $message->toArray();
        if (! $message->hasRecipient()) {
            $route = $this->route($notifiable, $notification);
            if ($route === null) {
                // Nothing to send to is not an error of the API: the notifiable simply has no number.
                return null;
            }
            $arguments['to'] = $route;
        }

        // Set by the notification sender; a notification sent by hand has none, and the SDK then makes a key.
        /** @phpstan-ignore isset.property */
        $key = isset($notification->id) ? (string) $notification->id : null;

        try {
            return $this->client->messages->send(...$arguments, requestOptions: new RequestOptions(idempotencyKey: $key));
        } catch (MyCommException $e) {
            $this->events->dispatch(new MyCommNotificationFailed($notifiable, $notification, $e));

            throw $e;
        }
    }

    private function route(object $notifiable, Notification $notification): ?string
    {
        $route = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('mycomm', $notification)
            : null;
        $route ??= $notifiable->phone ?? null;

        return is_string($route) && trim($route) !== '' ? $route : null;
    }
}
