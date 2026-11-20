# Changelog

All notable changes to this package are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package follows
[semantic versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.0] - 2026-11-21

For `mycomm/mycomm-php` 2.2 and the API `2.1.0`: WhatsApp numbers, linked as a device of the phone, through the same
channel endpoints as every other type.

### Added

- Through the SDK: `MyComm::channels()->create(type: 'whatsapp_web', connect: ...)`, `connectState($id)` and the new
  `continueConnect($id, action: 'retry' | 'switch_method' | 'relink')`; the error codes `CHANNEL_LIMIT_REACHED`,
  `CHANNEL_LINK_BLOCKED`, `CHANNEL_LINK_BUSY`, `CHANNEL_NO_CAPACITY`, `CHANNEL_SERVER_UNAVAILABLE` and
  `CHANNEL_NUMBER_IN_USE`; `channel_options` keyed by channel type; media uploads of more types.

### Changed

- Requires `mycomm/mycomm-php` `^2.2`.
- The channel type `whatsapp_evolution` is now `whatsapp_web`. It was a reserved value that the API never offered.
- `429 CHANNEL_LINK_BLOCKED` is thrown at once instead of being retried: the block lasts minutes or hours.

## [2.1.0] - 2026-11-09

For `mycomm/mycomm-php` 2.1. Published 2.0.0 still used `account_id` and `default_line_id`; the SDK now follows the
API and renames them, so code that reads either field has to change when it moves to 2.1.0.

### Changed

- Requires `mycomm/mycomm-php` `^2.1`.
- `account_id` is now `user_id`. The facade documents `me()` (`GET`/`PATCH /v1/me`) instead of `account()`, as the SDK
  renamed it.
- `default_line_id` is now `direct_line_id`: every number has its own direct line.
- A direct line sends only through its own number: setting any other senders on it, or switching its own number off
  or turning `mirror_inbound` on, answers `409 INVALID_STATE`.
- `from` prefers the live number: when the number also has a disconnected copy, the live one sends; `LINE_ARCHIVED`
  answers only when no live copy exists.

## [2.0.0] - 2026-11-02

For `mycomm/mycomm-php` 2.0 and the API `2.0.0`, where lines replace inboxes and partners give each user a line.
Nothing from 1.x is kept as an alias.

### Changed (breaking)

- Requires `mycomm/mycomm-php` `^2.0`.
- `MyCommMessage::line($lineId)` replaces `inbox()`; the message sends `line_id` instead of `inbox_id`.
- `php artisan mycomm:verify --line=` replaces `--inbox=`; `MyComm::verify(?string $to, ?string $lineId)`.
- The facade documents `lines()` instead of `inboxes()`.
- `MyComm::forLine($lineId)` replaces `forAccount()`: a partner key sends as one of its lines (`MyComm-Line`).
- The facade no longer documents `connectSessions()`, which the SDK removed; it documents `links()`.

### Added

- `MyCommMessage::prefer('whatsapp')`: the channel type the line tries first for this message (`sms` or `whatsapp`,
  or a `MyComm\Enums\MessagePrefer`); it beats the line's own preference.
- `<x-mycomm-connect :line="$lineId" purpose="connect" />`: renders the `@mycomm/connect` widget with a hosted link
  made for the page view (`return_url` the current URL, `allowed_origins` the page's origin). A partner key needs
  `:line`; an account key may leave it out when it reaches one line. `purpose` is `connect`, `add_channel`,
  `replace_device` (with `:replaces-device`), `manage` or `claim`; other attributes go to the element.
  `MyComm::fake()` covers it. When the API refuses the link, the element renders without a `url` and a warning is logged,
  instead of the page failing.
- `php artisan mycomm:verify` and `MyComm::verify()` work with a partner key: the key is checked on the partner API and
  the send dry-run through `--line`.
- `MyComm::fake()` fakes a partner key when `mycomm.api_key` is one.
- Requires `illuminate/view`, for the component.

## [1.0.0] - 2026-10-26

The first stable release, for `mycomm/mycomm-php` 1.0 and the frozen API `1.0.0`. From here the package follows semantic
versioning: `1.x` releases only add. 0.2.0 was never released; its changes are here.

### Added

- `mycomm` notification channel: `MyCommMessage` and `MyCommChannel`. The notification id is the idempotency key. A
  failure dispatches `MyCommNotificationFailed` (with the SDK's `summary()`) and rethrows.
- `php artisan mycomm:verify {--to=} {--inbox=}` and `MyComm::verify()`: checks the key and dry-runs a send, sends
  nothing, exits 1 when a check fails.
- `mycomm.validate` (`MYCOMM_VALIDATE`, default on): the SDK's checks before sending.
- The facade documents `automationRules()`, `automationRuns()`, `campaigns()`, `connectSessions()` and `templates()`.
- An environment variable table, error handling and notification sections in the README.

### Changed

- Requires `mycomm/mycomm-php` `^1.0`. `MessageStatus` and the other message enums are typed in the SDK now, so compare
  `$event->data->message->status` with `MessageStatus::Delivered`.

## [0.1.0] - 2026-10-14

First release, for `mycomm/mycomm-php` 0.1 and Laravel 12 and 13.

### Added

- Service provider that builds `MyComm\Client` from `config/mycomm.php` and the environment.
- `MyComm` facade with every resource of the client and `MyComm::fake()` for tests.
- `Route::mycommWebhooks()`: a signed webhook receiver that de-duplicates deliveries and dispatches each
  event as a Laravel event, plus `WebhookReceived` for all of them.
- `VerifyWebhookSignature` middleware for custom receivers.
