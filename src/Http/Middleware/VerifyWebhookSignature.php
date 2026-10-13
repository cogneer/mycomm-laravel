<?php

declare(strict_types=1);

namespace MyComm\Laravel\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use MyComm\Exceptions\MyCommException;
use MyComm\Exceptions\WebhookSignatureException;
use MyComm\Webhooks\Event;
use MyComm\Webhooks\Webhook;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through only when MyComm signed its body, and attaches the typed event to it.
 */
final class VerifyWebhookSignature
{
    /** Request attribute that holds the verified event. */
    public const EVENT = 'mycomm.event';

    public function __construct(private readonly Repository $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secrets = array_values(array_filter(array_map('trim', explode(',', (string) $this->config->get('mycomm.webhook.secret')))));
        if ($secrets === []) {
            throw new MyCommException('Set MYCOMM_WEBHOOK_SECRET to the signing secret of your MyComm webhook endpoint.');
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->headers->get(Webhook::SIGNATURE_HEADER),
                $secrets,
                (int) $this->config->get('mycomm.webhook.tolerance', Webhook::DEFAULT_TOLERANCE),
            );
        } catch (WebhookSignatureException $e) {
            abort(400, $e->getMessage());
        }

        $request->attributes->set(self::EVENT, $event);

        return $next($request);
    }

    /**
     * The event of a request that passed this middleware.
     */
    public static function event(Request $request): Event
    {
        $event = $request->attributes->get(self::EVENT);
        if (! $event instanceof Event) {
            throw new MyCommException('This request did not pass the MyComm webhook signature check.');
        }

        return $event;
    }
}
