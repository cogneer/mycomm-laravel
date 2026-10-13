<?php

declare(strict_types=1);

return [

    /*
    | An account key (mc_live_...) or a partner key (mc_partner_...), created in the MyComm portal under
    | Developers > API keys. Keep it out of version control.
    */
    'api_key' => env('MYCOMM_API_KEY'),

    /*
    | Origin of the API, without /v1. Change it only to reach a staging environment.
    */
    'base_url' => env('MYCOMM_BASE_URL', 'https://mycomm.cogneer.tech'),

    /*
    | Retries after a network error, a 429 or a 5xx. Every POST carries an Idempotency-Key, so a retry
    | never sends a message twice.
    */
    'max_retries' => (int) env('MYCOMM_MAX_RETRIES', 2),

    /*
    | Seconds per attempt.
    */
    'timeout' => (float) env('MYCOMM_TIMEOUT', 30),

    'webhook' => [

        /*
        | Signing secret of your webhook endpoint, shown once when the endpoint is created or its secret
        | rotated. While you roll over to a new secret, list both, separated by a comma.
        */
        'secret' => env('MYCOMM_WEBHOOK_SECRET'),

        /*
        | Seconds a signature stays acceptable.
        */
        'tolerance' => (int) env('MYCOMM_WEBHOOK_TOLERANCE', 300),

        /*
        | MyComm delivers at least once, so the same event can arrive twice. With this on, an event id seen
        | in the last 7 days is acknowledged without dispatching it again. Uses the default cache store.
        */
        'deduplicate' => (bool) env('MYCOMM_WEBHOOK_DEDUPLICATE', true),

    ],

];
