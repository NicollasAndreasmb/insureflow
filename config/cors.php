<?php

/*
 * A SPA é servida pela mesma origem da API, então navegadores não precisam de
 * CORS para usá-la. O padrão do framework libera qualquer origem ('*'); aqui só
 * a própria APP_URL (e origens extras explícitas em CORS_ALLOWED_ORIGINS) são
 * aceitas, para que nenhum outro site leia respostas da API.
 */

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter([
        rtrim((string) env('APP_URL'), '/'),
        ...explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => true,

];
