<?php

return [
    'base_url'      => env('FLOUCI_BASE_URL', 'https://developers.flouci.com/api/v2'),
    'public_token'  => env('FLOUCI_PUBLIC_TOKEN', ''),
    'private_token' => env('FLOUCI_PRIVATE_TOKEN', ''),
    'success_url'   => env('FLOUCI_SUCCESS_URL'),
    'fail_url'      => env('FLOUCI_FAIL_URL'),
    'webhook_url'   => env('FLOUCI_WEBHOOK_URL'),
];
