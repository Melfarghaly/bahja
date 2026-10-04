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

    // media/* = signed wall photo/video links, read by the web app (Flutter web
    // decodes images through fetch, so it needs CORS). The signature is the
    // credential; no cookies are involved.
    'paths' => ['api/*', 'media/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Range', 'Accept-Ranges', 'Content-Length'],

    'max_age' => 0,

    'supports_credentials' => false,

];
