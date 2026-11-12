<?php

namespace Godrade\Trame\Patterns;

use Godrade\Trame\Support\Palette;
use Illuminate\Support\Facades\Lang;

/** The sections shipped with the package. Texts live in lang/{locale}/patterns.php. */
final class DefaultPattern implements Pattern
{
    public const KEYS = ['hero', 'features', 'testimonials', 'faq', 'cta', 'image_text'];

    public function __construct(private readonly string $key) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return __("trame::patterns.{$this->key}.label");
    }

    public function description(): string
    {
        return __("trame::patterns.{$this->key}.description");
    }

    public function content(array $locales, string $fallback): array
    {
        $t = fn (string $name): array => $this->translations($name, $locales, $fallback);

        return match ($this->key) {
            'hero' => $this->section(['bg' => $this->color('slate-900'), 'padding' => ['base' => 'xl']], [
                $this->row([$this->column(12, [
                    $this->heading($t('title'), 1, '5xl', 'white'),
                    $this->text($t('lead'), 'lead', 'slate-200'),
                    // On the dark section: a white solid button (dark text) and a white outline one.
                    $this->button($t('primary'), 'solid', 'white'),
                    $this->button($t('secondary'), 'outline', 'white'),
                ])]),
            ]),
            'features' => $this->section(['bg' => $this->color('slate-50'), 'padding' => ['base' => 'lg']], [
                $this->row([$this->column(12, [$this->heading($t('title'), 2, '3xl', null, 'center')])]),
                $this->row(array_map(fn (int $i) => $this->column(4, [
                    $this->block('card', ['title' => $t("item{$i}_title"), 'text' => $t("item{$i}_text")]),
                ], $this->card()), [1, 2, 3])),
            ]),
            'testimonials' => $this->section(['padding' => ['base' => 'lg']], [
                $this->row([$this->column(12, [$this->heading($t('title'), 2, '3xl', null, 'center')])]),
                $this->row(array_map(fn (int $i) => $this->column(6, [
                    $this->block('quote', [
                        'quote' => $t("quote{$i}"),
                        'author' => $this->plain("quote{$i}_author", $fallback),
                        'source' => $t("quote{$i}_source"),
                    ]),
                ]), [1, 2])),
            ]),
            'faq' => $this->section(['padding' => ['base' => 'lg']], [
                $this->row([$this->column(12, [
                    $this->heading($t('title'), 2, '3xl'),
                    ...array_map(fn (int $i) => $this->block('question', [
                        'question' => $t("q{$i}"),
                        'answer' => $this->html($t("a{$i}")),
                    ]), [1, 2, 3]),
                ])]),
            ]),
            'cta' => $this->section(['bg' => $this->color('indigo-600'), 'padding' => ['base' => 'xl']], [
                $this->row([$this->column(12, [
                    $this->heading($t('title'), 2, '4xl', 'white', 'center'),
                    $this->text($t('text'), 'lead', 'white', 'center'),
                    $this->button($t('button'), 'solid', 'slate-900', 'center'),
                ])]),
            ]),
            'image_text' => $this->section(['padding' => ['base' => 'lg']], [
                $this->row([
                    $this->column(6, [$this->block('image', ['alt' => $t('image_alt')])], ['valign' => 'center']),
                    $this->column(6, [
                        $this->heading($t('title'), 2, '3xl'),
                        $this->text($t('text')),
                        $this->button($t('button'), 'solid'),
                    ], ['valign' => 'center']),
                ]),
            ]),
            default => $this->section([], []),
        };
    }

    /** One value per locale; a locale without its own lang file uses the fallback locale's. */
    private function translations(string $name, array $locales, string $fallback): array
    {
        $key = "trame::patterns.{$this->key}.{$name}";
        $out = [];

        foreach ($locales as $locale) {
            $out[$locale] = (string) trans($key, [], Lang::hasForLocale($key, $locale) ? $locale : $fallback);
        }

        return $out;
    }

    private function plain(string $name, string $fallback): string
    {
        return (string) trans("trame::patterns.{$this->key}.{$name}", [], $fallback);
    }

    /** @param array<string, string> $texts */
    private function html(array $texts): array
    {
        return array_map(fn (string $text) => '<p>'.e($text).'</p>', $texts);
    }

    /** A palette colour, or null when the host palette does not offer it. */
    private function color(?string $name): ?string
    {
        return $name !== null && in_array($name, Palette::colors(), true) ? $name : null;
    }

    private function card(): array
    {
        return ['background' => $this->color('white'), 'radius' => 'xl', 'padding' => ['base' => 'md']];
    }

    private function heading(array $text, int $level, string $size, ?string $color = null, string $align = 'start'): array
    {
        return $this->block('heading', array_filter([
            'text' => $text,
            'level' => $level,
            'size' => $size,
            'align' => $align === 'start' ? null : ['base' => $align],
            'color' => $this->color($color),
        ], fn ($value) => $value !== null));
    }

    private function text(array $text, string $size = 'base', ?string $color = null, string $align = 'start'): array
    {
        return $this->block('text', array_filter([
            'content' => $this->html($text),
            'size' => $size,
            'align' => $align === 'start' ? null : ['base' => $align],
            'color' => $this->color($color),
        ], fn ($value) => $value !== null));
    }

    private function button(array $label, string $variant, ?string $color = null, string $align = 'start'): array
    {
        return $this->block('button', array_filter([
            'label' => $label,
            'url' => array_map(fn () => '#', $label),
            'variant' => $variant,
            'color' => $this->color($color),
            'align' => $align === 'start' ? null : ['base' => $align],
        ], fn ($value) => $value !== null));
    }

    /** @param array<string, mixed> $data */
    private function block(string $type, array $data): array
    {
        return ['type' => $type, 'data' => $data];
    }

    private function column(int $md, array $blocks, array $settings = []): array
    {
        return [
            'type' => 'column',
            'span' => $md === 12 ? ['base' => 12] : ['base' => 12, 'md' => $md],
            'settings' => array_filter($settings, fn ($value) => $value !== null),
            'blocks' => $blocks,
        ];
    }

    private function row(array $columns): array
    {
        return ['type' => 'row', 'settings' => [], 'columns' => $columns];
    }

    private function section(array $settings, array $rows): array
    {
        return [
            'type' => 'section',
            'settings' => array_filter($settings, fn ($value) => $value !== null),
            'rows' => $rows,
        ];
    }
}
