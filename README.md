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
- Drag blocks, layouts and templates from the palette straight into the canvas
- Double-click a text to edit it in place (plain text, or rich text with ⌘B / ⌘I)
- Edit a global template from any page that uses it; changes apply everywhere
- Settings panel generated from block schemas, live validation
- Mobile / tablet / desktop preview, per-breakpoint column widths
- Per-locale editing of translatable fields, fallback shown as placeholder
- Undo / redo (⌘Z / ⇧⌘Z), save (⌘S), publish, optimistic locking
- Media library, reusable and global templates

If you override the preview layout, keep `{!! $bridge ?? '' !!}` before `</body>`.

### In-place editing in your own blocks

Block views receive an `$editable` helper. Put it on the element that displays a text field:

```blade
<h3 class="…" {!! $editable('title') !!}>{{ $title }}</h3>
<div class="…" {!! $editable('body', rich: true) !!}>{!! $body !!}</div>
```

It renders nothing outside the editor. Use it only on an element that displays the field as-is
(no extra markup or formatting inside), otherwise the edited text would include it.

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
    "settings": { "bg": "slate-900", "padding": { "base": "md", "lg": "xl" }, "rows_gap": { "base": "md" }, "container": "boxed" },
    "rows": [{
      "id": "row_1",
      "settings": { "gap": "md" },
      "columns": [{
        "id": "col_1",
        "span": { "base": 12, "md": 6 },
        "settings": { "valign": "start", "gap": { "base": "sm" } },
        "blocks": [
          { "id": "blk_1", "type": "heading", "v": 1,
            "data": { "text": { "fr": "Bienvenue", "en": "Welcome" }, "level": 1, "color": "white",
                      "align": { "base": "center", "lg": "start" } },
            "settings": { "hide": ["base"], "margin_top": { "lg": "md" } } }
        ]
      }]
    }]
  }]
}
```

Settings store **semantic values** (`"padding": "lg"`), never classes. Mapping lives in
`Godrade\Trame\Support\Tokens`.

**Per-screen settings.** Breakpoints are `base` (mobile), `md` (tablet, ≥ 48rem) and `lg` (desktop, ≥ 64rem),
edited through the device switcher of the editor.

- `settings.hide` on a section, row, column or block lists the breakpoints it is hidden on. It is stored only
  when not empty. Public output uses `max-md:hidden`, `md:max-lg:hidden` and `lg:hidden` (a block gets a
  wrapping `<div>`). In the editor the node stays selectable and is dimmed on the matching device.
- A responsive select stores one value per breakpoint; `md` and `lg` are optional and inherit from the
  breakpoint below. A plain scalar (data saved before the field became responsive) is read as `base`.

**Spacing.** Space between elements uses one scale, `none` 0, `xs` .5rem, `sm` 1rem, `md` 2rem, `lg` 3rem,
`xl` 4rem, `2xl` 6rem, set per breakpoint:

| Where | Setting | Default |
|---|---|---|
| Section, vertical padding | `settings.padding` (`none`, `sm`, `md`, `lg`, `xl`) | `md` |
| Between the rows of a section | `settings.rows_gap` | `none` |
| Between the columns of a row | `settings.gap` on the row (`none`, `sm`, `md`, `lg`) | `md` |
| Between the blocks of a column | `settings.gap` on the column | `sm` |
| Around one block | `settings.margin_top`, `settings.margin_bottom` on the block | unset |

Block settings are optional and only stored when set; a block with a margin gets a wrapping `<div>`.

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

**Per-screen values:** `Field::select('align')->options([...])->responsive()` lets editors set a value per
device. Render it with `Tokens::responsive(Tokens::ALIGN_RESPONSIVE, $data['align'], 'start')`
(`JUSTIFY_RESPONSIVE` for flex alignment). The class maps are written literally in `Tokens` so Tailwind
detects them; add your own literal map for other responsive values.

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

### Spatie Media Library

```bash
composer require spatie/laravel-medialibrary
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

```php
// config/trame.php
'media' => [
    'provider' => \Godrade\Trame\Media\SpatieMediaProvider::class,
    'spatie' => [
        'model' => \Godrade\Trame\Models\MediaLibrary::class,
        'library' => 'default',     // one owner record per library name
        'collection' => 'trame',
        'disk' => null,             // null: media-library.disk_name
        'conversion' => null,       // e.g. 'web': served instead of the original once generated
    ],
],
```

Uploads are attached to a `MediaLibrary` record (`trame_media_libraries` table). The editor only lists and
resolves media of that record and collection: an id pointing to another model's media renders nothing.

To use conversions, extend the model and point `trame.media.spatie.model` to it:

```php
use Godrade\Trame\Models\MediaLibrary;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TrameMedia extends MediaLibrary
{
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('web')->fit(Fit::Max, 1600, 1600)->format('webp');
    }
}
```

Switching providers does not migrate existing files: image blocks keep the ids of the previous provider.

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
