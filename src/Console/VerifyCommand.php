<?php

declare(strict_types=1);

namespace MyComm\Laravel\Console;

use Illuminate\Console\Command;
use MyComm\Client;

/**
 * `php artisan mycomm:verify --to=+923001234567`: checks the key and dry-runs a send. Sends nothing. Exit code 1 when a check
 * fails, so it can sit in a deploy script.
 */
final class VerifyCommand extends Command
{
    protected $signature = 'mycomm:verify {--to= : A number you control, to dry-run a send to} {--inbox= : The inbox to send from, when the key can use several}';

    protected $description = 'Check the MyComm API key and, with --to, that a message would be accepted (nothing is sent)';

    public function handle(Client $client): int
    {
        $to = $this->option('to');
        $inbox = $this->option('inbox');
        $result = $client->verify(is_string($to) && $to !== '' ? $to : null, is_string($inbox) && $inbox !== '' ? $inbox : null);

        foreach ($result->checks as $check) {
            $this->line(($check['ok'] ? '<info>PASS</info>' : '<error>FAIL</error>').'  '.$check['name'].': '.$check['message']);
        }
        if ($result->holdReason !== null) {
            $this->line('<comment>NOTE</comment>  a message to this number would wait: '.$result->holdReason.' (see https://mycomm.cogneer.tech/docs/guides/message-lifecycle)');
        }
        if (! $result->ok()) {
            $this->newLine();
            $this->error('MyComm is not ready: fix the failed check above. A dry run does not prove the phone is online or the carrier delivers.');

            return self::FAILURE;
        }
        if ($to === null || $to === '') {
            $this->line('Pass --to=<a number you control> to dry-run a send as well.');
        }

        return self::SUCCESS;
    }
}
