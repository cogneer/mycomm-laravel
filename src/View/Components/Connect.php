<?php

declare(strict_types=1);

namespace MyComm\Laravel\View\Components;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Component;
use MyComm\Client;
use MyComm\Exceptions\ApiException;
use MyComm\Exceptions\ConnectionException;
use MyComm\Exceptions\MyCommException;

/**
 * `<x-mycomm-connect :line="$lineId" purpose="connect" />`: the `@mycomm/connect` widget with a hosted link made for
 * this page view. The link returns to the current URL and reports only to this page's origin. A partner key needs
 * `:line` (one of its lines); an account key may leave it out when it reaches a single line. `purpose="replace_device"`
 * needs `:replaces-device` (the phone being replaced). Any other attribute (`mode`, `label`, `height`, `timeout`,
 * `class`, ...) is passed to the element. Load the widget's script on the page.
 *
 * When MyComm refuses the link or cannot be reached (too many open links for the line, a rate limit, an `http` page URL
 * as `return_url`), the page still renders: the element comes without a `url`, so the widget shows its own error, and
 * the reason is logged as a warning.
 */
final class Connect extends Component
{
    public readonly ?string $url;

    public function __construct(
        Client $client,
        Request $request,
        public readonly ?string $line = null,
        public readonly string $purpose = 'connect',
        ?string $replacesDevice = null,
    ) {
        $options = [
            'purpose' => $purpose,
            'return_url' => $request->fullUrl(),
            'allowed_origins' => [$request->getSchemeAndHttpHost()],
        ];
        if ($replacesDevice !== null) {
            $options['replaces_device_id'] = $replacesDevice;
        }
        $line = $line === '' ? null : $line;

        if ($client->isPartnerKey() && $line === null) {
            throw new MyCommException('A partner key needs the line: <x-mycomm-connect :line="$lineId" />.');
        }

        try {
            $this->url = $client->isPartnerKey()
                ? $client->partner->lines->links->create((string) $line, ...$options)->url
                : $client->lines->links->create($line ?? self::onlyLine($client), ...$options)->url;
        } catch (ApiException|ConnectionException $e) {
            Log::warning('MyComm did not create the connect link: '.$e->getMessage(), ['line' => $line, 'purpose' => $purpose]);
            $this->url = null;
        }
    }

    public function render(): string
    {
        return '<mycomm-connect{!! $urlAttribute() !!} purpose="{{ $purpose }}" {{ $attributes }}></mycomm-connect>';
    }

    /**
     * ` url="..."`, escaped, or nothing when there is no link.
     */
    public function urlAttribute(): string
    {
        return $this->url === null ? '' : ' url="'.e($this->url).'"';
    }

    private static function onlyLine(Client $client): string
    {
        $lines = $client->lines->list(limit: 2)->data;
        if (count($lines) !== 1) {
            throw new MyCommException('The key reaches '.(count($lines) === 0 ? 'no line' : 'several lines').': pass the line, <x-mycomm-connect :line="$lineId" />.');
        }

        return $lines[0]->id;
    }
}
