<?php

return [
    'page_title' => 'OTP Service',
    'breadcrumb' => 'OTP Service',
    'title'      => 'WhatsApp OTP Service',
    'subtitle'   => 'Send verification codes via WhatsApp from your own Laravel or web/API project.',

    'instance_connected'   => 'Sending via :name',
    'no_connected_instance'=> 'No connected WhatsApp instance — connect one to enable delivery.',

    'config_title'    => 'Configuration',
    'config_subtitle' => 'Controls how OTP codes are generated and delivered.',
    'enable_label'    => 'Enable OTP API',
    'enable_hint'     => 'When off, /api/otp/send returns 403.',
    'code_length_label'=> 'Code length',
    'digits'          => 'digits',
    'ttl_label'       => 'Validity (minutes)',
    'ttl_hint'        => 'Between 1 and 60. Applied to newly issued codes only.',
    'template_label'  => 'Message template',
    'template_hint'   => 'Use {code} for the code and {ttl} for the validity in minutes. {code} is required.',
    'save'            => 'Save changes',
    'saved'           => 'OTP settings saved.',
    'template_missing_code' => 'The message template must contain the {code} placeholder.',

    'credentials_title'   => 'API credentials',
    'credentials_subtitle'=> 'Use these to authenticate calls from your other project.',
    'base_url_label'      => 'Base URL',
    'api_key_label'       => 'X-Api-Key header value',
    'api_key_hint'        => 'Treat as a password.',
    'regenerate_from_profile' => 'Regenerate from Profile',
    'no_api_key'          => 'You do not have an API key yet.',
    'generate_api_key'    => 'Generate one from Profile.',

    'integration_title'   => 'Integration',
    'integration_subtitle'=> 'Copy-paste snippets to call the service from your other project.',
    'send_code'           => 'Send an OTP',
    'verify_code'         => 'Verify a code',
    'laravel_hint'        => 'Requires guzzlehttp/guzzle. Store the API key in .env as WAVADESK_API_KEY.',

    'response_ref_title'  => 'Response reference',
    'response_ref_ok'     => 'true on success, false otherwise. Errors include a message.',
    'response_ref_retry'  => 'Seconds to wait before retrying (only on cooldown error, HTTP 429).',
];
