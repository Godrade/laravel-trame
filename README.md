# Trame

Visual, grid-based, multilingual page builder for Laravel and Tailwind CSS v4.

> **Status: v0.1, early release.** The document format and the block API may still change before 1.0;
> breaking changes are listed in [CHANGELOG.md](CHANGELOG.md).

- PHP 8.3+, Laravel 12 or 13, Tailwind CSS v4
- No paid dependency, no Livewire requirement
- Layout shared across locales, translatable fields per block
- Custom blocks in PHP + Blade, reusable and global templates
- Per-screen settings (visibility, alignment, spacing)
- Pluggable media storage (local disk or Spatie Media Library)

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

### Theme

Core blocks carry no brand of their own: they use your font, inherit the text colour of each section and
draw hairlines in that colour. Three CSS variables set the rest:

```css
/* after the imports above */
:root {
    --trame-accent: var(--color-emerald-700);  /* solid buttons, default #000 */
    --trame-on-accent: #fff;                   /* text on the accent, default #fff */
    --trame-radius: 0;                         /* buttons, images, gallery, default .25rem */
}
```

Editors can still pick a palette colour per block; the variables only apply when they do not.

### Core blocks

| Block | Type | Notes |
|---|---|---|
| Heading | `heading` | level h1–h6, size, per-screen alignment |
| Text | `text` | rich text (paragraphs, h2, h3, lists, quotes, links) styled without the typography plugin |
| Quote | `quote` | quoted text, author, source; also for testimonials |
| Callout | `callout` | title and rich text set apart by a side rule (info, success, warning) |
| Question & answer | `question` | native `<details>`, no JavaScript; stack several for a FAQ |
| Button | `button` | solid, outline or link |
| Image | `image` | alt text, optional caption |
| Gallery | `gallery` | list of images with alt text and caption, columns per screen, crop format |
| Spacer | `spacer` | vertical space, optional hairline |

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

To change the canvas markup, see [Editor preview](#editor-preview).

Block views opt in to in-place editing with `$editable` (see [Custom blocks](#in-place-editing)).

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
| Between the rows of a section | `settings.rows_gap` | `md` |
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

A block is a PHP class (schema, view, optional view data) and a Blade view.

```bash
php artisan make:trame-block Testimonial
# app/Trame/Blocks/TestimonialBlock.php + resources/views/trame/blocks/testimonial.blade.php
```

Classes in `app/Trame/Blocks` are discovered automatically (`trame.blocks.discover`). Classes elsewhere go
in `trame.blocks.register`. A block reusing a core `$type` replaces the core block.

### The class

```php
namespace App\Trame\Blocks;

use Godrade\Trame\Blocks\Block;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Media\MediaProvider;
use Godrade\Trame\Support\Tokens;

final class TestimonialBlock extends Block
{
    public static string $type = 'testimonial';   // stored in documents: never rename it
    public static string $category = 'content';    // palette group: content, media, layout or your own
    public static int $version = 1;

    // Blocks are resolved from the container: inject what with() needs.
    public function __construct(private readonly MediaProvider $media) {}

    public static function label(): string
    {
        return __('Testimonial');
    }

    public function schema(): array
    {
        return [
            Field::textarea('quote')->label(__('Quote'))->translatable()->required()->max(500),
            Field::text('author')->required()->max(80),
            Field::image('avatar'),
            Field::color('accent')->default('indigo-600'),
            Field::select('align')->options(Tokens::keys(Tokens::ALIGN))->default('start')->responsive(),
        ];
    }

    /** Extra view variables, computed from the values resolved for the current locale. */
    public function with(array $data): array
    {
        return [
            'avatar' => $data['avatar'] !== null ? $this->media->find($data['avatar']) : null,
            'accent' => Tokens::color($data['accent'], 'bg'),
            'alignment' => Tokens::responsive(Tokens::ALIGN_RESPONSIVE, $data['align'], 'start'),
        ];
    }

    public function view(): string
    {
        return 'trame.blocks.testimonial';
    }
}
```

The schema drives server-side validation, normalization and the editor settings panel.

### The view

The view receives every field (translatable ones already resolved for the locale), the `with()` values,
`$locale`, `$editing` (true in the editor canvas) and `$editable`.

```blade
{{-- resources/views/trame/blocks/testimonial.blade.php --}}
<figure class="{{ $alignment }}">
    <blockquote><p {!! $editable('quote') !!}>{{ $quote }}</p></blockquote>
    <figcaption class="flex items-center gap-3">
        @if($avatar)
            <img src="{{ $avatar->url }}" alt="" class="size-10 rounded-full">
        @else
            <span class="size-10 rounded-full {{ $accent['class'] }}" @if($accent['style']) style="{{ $accent['style'] }}" @endif></span>
        @endif
        <span {!! $editable('author') !!}>{{ $author }}</span>
    </figcaption>
</figure>
```

- Escape plain text with `{{ }}`. Only `richText` values are sanitized HTML and may be printed with `{!! !!}`.
- Write Tailwind classes literally (in the view or in a `Tokens`-style constant). A class built by
  concatenation is invisible to Tailwind and will be missing from the CSS.
- Do not wrap the block in margins or visibility classes: editors set those per block
  (`settings.margin_*`, `settings.hide`) and Trame adds the wrapper.

### Fields

| Factory | Stored value | Notes |
|---|---|---|
| `Field::text($name)` | string | `->max()` (255 by default) |
| `Field::textarea($name)` | string | 5000 by default |
| `Field::richText($name)` | sanitized HTML | Tiptap in the editor |
| `Field::url($name)` | string | `http(s)://`, `/`, `#`, `mailto:`, `tel:` only |
| `Field::number($name)` | int or float | `->min()`, `->max()` |
| `Field::toggle($name)` | bool | defaults to `false` |
| `Field::select($name)` | option key | `->options(['a', 'b'])` or `['a' => 'Label']`, `->responsive()` |
| `Field::color($name)` | palette token | `->allowCustom()` also accepts `#rrggbb`; render with `Tokens::color()` |
| `Field::image($name)` | media id | resolve it with `MediaProvider::find()` |
| `Field::repeater($name)` | list of items | `->fields([...])`, `->min()`, `->max()` (50 by default) |

Common modifiers: `->label()`, `->default()`, `->required()`, `->help()`, `->rules([...])` (extra Laravel
rules), `->translatable()` (text, textarea, richText and url only: one value per locale).

A repeater stores `[{"id": "itm_…", "image": 3, "caption": {"fr": "…"}}]`. Items accept text, textarea,
url, number, toggle, select, colour and image sub-fields (no rich text, no nested repeater); the editor adds,
reorders and removes them. Values reach the view resolved for the locale, as a list of arrays.

`->responsive()` (select only) stores one value per breakpoint. Render it with `Tokens::responsive()` and a
literal class map: `ALIGN_RESPONSIVE` and `JUSTIFY_RESPONSIVE` are provided, add your own for other values.
`->default(['base' => '2', 'md' => '3'])` sets a default per breakpoint.

### In-place editing

`$editable('field')` makes an element editable in the canvas by double-click; add `rich: true` for a
`richText` field. It renders nothing outside the editor. Put it on an element that contains the value only:
any extra markup or text inside it (quotes, icons) would end up in the stored value.

### Changing a schema

Adding an optional field or a default needs nothing: stored documents are normalized on read. To rename a
field or change its shape, increment `$version` and convert old data in `migrate()`. Documents are migrated
on read and on save.

```php
public static int $version = 2;

public function migrate(array $data, int $fromVersion): array
{
    if ($fromVersion < 2) {
        $data['author'] ??= $data['name'] ?? null;   // v1 called it "name"
        unset($data['name']);
    }

    return $data;
}
```

A block whose class is missing is kept untouched in stored documents and skipped when rendering, so
removing a class temporarily does not lose data.

`$icon` is reserved: the palette currently shows the first letter of the label.

## Customising views

Publish the views you want to change (`resources/views/vendor/trame`), or point the config to your own:

```bash
php artisan vendor:publish --tag=trame-views
```

### Editor preview

The canvas is an iframe rendering `trame.editor.preview.layout` with your CSS
(`trame.editor.preview.vite`). Override it to add fonts, a site header or a dark background, so the canvas
looks like the real page:

```php
// config/trame.php
'editor' => [
    'preview' => [
        'layout' => 'trame.preview',
        'vite' => ['resources/css/app.css'],
    ],
],
```

```blade
{{-- resources/views/trame/preview.blade.php --}}
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    @if(! empty($vite))
        @vite($vite)
    @endif
</head>
<body class="bg-white font-sans antialiased">
    {!! $content !!}

    {!! $bridge ?? '' !!}
</body>
</html>
```

Variables: `$content` (document rendered in editing mode), `$locale`, `$vite` and `$bridge`.
**Keep `{!! $bridge ?? '' !!}` before `</body>`:** it loads the canvas script (selection, drag and drop,
in-place editing). Without it the preview shows but nothing can be selected.

### Public pages

`Trame::pageRoutes()` renders `trame::page`, which extends `trame.pages.layout`
(default `trame::layouts.page`). Set your own layout; it must yield `title`, `description` and `content`:

```blade
{{-- resources/views/layouts/site.blade.php --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <title>@yield('title')</title>
    <meta name="description" content="@yield('description')">
    @vite('resources/css/app.css')
</head>
<body>
    @include('partials.header')
    <main>@yield('content')</main>
</body>
</html>
```

For models using `HasTrame`, render in your own views with `{!! $model->renderTrame() !!}`.

### Core blocks

Core block views live in `resources/views/vendor/trame/blocks` once published. To change a core block's
schema rather than its markup, register a block with the same `$type`.

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
