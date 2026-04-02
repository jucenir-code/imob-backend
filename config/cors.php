<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => value(function () {
        $methods = env('CORS_ALLOWED_METHODS', '*');

        if ($methods === '*' || $methods === '') {
            return ['*'];
        }

        return array_map('trim', explode(',', $methods));
    }),

    'allowed_origins' => value(function () {
        $origins = env('CORS_ALLOWED_ORIGINS', '*');

        if ($origins === '*' || $origins === '') {
            return ['*'];
        }

        return array_map('trim', explode(',', $origins));
    }),

    'allowed_origins_patterns' => [],

    'allowed_headers' => value(function () {
        $headers = env('CORS_ALLOWED_HEADERS', '*');

        if ($headers === '*' || $headers === '') {
            return ['*'];
        }

        return array_map('trim', explode(',', $headers));
    }),

    'exposed_headers' => value(function () {
        $headers = env('CORS_EXPOSED_HEADERS', '');

        if ($headers === '') {
            return [];
        }

        return array_map('trim', explode(',', $headers));
    }),

    'max_age' => 0,

    'supports_credentials' => filter_var(
        env('CORS_SUPPORTS_CREDENTIALS', false),
        FILTER_VALIDATE_BOOLEAN
    ),

];
