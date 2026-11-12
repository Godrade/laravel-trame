<?php

namespace Godrade\Trame\Patterns;

/**
 * A ready-made section offered in the editor's "Add" panel.
 * Register your own with config('trame.patterns.extra').
 */
interface Pattern
{
    /** Unique key, e.g. "hero". */
    public function key(): string;

    public function label(): string;

    /** One line shown under the label. */
    public function description(): string;

    /**
     * A section node ({type: 'section', settings, rows}); ids and defaults are filled
     * in afterwards. Translatable fields are {locale: text} maps for every locale.
     *
     * @param  array<int, string>  $locales
     * @return array<string, mixed>
     */
    public function content(array $locales, string $fallback): array;
}
