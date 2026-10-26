<?php

declare(strict_types=1);

namespace MyComm\Laravel\Notifications;

use DateTimeInterface;

/**
 * The text message a notification sends through MyComm, built fluently in `toMyComm()`:
 *
 *     public function toMyComm(object $notifiable): MyCommMessage
 *     {
 *         return MyCommMessage::create("Order {$this->order->number} has shipped.")->category('transactional');
 *     }
 *
 * The recipient comes from `to()` or `contactId()` when you set one, otherwise from the notifiable's
 * `routeNotificationForMyComm()` (a phone number) or its `phone` attribute.
 */
final class MyCommMessage
{
    private ?string $to = null;

    private ?string $contactId = null;

    private ?string $inboxId = null;

    private ?string $body = null;

    private ?string $templateId = null;

    /** @var array<string, mixed>|null */
    private ?array $variables = null;

    private ?string $category = null;

    private ?string $lane = null;

    private ?string $referenceId = null;

    /** @var array<string, mixed>|null */
    private ?array $metadata = null;

    private DateTimeInterface|string|null $sendAfter = null;

    private ?int $expiresIn = null;

    private bool $dryRun = false;

    public function __construct(?string $body = null)
    {
        $this->body = $body;
    }

    public static function create(?string $body = null): self
    {
        return new self($body);
    }

    /** The recipient's address (a phone number in any accepted format). */
    public function to(string $address): self
    {
        $this->to = $address;

        return $this;
    }

    /** Send to a contact you already have in MyComm instead of a number. */
    public function contactId(string $contactId): self
    {
        $this->contactId = $contactId;

        return $this;
    }

    /** The inbox to send from; leave out when the key can use only one. */
    public function inbox(string $inboxId): self
    {
        $this->inboxId = $inboxId;

        return $this;
    }

    public function body(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Send a saved template instead of a body.
     *
     * @param  array<string, mixed>  $variables
     */
    public function template(string $templateId, array $variables = []): self
    {
        $this->templateId = $templateId;
        $this->variables = $variables === [] ? null : $variables;

        return $this;
    }

    /** `transactional` (the recipient expects it) or `marketing`. */
    public function category(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    /** `high`, `normal` or `bulk`. */
    public function lane(string $lane): self
    {
        $this->lane = $lane;

        return $this;
    }

    public function referenceId(string $referenceId): self
    {
        $this->referenceId = $referenceId;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function sendAfter(DateTimeInterface|string $when): self
    {
        $this->sendAfter = $when;

        return $this;
    }

    /** Drop the message if it has not been sent after this many seconds. */
    public function expiresIn(int $seconds): self
    {
        $this->expiresIn = $seconds;

        return $this;
    }

    /** Validate and compute without sending anything. */
    public function dryRun(bool $dryRun = true): self
    {
        $this->dryRun = $dryRun;

        return $this;
    }

    public function recipient(): ?string
    {
        return $this->to;
    }

    public function hasRecipient(): bool
    {
        return $this->to !== null || $this->contactId !== null;
    }

    /**
     * The arguments of `$client->messages->send()`, without the ones you did not set.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'to' => $this->to,
            'contact_id' => $this->contactId,
            'inbox_id' => $this->inboxId,
            'body' => $this->body,
            'template_id' => $this->templateId,
            'variables' => $this->variables,
            'category' => $this->category,
            'lane' => $this->lane,
            'reference_id' => $this->referenceId,
            'metadata' => $this->metadata,
            'send_after' => $this->sendAfter,
            'expires_in' => $this->expiresIn,
            'dry_run' => $this->dryRun ? true : null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
