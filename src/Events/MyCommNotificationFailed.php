<?php

declare(strict_types=1);

namespace MyComm\Laravel\Events;

use Illuminate\Notifications\Notification;
use MyComm\Exceptions\ApiException;
use MyComm\Exceptions\MyCommException;

/**
 * Dispatched when sending a notification through MyComm failed, just before the exception is rethrown. Listen for it to log
 * `summary` or to mark the notifiable as unreachable.
 */
final class MyCommNotificationFailed
{
    /** One line with the code, status, message, details and request id (the SDK's `summary()`), or the message of a local failure. */
    public readonly string $summary;

    public function __construct(
        public readonly object $notifiable,
        public readonly Notification $notification,
        public readonly MyCommException $exception,
    ) {
        $this->summary = $exception instanceof ApiException ? $exception->summary() : $exception->getMessage();
    }
}
