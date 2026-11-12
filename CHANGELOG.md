# Changelog

All notable changes to `godrade/laravel-trame`. The project follows [Semantic Versioning](https://semver.org);
before 1.0, minor versions may contain breaking changes.

## v0.1.0 (unreleased)

First release.

### Document and rendering
- JSON document: sections, rows, 12-column grid columns (width per breakpoint `base`, `md`, `lg`), blocks.
- Global and reusable templates for sections, rows and blocks.
- Semantic settings mapped to literal Tailwind classes (`Tokens`), Tailwind v4 sources and palette generator
  (`trame:sources`).
- Per-screen settings: hide any node per breakpoint, responsive select fields (alignment of the heading,
  text and button blocks), spacing between rows, columns and blocks, block margins, section padding.
- Multilingual: shared layout, `->translatable()` fields, fallback or hide mode.
- Core blocks: heading, text, card, quote, callout, question & answer, button, image, gallery, spacer. Neutral
  design that inherits the host font and text colour; theme hooks `--trame-accent`, `--trame-on-accent`,
  `--trame-radius`; rich text lists, links and subheadings styled without the typography plugin.
- Appearance settings on every block and column: background, text colour, padding per screen, border and
  border colour, radius, shadow, CSS class. Blocks can apply them to their own root (`Block::$box`).
- Card block; text block readable width and lead size; button sizes; highlighted quote; callout icons.
- Autosave of the draft and revision history with restore (`trame_revisions` table, `trame.revisions`).
- Section background image with overlay, position and text colour.
- Responsive images: `srcset`/`sizes` from the column width, resized copies on upload (`trame.media.widths`).
- Starter sections (patterns) and internal page links (`page:{id}`, `LinkResolver`, `trame.links.page_url`).
- Locked sections (structure frozen in simple mode), checks panel (translations to do, images without
  alternative text, heading levels), hints on empty blocks in the canvas.
- Solid buttons pick dark or white text from the chosen colour's lightness.
- Simple and advanced editor modes: simple by default (content only, short palette), advanced with the
  `trame.advanced` gate and a toolbar toggle. `Field::advanced()`, `trame.editor.simple.blocks` and `.layouts`.
- Visual samples for radius, shadow, border and spacing options (`Field::preview()`).
- Repeater field (`Field::repeater()->fields([...])`) with add, reorder and remove in the editor.
- Responsive select defaults per breakpoint (`->default(['base' => '2', 'md' => '3'])`).
- Rich text sanitized with `symfony/html-sanitizer`; URL, colour and locale validation.
- Published render cache per content, published document and locale (`trame:clear`).

### Editor
- Standalone page with prebuilt assets, preview iframe using the host CSS.
- Selection, drag and drop in the canvas and from the palette, in-place text editing (`$editable`).
- Settings panel generated from schemas, live validation, undo/redo, optimistic locking (409).
- Mobile, tablet and desktop preview; per-device values.
- Media library, template library, global template edit mode.

### Integration
- `HasTrame` trait, `Page` model, opt-in public page routes (`Trame::pageRoutes()`).
- `trame.edit` gate, denied by default; `trame.view` gate for a read-only editor (demo, reviewers): writes are
  refused and hidden, previews are rate limited for viewers.
- Media providers: `LocalDiskProvider` (default) and `SpatieMediaProvider`
  (`spatie/laravel-medialibrary` ^11.23).
- `make:trame-block` generator, block discovery in `app/Trame/Blocks`.
- Laravel 12 and 13, PHP 8.3 and 8.4.
