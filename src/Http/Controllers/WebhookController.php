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
use Throwable;

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

        $key = 'mycomm:webhook:'.$event->id;
        $deduplicate = (bool) $config->get('mycomm.webhook.deduplicate', true);

        // add() is atomic: of two deliveries of one event that race, only one dispatches.
        if ($deduplicate && ! $cache->add($key, true, self::REMEMBER_SECONDS)) {
            return new Response('', 204);
        }

        try {
            $events->dispatch($event);
            $events->dispatch(new WebhookReceived($event));
        } catch (Throwable $e) {
            // A listener failed, so this answers with an error and MyComm delivers the event again. That
            // delivery must not be taken for a duplicate, or the event would be lost.
            if ($deduplicate) {
                $cache->forget($key);
            }

            throw $e;
        }

        return new Response('', 204);
    }
}
