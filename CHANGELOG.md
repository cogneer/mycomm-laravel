# Changelog

All notable changes to this package are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package follows
[semantic versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- A webhook whose listener threw is no longer remembered as handled, so the delivery MyComm retries is
  dispatched instead of being skipped as a duplicate.

## [0.1.0] - 2026-10-14

First release, for `mycomm/mycomm-php` 0.1 and Laravel 12 and 13.

### Added

- Service provider that builds `MyComm\Client` from `config/mycomm.php` and the environment.
- `MyComm` facade with every resource of the client and `MyComm::fake()` for tests.
- `Route::mycommWebhooks()`: a signed webhook receiver that de-duplicates deliveries and dispatches each
  event as a Laravel event, plus `WebhookReceived` for all of them.
- `VerifyWebhookSignature` middleware for custom receivers.
