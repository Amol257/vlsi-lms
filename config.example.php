<?php
/**
 * VLSI LMS Configuration Template
 *
 * Copy this file to config.local.php either in the directory above the document root
 * or in the document root (protected by .htaccess). Never commit config.local.php.
 */

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'u123456789_lms',
        'user' => 'u123456789_lmsuser',
        'pass' => 'CHANGE_ME_DATABASE_PASSWORD',
        'charset' => 'utf8mb4'
    ],
    'supabase' => [
        'url' => 'https://YOUR_PROJECT_REF.supabase.co',
        'anon_key' => 'REPLACE_WITH_SUPABASE_ANON_KEY',
        'google_oauth_enabled' => true,
    ],
    'app' => [
        'site_url' => 'https://learn.vlsiinsights.com',
        'app_key' => 'REPLACE_WITH_32_RANDOM_BYTES_HEX',
        'admin_bootstrap_emails' => [
            'admin@vlsiinsights.com'
        ],
        'session_idle_timeout' => 28800, // 8 hours
        'session_absolute_timeout' => 86400, // 24 hours
        'server_transcode' => false,
    ],
    'razorpay' => [
        'enabled' => false,
        'key_id' => 'rzp_live_placeholder',
        'key_secret' => 'placeholder_secret',
        'webhook_secret' => 'placeholder_webhook_secret'
    ],
    'enrollment' => [
        'webhook_secret' => 'REPLACE_WITH_RANDOM_SECRET_FOR_MAIN_SITE'
    ]
];
