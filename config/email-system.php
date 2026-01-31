<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Variable Recursion Depth
    |--------------------------------------------------------------------------
    |
    | Maximum depth for recursive variable resolution. This prevents infinite
    | loops when variables reference each other.
    |
    */
    'variable_recursion_depth' => env('EMAIL_VARIABLE_RECURSION_DEPTH', 5),

    /*
    |--------------------------------------------------------------------------
    | Default SMTP Environment
    |--------------------------------------------------------------------------
    |
    | Default environment for SMTP account selection ('dev' or 'prod').
    | Normally determined by app.debug config.
    |
    */
    'default_smtp_environment' => env('EMAIL_DEFAULT_SMTP_ENV', 'dev'),

    /*
    |--------------------------------------------------------------------------
    | Tracking Enabled
    |--------------------------------------------------------------------------
    |
    | Enable email open and click tracking. When enabled, tracking pixel
    | and tracked links will be embedded in emails.
    |
    */
    'tracking_enabled' => env('EMAIL_TRACKING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Override Recipient in Development
    |--------------------------------------------------------------------------
    |
    | Override all email recipients in development mode. Useful for testing
    | without sending emails to real users.
    |
    */
    'override_recipient_in_dev' => env('MAIL_OVERRIDE_TO', null),

    /*
    |--------------------------------------------------------------------------
    | Variable Groups
    |--------------------------------------------------------------------------
    |
    | Available variable groups for categorizing email variables.
    |
    */
    'variable_groups' => [
        'user' => 'Felhasználó változók',
        'order' => 'Megrendelés változók',
        'album' => 'Album változók',
        'general' => 'Általános változók',
        'custom' => 'Egyedi változók',
    ],
];
