<?php

declare(strict_types=1);

namespace MyComm\Laravel;

use BadMethodCallException;
use MyComm\Client;
use MyComm\VerifyResult;

/**
 * What the `MyComm` facade talks to: each resource of the client as a method.
 *
 *     MyComm::messages()->send(to: '+923001234567', body: 'Hi');
 */
final class MyCommManager
{
    public function __construct(private readonly Client $client) {}

    public function client(): Client
    {
        return $this->client;
    }

    /**
     * Checks the key and, with a number, dry-runs a send. Sends nothing. See `Client::verify()`.
     */
    public function verify(?string $to = null, ?string $inboxId = null): VerifyResult
    {
        return $this->client->verify($to, $inboxId);
    }

    /**
     * A client that acts as one managed account. For partner keys.
     */
    public function forAccount(string $accountId): Client
    {
        return $this->client->forAccount($accountId);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $resource, array $arguments): object
    {
        if (! property_exists($this->client, $resource)) {
            throw new BadMethodCallException("MyComm has no resource named {$resource}.");
        }

        return $this->client->{$resource};
    }
}
