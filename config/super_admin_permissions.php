<?php

/*
 * Permission slugs that can be assigned to super admin accounts.
 * null sidebar_permissions = full access (master).
 * An array = restricted to listed slugs.
 *
 * Only the control panel's own sections appear here. Tenant-operational
 * management (instances, users, teams) is not part of the platform panel.
 */
return [
    'platform_tenants'       => ['icon' => 'ri-building-2-line',     'group' => 'platform'],
    'platform_plans'         => ['icon' => 'ri-price-tag-3-line',    'group' => 'platform'],
    'platform_conversation_settings' => ['icon' => 'ri-timer-flash-line', 'group' => 'platform'],
    'platform_system_health' => ['icon' => 'ri-pulse-line',          'group' => 'platform'],
    'platform_legal_pages'   => ['icon' => 'ri-file-shield-2-line',  'group' => 'platform'],
    'conversations'          => ['icon' => 'ri-message-3-line',      'group' => 'content'],
    'customers'              => ['icon' => 'ri-contacts-line',       'group' => 'content'],
    'reports'                => ['icon' => 'ri-bar-chart-2-line',    'group' => 'account'],
    'audit_log'              => ['icon' => 'ri-file-list-3-line',    'group' => 'account'],
    'billing'                => ['icon' => 'ri-bank-card-line',      'group' => 'account'],
    'notifications'          => ['icon' => 'ri-notification-3-line', 'group' => 'account'],
];
