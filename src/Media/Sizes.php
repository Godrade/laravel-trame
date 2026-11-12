<?php

namespace Godrade\Trame\Media;

/**
 * Builds the `sizes` attribute of responsive images from the width a column
 * takes in the 12-column grid. Gutters and container limits are ignored: the
 * result is an estimate that lets the browser pick a close-enough candidate.
 */
final class Sizes
{
    private const QUERIES = ['lg' => '(min-width: 1024px)', 'md' => '(min-width: 768px)'];

    /**
     * @param  array<string, mixed>  $span  {"base": 12, "md": 6, "lg": 4}; md and lg inherit.
     * @return array{base: int, md: int, lg: int} Share of the viewport width (vw) per breakpoint.
     */
    public static function fromSpan(array $span): array
    {
        $columns = 12;
        $out = [];

        foreach (['base', 'md', 'lg'] as $breakpoint) {
            $value = isset($span[$breakpoint]) && is_numeric($span[$breakpoint]) ? (int) $span[$breakpoint] : $columns;
            $columns = max(1, min(12, $value));
            $out[$breakpoint] = (int) round($columns / 12 * 100);
        }

        return $out;
    }

    /**
     * Splits a width between $counts items per row ({"base": 2, "md": 3}; md and lg inherit).
     *
     * @param  array<string, int>  $vw
     * @param  array<string, mixed>  $counts
     * @return array{base: int, md: int, lg: int}
     */
    public static function divide(array $vw, array $counts): array
    {
        $count = 1;
        $out = [];

        foreach (['base', 'md', 'lg'] as $breakpoint) {
            if (isset($counts[$breakpoint]) && is_numeric($counts[$breakpoint])) {
                $count = max(1, (int) $counts[$breakpoint]);
            }

            $out[$breakpoint] = (int) round(($vw[$breakpoint] ?? 100) / $count);
        }

        return $out;
    }

    /** @param  array<string, int>  $vw */
    public static function string(array $vw): string
    {
        $parts = [];
        $previous = $vw['base'] ?? 100;

        foreach (['md', 'lg'] as $breakpoint) {
            $value = $vw[$breakpoint] ?? $previous;

            if ($value !== $previous) {
                array_unshift($parts, self::QUERIES[$breakpoint].' '.$value.'vw');
            }

            $previous = $value;
        }

        $parts[] = ($vw['base'] ?? 100).'vw';

        return implode(', ', $parts);
    }
}
