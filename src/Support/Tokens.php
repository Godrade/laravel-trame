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

    /** Spacing scale shared by gaps and margins: none 0, xs .5rem, sm 1rem, md 2rem, lg 3rem, xl 4rem, 2xl 6rem. */
    public const SPACE = ['none', 'xs', 'sm', 'md', 'lg', 'xl', '2xl'];

    /** Space between the blocks of a column (flex gap). */
    public const STACK_GAP_RESPONSIVE = [
        'base' => ['none' => 'gap-0', 'xs' => 'gap-2', 'sm' => 'gap-4', 'md' => 'gap-8', 'lg' => 'gap-12', 'xl' => 'gap-16', '2xl' => 'gap-24'],
        'md' => ['none' => 'md:gap-0', 'xs' => 'md:gap-2', 'sm' => 'md:gap-4', 'md' => 'md:gap-8', 'lg' => 'md:gap-12', 'xl' => 'md:gap-16', '2xl' => 'md:gap-24'],
        'lg' => ['none' => 'lg:gap-0', 'xs' => 'lg:gap-2', 'sm' => 'lg:gap-4', 'md' => 'lg:gap-8', 'lg' => 'lg:gap-12', 'xl' => 'lg:gap-16', '2xl' => 'lg:gap-24'],
    ];

    /** Space between the rows of a section (flex gap). */
    public const ROWS_GAP_RESPONSIVE = self::STACK_GAP_RESPONSIVE;

    public const MARGIN_TOP_RESPONSIVE = [
        'base' => ['none' => 'mt-0', 'xs' => 'mt-2', 'sm' => 'mt-4', 'md' => 'mt-8', 'lg' => 'mt-12', 'xl' => 'mt-16', '2xl' => 'mt-24'],
        'md' => ['none' => 'md:mt-0', 'xs' => 'md:mt-2', 'sm' => 'md:mt-4', 'md' => 'md:mt-8', 'lg' => 'md:mt-12', 'xl' => 'md:mt-16', '2xl' => 'md:mt-24'],
        'lg' => ['none' => 'lg:mt-0', 'xs' => 'lg:mt-2', 'sm' => 'lg:mt-4', 'md' => 'lg:mt-8', 'lg' => 'lg:mt-12', 'xl' => 'lg:mt-16', '2xl' => 'lg:mt-24'],
    ];

    public const MARGIN_BOTTOM_RESPONSIVE = [
        'base' => ['none' => 'mb-0', 'xs' => 'mb-2', 'sm' => 'mb-4', 'md' => 'mb-8', 'lg' => 'mb-12', 'xl' => 'mb-16', '2xl' => 'mb-24'],
        'md' => ['none' => 'md:mb-0', 'xs' => 'md:mb-2', 'sm' => 'md:mb-4', 'md' => 'md:mb-8', 'lg' => 'md:mb-12', 'xl' => 'md:mb-16', '2xl' => 'md:mb-24'],
        'lg' => ['none' => 'lg:mb-0', 'xs' => 'lg:mb-2', 'sm' => 'lg:mb-4', 'md' => 'lg:mb-8', 'lg' => 'lg:mb-12', 'xl' => 'lg:mb-16', '2xl' => 'lg:mb-24'],
    ];

    public const SECTION_PADDING_RESPONSIVE = [
        'base' => ['none' => 'py-0', 'sm' => 'py-8', 'md' => 'py-12', 'lg' => 'py-20', 'xl' => 'py-32'],
        'md' => ['none' => 'md:py-0', 'sm' => 'md:py-8', 'md' => 'md:py-12', 'lg' => 'md:py-20', 'xl' => 'md:py-32'],
        'lg' => ['none' => 'lg:py-0', 'sm' => 'lg:py-8', 'md' => 'lg:py-12', 'lg' => 'lg:py-20', 'xl' => 'lg:py-32'],
    ];

    /**
     * Breakpoint => class hiding an element on that breakpoint only.
     * Range variants leave the element's display untouched elsewhere.
     */
    public const HIDE = ['base' => 'max-md:hidden', 'md' => 'md:max-lg:hidden', 'lg' => 'lg:hidden'];

    /** Large sizes tighten tracking and leading; small ones keep the defaults. */
    public const HEADING_SIZE = [
        'xl' => 'text-xl',
        '2xl' => 'text-2xl',
        '3xl' => 'text-3xl tracking-tight',
        '4xl' => 'text-4xl leading-tight tracking-tight',
        '5xl' => 'text-5xl leading-none tracking-tight',
    ];

    public const SECTION_PADDING = ['none' => 'py-0', 'sm' => 'py-8', 'md' => 'py-12', 'lg' => 'py-20', 'xl' => 'py-32'];

    public const CONTAINER = ['boxed' => 'mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8', 'full' => 'w-full'];

    /**
     * A 12-track grid always has 11 column gaps, even when items span 12:
     * 11 × gap-10 (40px) = 440px overflows a 390px phone. Column gaps stay
     * small on mobile; row gaps (stacked columns) keep the full size.
     */
    public const GAP = [
        'none' => 'gap-0',
        'sm' => 'gap-x-2 gap-y-6 md:gap-x-4',
        'md' => 'gap-x-3 gap-y-10 md:gap-x-6',
        'lg' => 'gap-x-4 gap-y-14 md:gap-x-10',
    ];

    public const VALIGN = ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'];

    /** "md" follows the host theme radius (--trame-radius, see resources/css/trame.css). */
    public const ROUNDED = ['none' => 'rounded-none', 'md' => 'rounded-(--trame-radius)', 'lg' => 'rounded-2xl', 'full' => 'rounded-full'];

    /**
     * Rich text from the editor (paragraphs, h2, h3, lists, blockquotes, links).
     * Tailwind's preflight resets lists and headings: this restores them without
     * the typography plugin, in the host's font and colour.
     */
    public const PROSE = 'text-pretty [&>*+*]:mt-4 [&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:leading-tight [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:ps-6 [&_ol]:ps-6 [&_li+li]:mt-1 [&_li::marker]:opacity-60 [&_a]:underline [&_a]:decoration-current/40 [&_a]:underline-offset-4 [&_a:hover]:decoration-current [&_strong]:font-semibold [&_blockquote]:border-s-2 [&_blockquote]:border-current/20 [&_blockquote]:ps-4';

    /**
     * Positions a width-limited element (readable text) according to its alignment,
     * so a centred paragraph is also a centred column.
     */
    public const MEASURE_ALIGN_RESPONSIVE = [
        'base' => ['start' => 'ms-0 me-auto', 'center' => 'mx-auto', 'end' => 'ms-auto me-0'],
        'md' => ['start' => 'md:ms-0 md:me-auto', 'center' => 'md:mx-auto', 'end' => 'md:ms-auto md:me-0'],
        'lg' => ['start' => 'lg:ms-0 lg:me-auto', 'center' => 'lg:mx-auto', 'end' => 'lg:ms-auto lg:me-0'],
    ];

    /** Surface tinted with the current text colour: a quiet panel on any background. */
    public const SURFACE = 'bg-current/5';

    /** Hairline in the current text colour: works on light and dark sections. */
    public const RULE = 'border-current/20';

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
