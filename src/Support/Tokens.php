<?php

namespace Godrade\Trame\Support;

/**
 * Semantic value => Tailwind classes.
 *
 * Every class below is written literally so Tailwind detects it when the
 * host scans this package (see resources/css/trame.css). Do not build
 * these class names dynamically.
 */
final class Tokens
{
    public const ALIGN = ['start' => 'text-start', 'center' => 'text-center', 'end' => 'text-end'];

    public const JUSTIFY = ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'];

    /** Per-breakpoint variants, for Field::select(...)->responsive() values. */
    public const ALIGN_RESPONSIVE = [
        'base' => self::ALIGN,
        'md' => ['start' => 'md:text-start', 'center' => 'md:text-center', 'end' => 'md:text-end'],
        'lg' => ['start' => 'lg:text-start', 'center' => 'lg:text-center', 'end' => 'lg:text-end'],
    ];

    public const JUSTIFY_RESPONSIVE = [
        'base' => self::JUSTIFY,
        'md' => ['start' => 'md:justify-start', 'center' => 'md:justify-center', 'end' => 'md:justify-end'],
        'lg' => ['start' => 'lg:justify-start', 'center' => 'lg:justify-center', 'end' => 'lg:justify-end'],
    ];

    /**
     * Breakpoint => class hiding an element on that breakpoint only.
     * Range variants leave the element's display untouched elsewhere.
     */
    public const HIDE = ['base' => 'max-md:hidden', 'md' => 'md:max-lg:hidden', 'lg' => 'lg:hidden'];

    public const HEADING_SIZE = ['xl' => 'text-xl', '2xl' => 'text-2xl', '3xl' => 'text-3xl', '4xl' => 'text-4xl', '5xl' => 'text-5xl'];

    public const SECTION_PADDING = ['none' => 'py-0', 'sm' => 'py-8', 'md' => 'py-12', 'lg' => 'py-20', 'xl' => 'py-32'];

    public const CONTAINER = ['boxed' => 'mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8', 'full' => 'w-full'];

    /**
     * A 12-track grid always has 11 column gaps, even when items span 12:
     * 11 × gap-10 (40px) = 440px overflows a 390px phone. Column gaps stay
     * small on mobile; row gaps (stacked columns) keep the full size.
     */
    public const GAP = [
        'none' => 'gap-0',
        'sm' => 'gap-x-2 gap-y-4 md:gap-x-4',
        'md' => 'gap-x-3 gap-y-6 md:gap-x-6',
        'lg' => 'gap-x-4 gap-y-10 md:gap-x-10',
    ];

    public const VALIGN = ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'];

    public const ROUNDED = ['none' => 'rounded-none', 'md' => 'rounded-md', 'lg' => 'rounded-lg', 'full' => 'rounded-full'];

    public const SPACER = ['sm' => 'h-4', 'md' => 'h-8', 'lg' => 'h-16', 'xl' => 'h-24'];

    /** @param array<string, string> $map */
    public static function pick(array $map, mixed $key, string $default): string
    {
        return $map[(string) $key] ?? $map[$default];
    }

    /**
     * Responsive value ({"base": "center", "md": "start"}, or a plain scalar
     * treated as base) => classes. md and lg are optional and inherit.
     *
     * @param  array<string, array<string, string>>  $maps  breakpoint => (value => class)
     */
    public static function responsive(array $maps, mixed $value, string $default): string
    {
        $value = is_array($value) ? $value : ['base' => $value];
        $classes = [self::pick($maps['base'], $value['base'] ?? null, $default)];

        foreach (['md', 'lg'] as $breakpoint) {
            $key = $value[$breakpoint] ?? null;

            if ($key !== null && isset($maps[$breakpoint][(string) $key])) {
                $classes[] = $maps[$breakpoint][(string) $key];
            }
        }

        return implode(' ', $classes);
    }

    /** @param mixed $hide List of breakpoints the node is hidden on. */
    public static function hide(mixed $hide): string
    {
        return implode(' ', array_map(fn (string $breakpoint) => self::HIDE[$breakpoint], self::hiddenOn($hide)));
    }

    /**
     * Known breakpoints only, deduplicated, in breakpoint order.
     *
     * @return array<int, string>
     */
    public static function hiddenOn(mixed $hide): array
    {
        $hide = is_array($hide) ? array_map(strval(...), array_filter($hide, is_scalar(...))) : [];

        return array_values(array_filter(array_keys(self::HIDE), fn (string $breakpoint) => in_array($breakpoint, $hide, true)));
    }

    /** @param array<string, string> $map @return array<int, string> */
    public static function keys(array $map): array
    {
        return array_keys($map);
    }

    /**
     * Palette token => utility class; custom hex => CSS variable + arbitrary utility.
     *
     * @param  'text'|'bg'|'border'  $utility
     * @return array{class: string, style: string}
     */
    public static function color(mixed $value, string $utility): array
    {
        if (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return ['class' => "{$utility}-(--trame-{$utility})", 'style' => "--trame-{$utility}: {$value};"];
        }

        if (is_string($value) && in_array($value, Palette::colors(), true)) {
            return ['class' => "{$utility}-{$value}", 'style' => ''];
        }

        return ['class' => '', 'style' => ''];
    }

    public static function join(?string ...$classes): string
    {
        return trim((string) preg_replace('/\s+/', ' ', implode(' ', array_filter($classes))));
    }
}
