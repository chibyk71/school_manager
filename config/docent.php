<?php

use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;

return [

    'default' => 'docs',

    'database' => [
        'enabled' => false,
        'connection' => null,
    ],

    'authorization' => [
        'denied_response' => 404,
    ],

    'content' => [
        'allow_html' => true,
        'database' => [
            'sanitize_html' => true,
        ],
    ],

    'seo' => [
        'title_template' => ':title — :site',
        'description' => null,
        'image' => null,
    ],

    'rendering' => [
        // defaults
    ],

    'search' => [
        'enabled' => true,
        'stop_words' => [
            'a', 'an', 'and', 'are', 'can', 'could', 'do', 'does', 'for', 'from',
            'how', 'i', 'in', 'is', 'it', 'my', 'of', 'on', 'or', 'our', 'should',
            'that', 'the', 'this', 'to', 'we', 'what', 'when', 'where', 'which',
            'with', 'would', 'you', 'your',
        ],
    ],

    'ai' => [
        'enabled' => false,
        'provider' => env('DOCENT_AI_PROVIDER'),
        'model' => env('DOCENT_AI_MODEL'),
        'language' => null,
        'log_questions' => true,
        'max_tokens' => 1200,
        'throttle' => '10,1',
        'corpus_budget' => 150000,
        'answer_ttl' => 300,
        'retrieval' => [
            'max_pages' => 8,
            'candidate_limit' => 24,
            'debug' => false,
        ],
        'conversation' => [
            'ttl' => 7200,
            'max_turns' => 10,
            'history_budget' => 12000,
        ],
    ],

    'insights' => [
        'enabled' => true,
        'categories' => [
            'pages' => true,
            'search' => true,
            'assistant' => false,
        ],
        'retention_days' => 90,
        'store_query_text' => true,
        'redact_query_text' => true,
    ],

    'widget' => [
        'enabled' => false,
        'mode' => 'overlay',
        'position' => 'right',
        'offset' => 24,
        'launcher' => 'button',
        'icon' => 'book-open',
        'preload' => false,
    ],

    'cache' => [
        'store' => null,
        'prefix' => 'docent',
    ],

    'theme' => [
        'accent' => '#4f46e5',
        'logo' => null,
        'logo_dark' => null,
    ],

    'abilities' => null,

    'sites' => [
        'docs' => [
            'name' => env('DOCENT_NAME', 'School Manager Docs'),
            'description' => env('DOCENT_DESCRIPTION', 'Internal documentation for School Manager users'),
            'route' => [
                'prefix' => 'docs',
                'domain' => null,
                'middleware' => ['web', 'auth'],
            ],
            'filesystem' => [
                'path' => null,
            ],
            'admin' => [
                'enabled' => false,
                'path' => 'admin',
                'gate' => 'viewDocentAdmin',
                'disk' => 'public',
                'uploads' => [
                    'public_cache' => false,
                ],
            ],
        ],
    ],

];
