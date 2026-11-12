<?php

use Godrade\Trame\Media\LocalDiskProvider;
use Godrade\Trame\Models\MediaLibrary;

return [

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | The layout (sections, rows, columns, blocks) is shared between locales.
    | Only fields declared ->translatable() store one value per locale.
    |
    | missing_translation:
    |   'fallback' => render the fallback locale value
    |   'hide'     => hide a block whose required translatable field is missing
    |
    */

    'locales' => ['fr', 'en'],

    'fallback_locale' => 'fr',

    'missing_translation' => 'fallback',

    /*
    |--------------------------------------------------------------------------
    | Palette
    |--------------------------------------------------------------------------
    |
    | Tailwind colour tokens editors can pick. Classes are built dynamically
    | (text-*, bg-*, border-*), so after changing this list run:
    |
    |   php artisan trame:sources
    |
    | and import the generated file in your stylesheet.
    |
    */

    'palette' => [
        'white', 'black',
        'slate-50', 'slate-100', 'slate-200', 'slate-500', 'slate-700', 'slate-900',
        'indigo-600', 'rose-600', 'emerald-600', 'amber-500',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocks
    |--------------------------------------------------------------------------
    |
    | Core blocks are always registered. Classes in "register" and classes
    | discovered in "discover.path" are added afterwards; a block using an
    | existing type overrides it.
    |
    */

    'blocks' => [
        'register' => [],

        'discover' => [
            'path' => app_path('Trame/Blocks'),
            'namespace' => 'App\\Trame\\Blocks',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Editor API
    |--------------------------------------------------------------------------
    |
    | Every editor route is also protected by the "trame.view" gate, and every
    | route that writes (save, publish, upload, templates) by "trame.edit".
    | "trame.edit" denies everyone until you define it in your application;
    | "trame.view" follows it unless you define it too (read-only editor:
    | visitors can try everything, nothing is stored). Drop "auth" from the
    | middleware to open the read-only editor to guests.
    |
    */

    'editor' => [
        'enabled' => true,
        'prefix' => 'trame',
        'middleware' => ['web', 'auth'],

        // Where the editor's "back" button goes when no ?return=/path is given.
        'return_url' => '/',

        // Simple mode, shown to editors without the "trame.advanced" gate: advanced fields are
        // hidden and the palette offers these blocks and column layouts only.
        'simple' => [
            'blocks' => ['heading', 'text', 'image', 'button', 'card', 'quote', 'question'],
            'layouts' => ['12', '6-6', '4-4-4', '8-4'],
        ],

        'preview' => [
            'layout' => 'trame::layouts.preview',
            'vite' => ['resources/css/app.css'],

            // Preview renders per minute for read-only viewers (editors are not limited).
            'viewer_per_minute' => 120,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Public pages
    |--------------------------------------------------------------------------
    |
    | Routes are never registered automatically (a catch-all slug route would
    | shadow your own routes). Call Trame::pageRoutes() at the END of
    | routes/web.php if you want them.
    |
    */

    'pages' => [
        'middleware' => ['web'],
        'layout' => 'trame::layouts.page',
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    */

    'media' => [
        // LocalDiskProvider, SpatieMediaProvider (requires spatie/laravel-medialibrary) or your own MediaProvider.
        'provider' => LocalDiskProvider::class,
        'max_kb' => 5120,

        // LocalDiskProvider
        'disk' => 'public',
        'directory' => 'trame',
        // Widths (px) of the resized copies generated on upload for srcset (jpeg, png, webp;
        // widths >= the original are skipped). [] disables them.
        'widths' => [480, 960, 1600],
        // Photos above this many pixels are kept without resized copies (GD memory).
        'max_pixels' => 24_000_000,

        // SpatieMediaProvider
        'spatie' => [
            'model' => MediaLibrary::class, // extend it to register conversions
            'library' => 'default',
            'collection' => 'trame',
            'disk' => null,        // null: media-library.disk_name
            'conversion' => null,  // conversion name served instead of the original, when generated
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Starter sections and links
    |--------------------------------------------------------------------------
    */

    'patterns' => [
        // The six built-in sections (hero, features, testimonials, faq, cta, image_text).
        'defaults' => true,
        // Your own: class names implementing Godrade\Trame\Patterns\Pattern.
        'extra' => [],
    ],

    'links' => [
        // Class implementing Godrade\Trame\Links\LinkResolver (default: PageLinkResolver).
        'resolver' => null,
        // URL of a page chosen in the link picker: fn (Page $page, string $locale): string.
        // Default: route "trame.page" if it exists, otherwise /{slug}.
        'page_url' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Revisions
    |--------------------------------------------------------------------------
    |
    | Every save and publication is kept in the history of a content. Saves by
    | the same user within "coalesce_minutes" update the same entry, so the
    | editor's autosave does not flood the history.
    |
    */

    'revisions' => [
        'coalesce_minutes' => 10,
        'keep' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Published content is cached per content, revision and locale. Run
    | "php artisan trame:clear" after deploying changes to block views.
    |
    */

    'cache' => [
        'enabled' => true,
        'store' => null,
        'ttl' => null,
    ],

    'run_migrations' => true,

];
