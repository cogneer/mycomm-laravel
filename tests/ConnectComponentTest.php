<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\View\ViewException;
use MyComm\Exceptions\MyCommException;
use MyComm\Http\Request;
use MyComm\Laravel\Facades\MyComm;
use MyComm\Testing\Examples;

beforeEach(function () {
    Route::get('settings/messaging', fn () => Blade::render(request()->query('blade', '<x-mycomm-connect :line="$lineId" purpose="connect" />'), ['lineId' => 'line-1']));
});

it('renders the widget with a connect link for the partner line, made for the current page', function () {
    config(['mycomm.api_key' => 'mc_partner_test']);
    $fake = MyComm::fake();

    $html = $this->get('https://erp.test/settings/messaging?tab=sms')->assertOk()->getContent();

    $url = Examples::RESPONSES['partner.lines.links.create']['body']['url'];
    expect($html)->toContain('<mycomm-connect url="'.e($url).'" purpose="connect"');
    $fake->assertCalledTimes('partner.lines.links.create', 1)
        ->assertCalled('partner.lines.links.create', fn (Request $request) => $request->path === '/partner/v1/lines/line-1/links'
            && $request->json === [
                'purpose' => 'connect',
                'return_url' => 'https://erp.test/settings/messaging?tab=sms',
                'allowed_origins' => ['https://erp.test'],
            ]);
});

it('passes the purpose and the widget attributes through', function () {
    config(['mycomm.api_key' => 'mc_partner_test']);
    $fake = MyComm::fake();

    $html = $this->get('https://erp.test/settings/messaging?'.http_build_query([
        'blade' => '<x-mycomm-connect line="line-9" purpose="add_channel" mode="inline" label="Add a SIM" />',
    ]))->assertOk()->getContent();

    expect($html)->toContain('purpose="add_channel"')
        ->and($html)->toContain('mode="inline"')
        ->and($html)->toContain('label="Add a SIM"');
    $fake->assertCalled('partner.lines.links.create', fn (Request $request) => $request->path === '/partner/v1/lines/line-9/links'
        && $request->json['purpose'] === 'add_channel');

    $this->get('https://erp.test/settings/messaging?'.http_build_query([
        'blade' => '<x-mycomm-connect line="line-9" purpose="replace_device" replaces-device="0198f3a2-7c41-7e0b-9d52-6a1f3b8c4e10" />',
    ]))->assertOk();
    $fake->assertCalled('partner.lines.links.create', fn (Request $request) => $request->json['purpose'] === 'replace_device'
        && $request->json['replaces_device_id'] === '0198f3a2-7c41-7e0b-9d52-6a1f3b8c4e10');
});

it('needs a line with a partner key', function () {
    config(['mycomm.api_key' => 'mc_partner_test']);
    $fake = MyComm::fake();

    $this->withoutExceptionHandling();

    try {
        $this->get('https://erp.test/settings/messaging?'.http_build_query(['blade' => '<x-mycomm-connect purpose="connect" />']));
        $this->fail('The component rendered without a line.');
    } catch (ViewException $e) {
        expect($e->getPrevious())->toBeInstanceOf(MyCommException::class)
            ->and($e->getPrevious()?->getMessage())->toStartWith('A partner key needs the line');
    }
    $fake->assertNothingCalled();
});

it('makes the link with an account key on the given line, or on its only line', function () {
    $fake = MyComm::fake();
    $fake->respond('lines.list', ['data' => [Examples::RESPONSES['lines.get']['body']], 'next_cursor' => null]);

    $this->get('https://app.test/settings/messaging')->assertOk();
    $this->get('https://app.test/settings/messaging?'.http_build_query(['blade' => '<x-mycomm-connect />']))->assertOk();

    $calls = $fake->calls('lines.links.create');
    expect($calls)->toHaveCount(2)
        ->and($calls[0]->path)->toBe('/v1/lines/line-1/links')
        ->and($calls[0]->header('MyComm-Line'))->toBeNull()
        ->and($calls[1]->path)->toBe('/v1/lines/'.Examples::RESPONSES['lines.get']['body']['id'].'/links')
        ->and($calls[1]->json['purpose'])->toBe('connect');
    $fake->assertNotCalled('partner.lines.links.create');
});
