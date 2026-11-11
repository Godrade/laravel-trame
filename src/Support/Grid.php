<?php

namespace Godrade\Trame\Support;

final class Grid
{
    public const COLUMNS = 12;

    /** Breakpoint => Tailwind variant prefix. Fixed: classes are safelisted in trame.css. */
    public const BREAKPOINTS = ['base' => '', 'md' => 'md:', 'lg' => 'lg:'];

    /** @return array<string, int> Known breakpoints only, clamped to 1..12, base defaults to 12. */
    public static function normalizeSpan(mixed $span): array
    {
        $span = is_array($span) ? $span : [];
        $out = ['base' => self::COLUMNS];

        foreach (array_keys(self::BREAKPOINTS) as $breakpoint) {
            if (isset($span[$breakpoint]) && is_numeric($span[$breakpoint])) {
                $out[$breakpoint] = max(1, min(self::COLUMNS, (int) $span[$breakpoint]));
            }
        }

        return $out;
    }

    /** @param array<string, int> $span */
    public static function span(array $span): string
    {
        $classes = [];

        foreach (self::normalizeSpan($span) as $breakpoint => $columns) {
            $classes[] = self::BREAKPOINTS[$breakpoint].'col-span-'.$columns;
        }

        return implode(' ', $classes);
    }
}
