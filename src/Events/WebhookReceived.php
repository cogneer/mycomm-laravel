<?php

declare(strict_types=1);

namespace MyComm\Laravel\Events;

use MyComm\Webhooks\Event;

/**
 * Dispatched for every verified webhook, after the event itself. Listen to this one to handle all events
 * in one place, including types this SDK version does not know (`MyComm\Webhooks\UnknownEvent`).
 */
final class WebhookReceived
{
    public function __construct(public readonly Event $event) {}
}
