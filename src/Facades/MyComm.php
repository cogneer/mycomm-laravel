<?php

declare(strict_types=1);

namespace MyComm\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use MyComm\Client;
use MyComm\Laravel\MyCommManager;
use MyComm\Testing\MyCommFake;

/**
 * @method static \MyComm\Client client()
 * @method static \MyComm\Client forAccount(string $accountId)
 * @method static \MyComm\Resources\Account account()
 * @method static \MyComm\Resources\ApiKeys apiKeys()
 * @method static \MyComm\Resources\Channels channels()
 * @method static \MyComm\Resources\ConsentEvents consentEvents()
 * @method static \MyComm\Resources\ContactFields contactFields()
 * @method static \MyComm\Resources\Contacts contacts()
 * @method static \MyComm\Resources\Conversations conversations()
 * @method static \MyComm\Resources\Devices devices()
 * @method static \MyComm\Resources\Exports exports()
 * @method static \MyComm\Resources\Groups groups()
 * @method static \MyComm\Resources\Imports imports()
 * @method static \MyComm\Resources\Inboxes inboxes()
 * @method static \MyComm\Resources\Media media()
 * @method static \MyComm\Resources\Messages messages()
 * @method static \MyComm\Resources\PairingCodes pairingCodes()
 * @method static \MyComm\Resources\Partner partner()
 * @method static \MyComm\Resources\SmartLists smartLists()
 * @method static \MyComm\Resources\Stats stats()
 * @method static \MyComm\Resources\Suppressions suppressions()
 * @method static \MyComm\Resources\WebhookDeliveries webhookDeliveries()
 * @method static \MyComm\Resources\WebhookEndpoints webhookEndpoints()
 *
 * @see MyCommManager
 */
final class MyComm extends Facade
{
    /**
     * Replaces the client with one that never touches the network, here and wherever it is injected.
     *
     *     $fake = MyComm::fake();
     *     ...
     *     $fake->assertCalled('messages.send');
     */
    public static function fake(): MyCommFake
    {
        $fake = new MyCommFake;

        self::getFacadeApplication()?->instance(Client::class, $fake->client());
        self::swap(new MyCommManager($fake->client()));

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return MyCommManager::class;
    }
}
