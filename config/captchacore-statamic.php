<?php

return [
    // API connection — keys from the CaptchaCore customer area
    'service_url' => env('CAPTCHACORE_URL', 'https://api.captchacore.eu'),
    'site_key' => env('CAPTCHACORE_SITE_KEY', ''),
    'secret_key' => env('CAPTCHACORE_SECRET_KEY', ''),
    'timeout' => (int) env('CAPTCHACORE_TIMEOUT', 3),
    // Let submissions through while the service is unreachable (5xx, 408, timeout, network)
    'fail_open' => (bool) env('CAPTCHACORE_FAIL_OPEN', true),

    // Statamic forms checked on submit: '*' = all forms, otherwise a
    // comma-separated list (or array) of form handles, e.g. 'contact,newsletter'.
    'forms' => env('CAPTCHACORE_STATAMIC_FORMS', '*'),

    // Form type sent to CaptchaCore per form handle (controls the strictness
    // of the check). Forms not listed here use default_form_type.
    // Allowed: login | register | contact | comment | checkout | custom
    'form_types' => [
        // 'newsletter' => 'register',
    ],

    'default_form_type' => env('CAPTCHACORE_STATAMIC_FORM_TYPE', 'contact'),

    // Widget options. Only non-empty values are written to the page; empty =
    // setting from the customer area (language: the page's <html lang>).
    'widget' => [
        'mode' => env('CAPTCHACORE_WIDGET_MODE', ''),         // interactive | visible | invisible
        'theme' => env('CAPTCHACORE_WIDGET_THEME', ''),       // auto | light | dark
        'color' => env('CAPTCHACORE_WIDGET_COLOR', ''),       // accent colour (hex)
        'label' => env('CAPTCHACORE_WIDGET_LABEL', ''),
        'brand' => env('CAPTCHACORE_WIDGET_BRAND', ''),
        'size' => env('CAPTCHACORE_WIDGET_SIZE', ''),         // normal | compact
        'lang' => env('CAPTCHACORE_WIDGET_LANG', ''),         // de | en | fr | es | …
        'endpoint' => env('CAPTCHACORE_WIDGET_ENDPOINT', 'eu'), // eu (EU-only CDN) | global
        'cdn_url' => env('CAPTCHACORE_WIDGET_CDN_URL'),       // optional own widget host
    ],
];
