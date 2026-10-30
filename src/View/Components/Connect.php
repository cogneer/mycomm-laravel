<?php

declare(strict_types=1);

namespace MyComm\Laravel\View\Components;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\View\Component;
use MyComm\Client;
use MyComm\Exceptions\MyCommException;

/**
 * `<x-mycomm-connect :line="$lineId" purpose="connect" />`: the `@mycomm/connect` widget with a hosted link made for
 * this page view. The link returns to the current URL and reports only to this page's origin. A partner key needs
 * `:line` (one of its lines); an account key may leave it out when it reaches a single line. `purpose="replace_device"`
 * needs `:replaces-device` (the phone being replaced). Any other attribute
 * (`mode`, `label`, `height`, `timeout`, `class`, ...) is passed to the element. Load the widget's script on the page.
 */
final class Connect extends Component
{
    public readonly string $url;

    public function __construct(
        Client $client,
        Config $config,
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

        if (str_starts_with((string) $config->get('mycomm.api_key'), 'mc_partner_')) {
            if ($line === null || $line === '') {
                throw new MyCommException('A partner key needs the line: <x-mycomm-connect :line="$lineId" />.');
            }
            $this->url = $client->partner->lines->links->create($line, ...$options)->url;

            return;
        }

        $this->url = $client->lines->links->create($line ?? self::onlyLine($client), ...$options)->url;
    }

    public function render(): string
    {
        return '<mycomm-connect url="{{ $url }}" purpose="{{ $purpose }}" {{ $attributes }}></mycomm-connect>';
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
