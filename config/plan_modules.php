<?php

/*
 * Modules a subscription plan can grant. The super admin ticks these per plan
 * on /admin-control-panel/platform/plans; Plan::hasModule() reads them and the
 * `module` middleware enforces them on the tenant panel.
 *
 * A plan whose `modules` column is NULL predates this list and is treated as
 * granting everything, so an un-migrated row never locks a tenant out.
 *
 * `always` marks a module that cannot be switched off — the product does not
 * function without it — so the form renders it ticked and disabled.
 *
 * `teaser` keeps the module in the sidebar for tenants whose plan does NOT
 * include it: the entry renders locked, and opening it shows a blurred preview
 * of the page behind an upgrade prompt instead of bouncing to the dashboard.
 * Use it for modules worth selling, not for every gap.
 */
return [
    'whatsapp'       => ['icon' => 'ri-whatsapp-line',       'group' => 'channels',   'always' => true],
    'webchat'        => ['icon' => 'ri-chat-smile-2-line',   'group' => 'channels',   'teaser' => true],
    'messenger'      => ['icon' => 'ri-messenger-line',      'group' => 'channels',   'teaser' => true],
    'ai_agent'       => ['icon' => 'ri-sparkling-2-line',    'group' => 'automation'],
    'knowledge_base' => ['icon' => 'ri-book-2-line',         'group' => 'automation'],
    'saved_replies'  => ['icon' => 'ri-chat-quote-line',     'group' => 'automation'],
    'reservations'   => ['icon' => 'ri-calendar-check-line', 'group' => 'modules',    'teaser' => true],
    'otp_service'    => ['icon' => 'ri-shield-keyhole-line', 'group' => 'modules'],
    'teams'          => ['icon' => 'ri-team-line',           'group' => 'workspace',  'teaser' => true],
    'reports'        => ['icon' => 'ri-bar-chart-2-line',    'group' => 'insights'],
    'audit_log'      => ['icon' => 'ri-file-list-3-line',    'group' => 'insights'],
    'api_access'     => ['icon' => 'ri-code-s-slash-line',   'group' => 'modules',    'teaser' => true],
];
