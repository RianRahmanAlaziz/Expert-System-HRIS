<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

return [
    'api_path' => 'api/v*',
    'api_domain' => null,
    'export_path' => 'api.json',

    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => <<<'MARKDOWN'
# Expert System HRIS API

REST API untuk aplikasi **Expert System Human Resources Information System (HRIS)**.

API ini menyediakan layanan untuk:

- Authentication & Authorization
- Employee Management
- Organization Management
- Attendance Management
- Leave & Permission Management
- Performance Management
- Competency Management
- Training & Development
- Career & Promotion
- Expert System
- Expert Consultation
- Recommendation
- Reporting & Dashboard

## API Version

Current API version: **v1**

## Base URL

`/api/v1`

## Authentication

API menggunakan **Laravel Sanctum** untuk authentication.

Endpoint yang membutuhkan authentication menggunakan:

`Authorization: Bearer {token}`

## Response Format

### Success Response

```json
{
    "success": true,
    "message": "Request successful",
    "data": {},
    "meta": {}
}
```

### Error Response

```json
{
    "success": false,
    "message": "Request failed",
    "errors": {}
}
```

## Pagination

Endpoint yang mendukung pagination menggunakan:

- `page`
- `per_page`

Parameter `per_page` memiliki nilai minimum `1` dan maksimum `100`.

## API Architecture

API menggunakan pola:

`Route → Controller → FormRequest → Service → Resource → ApiResponse`

Dokumentasi API dihasilkan secara otomatis berdasarkan route, controller, request validation, resource, model, dan response yang tersedia pada aplikasi.
MARKDOWN,
    ],

    'ui' => [
        'title' => 'Expert System HRIS API Documentation',
    ],

    'dev_tools' => [
        'enabled' => env(
            'SCRAMBLE_DEV_TOOLS',
            env('APP_DEBUG', false)
        ),
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],

        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => 'https://proxy.scalar.com',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => [
                'disabled' => true,
            ],
            'credentials' => 'include',
        ],
    ],

    'servers' => null,

    'enum_cases_description_strategy' => 'description',

    'enum_cases_names_strategy' => false,

    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],

    'security_strategy' => MiddlewareAuthSecurityStrategy::class,
];
