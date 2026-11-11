# Trame

Visual, grid-based, multilingual page builder for Laravel and Tailwind CSS v4.

> **Status: pre-alpha.** Server side (document model, blocks, 12-column grid, translations, rendering,
> JSON API) and the visual editor (Alpine + SortableJS + Tiptap, prebuilt in `dist/`).

- PHP 8.3+, Laravel 12+, Tailwind CSS v4
- No paid dependency, no Livewire requirement
- Layout shared across locales, translatable fields per block
- Custom blocks in PHP + Blade, reusable and global templates
- Pluggable media storage

## Installation

```bash
composer require godrade/trame
php artisan migrate
php artisan trame:sources
```

### Tailwind

Classes chosen in the editor are stored in the database, where Tailwind cannot see them.
Import the package sources and the generated palette after Tailwind:

```css
/* resources/css/app.css */
@import "tailwindcss";
@import "../../vendor/godrade/trame/resources/css/trame.css";
@import "./trame-palette.css";
```

Re-run `php artisan trame:sources` whenever `trame.palette` changes.

### Authorization

Every editor route is behind the `trame.edit` gate, which **denies everyone by default**:

```php
// AppServiceProvider::boot()
Gate::define('trame.edit', fn (User $user) => $user->is_admin);
```

## Visual editor

```php
// e.g. in an admin controller or Blade view
<a href="{{ $page->trameEditorUrl(return: '/admin/pages') }}">Edit</a>
```

The editor is a standalone page (`/trame/editor/{content}`) served with prebuilt assets: nothing to
compile or publish on the host. The canvas is an iframe rendering the real page with your Tailwind
build (`trame.editor.preview.vite`), so what you see is what is published.

- Click to select, drag & drop sections, rows, columns and blocks directly in the canvas
- Settings panel generated from block schemas, live validation
- Mobile / tablet / desktop preview, per-breakpoint column widths
- Per-locale editing of translatable fields, fallback shown as placeholder
- Undo / redo (⌘Z / ⇧⌘Z), save (⌘S), publish, optimistic locking
- Media library, reusable and global templates

If you override the preview layout, keep `{!! $bridge ?? '' !!}` before `</body>`.

### Developing the editor

```bash
npm install
npm run build   # rebuilds dist/ (commit it)
npm test        # document model tests
```

## Document model

```
Page → Sections → Rows → Columns (span per breakpoint: base, md, lg) → Blocks
```

```json
{
  "sections": [{
    "id": "sec_1",
    "settings": { "bg": "slate-900", "padding": "lg", "container": "boxed" },
    "rows": [{
      "id": "row_1",
      "settings": { "gap": "md" },
      "columns": [{
        "id": "col_1",
        "span": { "base": 12, "md": 6 },
        "settings": { "valign": "start" },
        "blocks": [
          { "id": "blk_1", "type": "heading", "v": 1,
            "data": { "text": { "fr": "Bienvenue", "en": "Welcome" }, "level": 1, "color": "white" } }
        ]
      }]
    }]
  }]
}
```

Settings store **semantic values** (`"padding": "lg"`), never classes. Mapping lives in
`Godrade\Trame\Support\Tokens`.

Any section, row or block can be replaced by a global template reference:
`{ "type": "global", "ref": 12 }`.

## Attaching content

```php
use Godrade\Trame\Concerns\HasTrame;

class Post extends Model
{
    use HasTrame;
}

$post->trame();                  // TrameContent (draft + published document)
$post->trame('sidebar');         // several documents per model
{!! $post->renderTrame() !!}     // published, cached, current locale
```

A standalone `Godrade\Trame\Models\Page` (translated title/slug/meta) is included. Public routes are
opt-in, at the **end** of `routes/web.php`:

```php
Trame::pageRoutes();          // /{slug}
Trame::pageRoutes('pages');   // /pages/{slug}
```

## Multilingual

```php
// config/trame.php
'locales' => ['fr', 'en'],
'fallback_locale' => 'fr',
'missing_translation' => 'fallback', // or 'hide'
```

Only fields marked `->translatable()` store per-locale values. Routing by locale stays in your app.
Alignment uses logical values (`start` / `center` / `end`) so RTL languages work.

## Custom blocks

```bash
php artisan make:trame-block Testimonial
```

Blocks in `app/Trame/Blocks` are discovered automatically.

```php
final class TestimonialBlock extends Block
{
    public static string $type = 'testimonial';
    public static int $version = 1;

    public function schema(): array
    {
        return [
            Field::textarea('quote')->translatable()->required(),
            Field::text('author'),
            Field::image('avatar'),
            Field::color('accent')->default('indigo-600'),
        ];
    }

    public function with(array $data): array
    {
        return ['accent' => Tokens::color($data['accent'], 'text')];
    }

    public function view(): string
    {
        return 'trame.blocks.testimonial';
    }
}
```

The schema drives both server-side validation and the editor settings panel.

**Changing a schema:** increment `$version` and implement `migrate(array $data, int $fromVersion)`.
Stored documents are migrated on read and on save. Never rename `$type` once in production.

Field types: `text`, `textarea`, `richText` (sanitized), `url` (http(s), relative, `#`, `mailto:`, `tel:` only),
`number`, `toggle`, `select`, `color` (palette, `->allowCustom()` for hex), `image`.

## Editor API

Prefix `config('trame.editor.prefix')`, middleware `web`, `auth`, `can:trame.edit`.

| Method | URI | |
|---|---|---|
| GET | `api/definitions` | blocks, layout fields, palette, locales, grid |
| GET / PUT | `api/contents/{id}` | draft; PUT accepts `revision` (409 on conflict) |
| POST | `api/contents/{id}/publish` · `unpublish` | |
| POST | `api/preview` | HTML for the editor iframe |
| GET / POST | `api/media` | images only, SVG refused |
| GET / POST / PUT / DELETE | `api/templates` | section, row or block templates |

Validation errors use full paths: `document.sections.0.rows.0.columns.1.blocks.2.data.text.fr`.

## Media

Implement `Godrade\Trame\Media\MediaProvider` and set `trame.media.provider`.
`LocalDiskProvider` (Laravel disk + `trame_media` table) is the default.

## Cache

Published renders are cached per content, published document and locale. After deploying changes to
block views:

```bash
php artisan trame:clear
```

## Testing

```bash
composer test
```

## License

MIT
