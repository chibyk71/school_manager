<?php

use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Site
    |--------------------------------------------------------------------------
    |
    | The site resolved when no explicit site key is selected.
    |
    */

    'default' => 'docs',

    // ── Shared defaults (overridable per site) ──────────────────────────────

    /*
    |--------------------------------------------------------------------------
    | Database Store
    |--------------------------------------------------------------------------
    |
    | Opt-in database-backed pages, composed *over* the filesystem (a database
    | page overrides a file with the same slug). Publish the tables with
    | `php artisan docent:install --with-database` (or
    | `vendor:publish --tag=docent-migrations`), migrate, then flip `enabled`.
    | `connection` is null for the default database connection.
    |
    */

    'database' => [
        'enabled' => false,
        'connection' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Response returned when a viewer is denied a page (front matter authorize
    | / audience). Options: 404 (hide existence, default), 403, or a redirect
    | string "redirect:/login".
    |
    */

    'authorization' => [
        'denied_response' => 404,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content
    |--------------------------------------------------------------------------
    |
    | Repository Markdown is reviewed application code, so its raw HTML remains
    | enabled by default and keeps the historical `allow_html` setting. Content
    | written through the database admin is sanitized when rendered: ordinary
    | structural HTML stays useful, while scripts, event handlers, unsafe URLs,
    | and other active content are removed. The original source remains stored
    | verbatim.
    | Deploy-trusted teams may explicitly disable database sanitization.
    |
    */

    'content' => [
        'allow_html' => true,
        'database' => [
            'sanitize_html' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Engine Optimization
    |--------------------------------------------------------------------------
    */

    'seo' => [
        // Serve {prefix}/sitemap.xml listing the pages a guest may see.
        'sitemap' => true,

        // A social-preview image for link unfurls (path or absolute URL).
        // Pages can override it with an `image:` front matter key.
        'image' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    |
    | A registered value or link closure that throws — a tenant-scoped lookup
    | for a reader with no tenant selected, say — substitutes nothing, reports
    | the exception, and lets the rest of the page render. A help center is
    | where someone goes when something is already wrong for them, so one
    | broken token should not take down every paragraph around it.
    |
    | The throwable still reaches exception tracking, so this defers the fix
    | rather than hiding it. Set `strict_tokens` to true to get the exception
    | instead.
    |
    */

    'render' => [
        'strict_tokens' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Share Links
    |--------------------------------------------------------------------------
    |
    | A share link makes one page readable by someone who is not signed in —
    | a support recipient, a sales lead — without opening the rest of the
    | site. The URL is the page's own, plus a short `?s=` token.
    |
    | Nothing viewer-specific ever reaches a share link. Authorization blocks
    | resolve as they would for a logged-out visitor and dynamic values render
    | their label instead of resolving, so no host resolver runs and there is
    | no reader data to leak. A recipient who IS signed in gets their normal
    | page, their own context intact; the token is simply ignored.
    |
    | The token is an alternative credential, not a hole in your guard: it
    | satisfies the guard for the page and for that page's images and
    | stylesheet, and for nothing else. Search, the assistant, llms.txt, and
    | the admin panel refuse it no matter how valid it is.
    |
    | `gate` is checked with Gate::allows, so leaving it undefined means
    | nobody can mint links. Change `salt` to invalidate every outstanding
    | link at once.
    |
    */

    'share' => [
        'enabled' => false,

        // The ability a viewer needs to mint share links.
        'gate' => 'shareDocentPage',

        // Rotate to invalidate every outstanding share link.
        'salt' => env('DOCENT_SHARE_SALT'),

        // Default and maximum link lifetime, in days.
        'ttl' => 30,
        'max_ttl' => 90,

        // Rate limit applied only to requests that carry a share token.
        'throttle' => '60,1',

        // Where the "sign in to read everything" footer link points. Null
        // uses the host's `login` route when it has one, and omits the offer
        // when it does not.
        'login_url' => null,

        // The middleware Docent's share credential must run before, and the
        // one it stands in for. The default covers Laravel's own `auth`,
        // `auth:sanctum`, and anything extending Authenticate; name a bespoke
        // guard here when it implements neither. Docent seats whatever it
        // finds here into the kernel's middleware priority map, since Laravel
        // can only order against an anchor already in that map.
        'before' => AuthenticatesRequests::class,
    ],

    'check' => [
        // Override a rule's severity by its stable id, or silence it with 'off'.
        // Opt-in quality rules run only when listed here as error/warning/warn.
        // e.g. 'heading-hierarchy' => 'warning', 'search-keywords' => 'off'.
        // Enable one with e.g. 'single-h1' => 'warning'.
        //
        // 'gated-link' reports links from an ungated page to a gated one — a
        // dead end for every reader whose role lacks the target's requirement.
        // Wrap a deliberate one in a :::can or :::audience block to exempt it.
        'rules' => [
            // 'unknown-icon' => 'warning',
            // 'gated-link' => 'warning',
        ],

        // The abilities an `authorize:` key (or `:::can` block) may name, so the
        // checker and the admin's autocompletion can tell a real permission from
        // a typo. Leave null to use Gate::has(), which only sees abilities passed
        // to Gate::define() — an application that bridges permissions with a
        // single Gate::before callback defines no gates, so Gate::has() answers
        // false for every real permission it has. Declare the surface instead:
        // a list of strings, or a backed-enum class-string as shorthand.
        //
        // A declared surface REPLACES Gate::has() rather than adding to it, so
        // it must name every ability the documentation may reference. An empty
        // array therefore means "no ability is valid here", not "fall back".
        //
        //     'abilities' => App\Enums\Permission::class,
        //
        // A closure belongs in a service provider, not here — config files must
        // stay serializable for `config:cache`:
        //
        //     Docent::abilities(fn () => Permission::query()->pluck('name')->all());
        'abilities' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    'search' => [
        'enabled' => true,
        // English defaults keep question-shaped queries focused. Replace this
        // list (or set it to []) when the documentation uses another locale.
        'stop_words' => [
            'a', 'an', 'and', 'are', 'can', 'could', 'do', 'does', 'for', 'from',
            'how', 'i', 'in', 'is', 'it', 'my', 'of', 'on', 'or', 'our', 'should',
            'that', 'the', 'this', 'to', 'we', 'what', 'when', 'where', 'which',
            'with', 'would', 'you', 'your',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ask the Docs
    |--------------------------------------------------------------------------
    |
    | Optional grounded answers powered by the host application's Prism setup.
    | The feature is off by default and Prism is intentionally not a required
    | package dependency. Questions use only the current viewer's visible docs.
    |
    */

    'ai' => [
        // Do not enable the Docent Assistant / AI category.
        'enabled' => false,
        'provider' => env('DOCENT_AI_PROVIDER'),
        'model' => env('DOCENT_AI_MODEL'),
        // null = answer in the docs' language (today's behavior).
        // 'viewer' = answer in the app locale of the request (app()->getLocale()).
        // Any locale string (e.g. 'de', 'pt-BR') = always answer in that language.
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

    /*
    |--------------------------------------------------------------------------
    | Documentation Insights
    |--------------------------------------------------------------------------
    |
    | Optional, first-party signals for improving the help center. Events use a
    | fixed schema and never store user IDs, IPs, sessions, referrers, user
    | agents, audience/gate context, or generated answer text. Common sensitive
    | patterns in search and question text are redacted before storage.
    |
    */

    'insights' => [
        'enabled' => true,
        'categories' => [
            'pages' => true,
            'search' => true,
            // Assistant/AI insights disabled (includes assistant feedback thumbs).
            'assistant' => false,
        ],
        'retention_days' => 90,
        'store_query_text' => true,
        'redact_query_text' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | In-app Help Widget
    |--------------------------------------------------------------------------
    |
    | Render the launcher explicitly with <x-docent::widget />. The iframe is
    | same-origin and therefore inherits the docs route middleware and viewer.
    |
    */

    'widget' => [
        'enabled' => false,
        'mode' => 'overlay',
        'position' => 'right',
        'offset' => 24,
        'launcher' => 'button',
        'icon' => 'book-open',
        'preload' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Store used for the parsed AST, navigation skeleton, and search index.
    | Null uses the default cache store. `docent:clear` bumps a version stamp
    | folded into every key, so no store-specific tagging is required.
    |
    */

    'cache' => [
        'store' => null,
        'prefix' => 'docent',
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    |
    | Brand the documentation UI entirely from config — no CSS rebuild, no
    | published views. Every token below flows through runtime CSS variables.
    |
    | `accent` is a single hex colour driving every accent (active nav, links,
    | focus rings, search highlights). `logo` is a path or URL shown in the top
    | bar; null falls back to a text wordmark. `logo_dark` swaps it in dark mode
    | (falls back to `logo`); `logomark` is a square mark used in the compact
    | mobile header (falls back to `logo` → wordmark). `favicon` is emitted as a
    | <link rel="icon"> when set.
    |
    | `font.sans` / `font.mono` are CSS font-family stacks (null keeps the
    | system stack); `font.href` optionally emits a webfont stylesheet link
    | (default is zero external requests).
    |
    | `gray` selects the base palette temperature (slate | zinc | stone |
    | neutral) and `radius` the corner feel (sharp | default | soft).
    |
    */

    'theme' => [
        // Align accent with School Manager primary brand feel.
        'accent' => '#4f46e5',
        'logo' => null,
        'logo_dark' => null,
        'logomark' => null,
        'favicon' => null,
        'font' => [
            'sans' => null,
            'mono' => null,
            'href' => null,
        ],
        'gray' => 'slate',
        'radius' => 'default',
    ],

    // ── Sites ───────────────────────────────────────────────────────────────

    /*
    |--------------------------------------------------------------------------
    | Documentation Sites
    |--------------------------------------------------------------------------
    |
    | Site keys are chosen by the host application. Any shared section above
    | may be repeated inside a site entry to override its defaults. Site-only
    | sections (name, description, route, filesystem, admin, navigation, and
    | layouts) live exclusively inside each site entry.
    |
    */

    'sites' => [
        'docs' => [
            /*
            |----------------------------------------------------------------------
            | Site Name
            |----------------------------------------------------------------------
            |
            | The name shown in the documentation UI (title bar, header). Defaults
            | to your application name suffixed with "Docs".
            |
            */

            'name' => env('DOCENT_NAME', 'School Manager Docs'),

            // Used by llms.txt and available to host applications for metadata.
            'description' => env('DOCENT_DESCRIPTION', 'Internal documentation for School Manager users'),

            /*
            |----------------------------------------------------------------------
            | Route
            |----------------------------------------------------------------------
            |
            | The docs are served under this prefix (and optional domain), guarded
            | by the middleware. For authenticated docs, use ['web', 'auth'].
            |
            */

            'route' => [
                'prefix' => 'docs',
                'domain' => null,
                // Authenticated internal docs: web stack (SchoolContext) + auth.
                'middleware' => ['web', 'auth'],
            ],

            /*
            |----------------------------------------------------------------------
            | Filesystem
            |----------------------------------------------------------------------
            |
            | Where the markdown documents live. Null resolves to
            | resource_path('docs').
            |
            */

            'filesystem' => [
                'path' => null,
            ],

            /*
            |----------------------------------------------------------------------
            | Admin Panel
            |----------------------------------------------------------------------
            |
            | The database-backed authoring panel, served under `path` inside the
            | docs route group (so `/docs/admin` by default — a docs page with that
            | exact slug would be shadowed by the panel). Requires the database
            | store (`docent.database.enabled`) and is off by default. Every admin
            | route — the panel and its JSON API — is additionally guarded by the
            | `gate` ability, which the host application defines (it denies guests
            | by default). Image uploads land on `disk` and are served back through
            | the docs `_uploads` route — any disk works (public, local, private
            | S3), no storage:link or public bucket required, and images inherit
            | the docs route middleware. Uploaded files use private immutable
            | browser caching by default. Enable `uploads.public_cache` only when
            | the documentation and every uploaded image are intentionally public
            | through any shared cache.
            |
            */

            'admin' => [
                'enabled' => false,
                'path' => 'admin',
                'gate' => 'viewDocentAdmin',
                'disk' => 'public',
                'uploads' => [
                    'public_cache' => false,
                ],
            ],

            /*
            |----------------------------------------------------------------------
            | Navigation
            |----------------------------------------------------------------------
            |
            | Top-level directories whose `_group.yml` contains `section: true`
            | become switchable documentation areas. Everything else remains in
            | the default section. Persistent links appear above the sidebar and
            | may target an external URL, a documentation page, or a named route.
            | Topbar links use the same shape but render as icon buttons beside
            | the theme toggle.
            |
            */

            'navigation' => [
                'default_section' => 'Documentation',
                'links' => [
                    // ['label' => 'Support', 'icon' => 'lifebuoy', 'url' => 'https://example.com/support'],
                    // ['label' => 'Setup guide', 'icon' => 'rocket-launch', 'page' => 'getting-started/setup'],
                    // ['label' => 'Admin console', 'icon' => 'wrench', 'route' => 'admin.dashboard', 'can' => 'admin'],
                ],
                'topbar' => [
                    // ['label' => 'GitHub', 'icon' => 'github', 'url' => 'https://github.com/acme/acme'],
                ],
            ],

            /*
            |----------------------------------------------------------------------
            | Page Layouts
            |----------------------------------------------------------------------
            |
            | A page opts out of the docs chrome with front-matter `layout: <name>`.
            | Names resolve to `docent::layouts.<name>` views, so a custom layout
            | is one new file in resources/views/vendor/docent/layouts/ — no
            | package views need forking. Map a name here instead to point it at
            | any view in your application. Every layout receives the same
            | documented payload as the built-in `landing` layout.
            |
            */

            'layouts' => [
                // 'help-center' => 'docs.help-center',
            ],
        ],
    ],

];
