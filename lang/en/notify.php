<?php

return [
    'page_title' => 'WhatsApp Notify',
    'breadcrumb' => 'WhatsApp Notify',
    'title'      => 'WhatsApp Notify Service',
    'subtitle'   => 'Like SMTP for email — your website POSTs a message and the admin receives it on WhatsApp.',

    'instance_connected'    => 'Sending via :name',
    'no_connected_instance' => 'No connected WhatsApp instance — connect one to enable delivery.',

    'config_title'    => 'Configuration',
    'config_subtitle' => 'Where notifications go and how they look.',
    'enable_label'    => 'Enable Notify API',
    'enable_hint'     => 'When off, /api/notify/send returns 403.',

    'admin_phone_label'   => 'Admin WhatsApp number',
    'admin_phone_hint'    => 'E.164 format with country code (e.g. +212600000000). All notifications are delivered here.',
    'admin_phone_invalid' => 'Enter a valid phone number in international format (7–15 digits, optional leading +).',

    'prefix_label' => 'Message prefix (optional)',
    'prefix_hint'  => 'Prepended to every notification, e.g. [Wavadesk]. Leave empty for none.',

    'save'  => 'Save changes',
    'saved' => 'Notify settings saved.',

    'credentials_title'    => 'API credentials',
    'credentials_subtitle' => 'Use these from your website or backend.',
    'endpoint_label'       => 'Endpoint',
    'api_key_label'        => 'X-Api-Key header value',
    'api_key_hint'         => 'Treat as a password.',
    'regenerate_from_profile' => 'Regenerate from Profile',
    'no_api_key'           => 'You do not have an API key yet.',
    'generate_api_key'     => 'Generate one from Profile.',

    'test_title'       => 'Send a test',
    'test_subtitle'    => 'Fire a notification right now to your admin number.',
    'test_placeholder' => 'Message body…',
    'test_default'     => 'Test notification from WavaDesk.',
    'test_button'      => 'Send test notification',
    'test_sent'        => 'Test notification queued. Check your WhatsApp.',
    'test_failed'      => 'Test notification failed. Check the settings above.',

    'integration_title'    => 'Integration',
    'integration_subtitle' => 'Copy-paste into your website to send order/event notifications.',
    'laravel_hint'         => 'Store the API key in .env as WAVADESK_API_KEY.',

    'response_ref_title' => 'Response reference',
    'response_ref_ok'    => 'true on success, false otherwise. Errors include a message.',

    'recent_title'    => 'Recent notifications',
    'recent_subtitle' => 'Last 20 messages delivered by this service.',
    'col_sent_at'     => 'Sent at',
    'col_message'     => 'Message',
    'col_status'      => 'Status',
];
