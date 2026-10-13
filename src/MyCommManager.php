<?php

declare(strict_types=1);

namespace MyComm\Laravel;

use BadMethodCallException;
use MyComm\Client;

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
