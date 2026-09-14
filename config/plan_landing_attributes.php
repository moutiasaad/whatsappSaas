<?php

/*
 * Built-in attributes a plan card can advertise on the public landing page.
 *
 * The super admin picks which of these appear, per plan, on
 * /admin-control-panel/platform/plans — nothing is shown automatically. Module
 * entitlements are offered alongside these under the `module:<key>` prefix.
 *
 * A plan whose `landing_features` column is NULL has never been curated; the
 * landing page falls back to the legacy layout for it rather than rendering an
 * empty card.
 */
return [
    'trial'             => ['icon' => 'ri-time-line'],
    'max_users'         => ['icon' => 'ri-user-line'],
    'max_instances'     => ['icon' => 'ri-smartphone-line'],
    'max_conversations' => ['icon' => 'ri-message-3-line'],
    'ai_messages'       => ['icon' => 'ri-sparkling-2-line'],
];
