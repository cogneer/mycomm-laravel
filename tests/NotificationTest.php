<?php

declare(strict_types=1);

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use MyComm\Exceptions\UnprocessableException;
use MyComm\Http\Request;
use MyComm\Laravel\Events\MyCommNotificationFailed;
use MyComm\Laravel\Facades\MyComm;
use MyComm\Laravel\Notifications\MyCommMessage;
use MyComm\Testing\FakeResponse;

final class Customer
{
    use Notifiable;

    public function __construct(public ?string $phone = '+923001234567') {}
}

final class OrderShipped extends Notification
{
    public function __construct(private readonly bool $withRecipient = false) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mycomm'];
    }

    public function toMyComm(object $notifiable): MyCommMessage
    {
        $message = MyCommMessage::create('Order 1094 has shipped.')->category('transactional')->referenceId('order-1094')->metadata(['order' => 1094]);

        return $this->withRecipient ? $message->to('+923331112222') : $message;
    }
}

it('sends a notification as a text message to the notifiable phone number', function () {
    $fake = MyComm::fake();

    (new Customer)->notify(new OrderShipped);

    $fake->assertCalledTimes('messages.send', 1)
        ->assertCalled('messages.send', fn (Request $request) => $request->json['to'] === '+923001234567'
            && $request->json['body'] === 'Order 1094 has shipped.'
            && $request->json['category'] === 'transactional'
            && $request->json['reference_id'] === 'order-1094'
            && $request->json['metadata'] === ['order' => 1094]);
});

it('uses the notification id as the idempotency key, so a retried job sends once', function () {
    $fake = MyComm::fake();
    $notification = new OrderShipped;
    $notification->id = '0198f3a2-7c41-7e0b-9d52-6a1f3b8c4e10';

    $customer = new Customer;
    app(ChannelManager::class)->sendNow($customer, $notification);

    $fake->assertCalled('messages.send', fn (Request $request) => $request->header('Idempotency-Key') === '0198f3a2-7c41-7e0b-9d52-6a1f3b8c4e10');
});

it('prefers the route of the notifiable and an explicit recipient over the phone attribute', function () {
    $fake = MyComm::fake();

    NotificationFacade::route('mycomm', '+923007654321')->notify(new OrderShipped);
    (new Customer)->notify(new OrderShipped(withRecipient: true));

    $recipients = array_map(fn (Request $request) => $request->json['to'], $fake->calls('messages.send'));
    expect($recipients)->toBe(['+923007654321', '+923331112222']);
});

it('skips a notifiable that has no number', function () {
    $fake = MyComm::fake();

    (new Customer(phone: null))->notify(new OrderShipped);

    $fake->assertNothingCalled();
});

it('reports a failure and lets it propagate', function () {
    Event::fake([MyCommNotificationFailed::class]);
    MyComm::fake()->respond('messages.send', FakeResponse::error(422, 'RECIPIENT_OPTED_OUT', 'The recipient +923001234567 opted out.'));

    try {
        (new Customer)->notify(new OrderShipped);
        $this->fail('The exception should reach the caller.');
    } catch (UnprocessableException $e) {
        expect($e->errorCode)->toBe('RECIPIENT_OPTED_OUT');
    }

    Event::assertDispatched(MyCommNotificationFailed::class, fn (MyCommNotificationFailed $event) => str_contains($event->summary, 'RECIPIENT_OPTED_OUT (422)') && $event->notifiable instanceof Customer);
});

it('builds the arguments of a send and leaves out what was not set', function () {
    $message = MyCommMessage::create()->contactId('0198f3a2-7c41-7e0b-9d52-6a1f3b8c4e10')->template('0198f3a2-7c41-7e0b-9d52-6a1f3b8c4e11', ['name' => 'Amina'])->lane('high')->expiresIn(600)->dryRun();

    expect($message->toArray())->toBe([
        'contact_id' => '0198f3a2-7c41-7e0b-9d52-6a1f3b8c4e10',
        'template_id' => '0198f3a2-7c41-7e0b-9d52-6a1f3b8c4e11',
        'variables' => ['name' => 'Amina'],
        'lane' => 'high',
        'expires_in' => 600,
        'dry_run' => true,
    ]);
});

it('says what a notification is missing', function () {
    MyComm::fake();
    $notification = new class extends Notification
    {
        /** @return list<string> */
        public function via(object $notifiable): array
        {
            return ['mycomm'];
        }
    };

    expect(fn () => (new AnonymousNotifiable)->route('mycomm', '+923001234567')->notify($notification))->toThrow(InvalidArgumentException::class, 'needs a toMyComm');
});
