# Changelog

All notable changes to this package are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package follows
[semantic versioning](https://semver.org/spec/v2.0.0.html).

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
