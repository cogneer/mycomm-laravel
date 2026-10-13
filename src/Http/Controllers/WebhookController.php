<?php

declare(strict_types=1);

namespace MyComm\Laravel\Http\Controllers;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use MyComm\Laravel\Events\WebhookReceived;
use MyComm\Laravel\Http\Middleware\VerifyWebhookSignature;

/**
 * Turns a verified MyComm webhook into Laravel events and acknowledges it.
 *
 * Listen for the event class you care about (`MyComm\Webhooks\Events\MessageDelivered`), or for
 * `WebhookReceived` to see all of them. Make slow listeners queued: MyComm waits 10 seconds for the answer.
 */
final class WebhookController
{
    /** MyComm keeps retrying a delivery for about a day and a half; a week covers replays too. */
    private const REMEMBER_SECONDS = 604800;

    public function __invoke(Request $request, Dispatcher $events, Config $config, Cache $cache): Response
    {
        $event = VerifyWebhookSignature::event($request);

        // add() is atomic: of two deliveries of one event that race, only one dispatches.
        if ($config->get('mycomm.webhook.deduplicate', true) && ! $cache->add('mycomm:webhook:'.$event->id, true, self::REMEMBER_SECONDS)) {
            return new Response('', 204);
        }

        $events->dispatch($event);
        $events->dispatch(new WebhookReceived($event));

        return new Response('', 204);
    }
}
