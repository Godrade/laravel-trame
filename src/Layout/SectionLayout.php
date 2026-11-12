<?php

namespace Godrade\Trame\Layout;

use Godrade\Trame\Fields\Field;
use Godrade\Trame\Media\MediaItem;
use Godrade\Trame\Support\Tokens;

final class SectionLayout
{
    // Literal class names: Tailwind scans src, so never build these by concatenation.
    private const OVERLAY_OPACITY = ['0' => 'opacity-0', '20' => 'opacity-20', '40' => 'opacity-40', '60' => 'opacity-60', '80' => 'opacity-80'];

    private const POSITION = ['center' => 'object-center', 'top' => 'object-top', 'bottom' => 'object-bottom'];

    /** @return array<int, Field> */
    public static function schema(): array
    {
        return [
            Field::color('bg')->allowCustom(),
            Field::image('bg_image'),
            Field::color('bg_overlay')->allowCustom()->advanced(),
            Field::select('bg_overlay_opacity')->options(array_keys(self::OVERLAY_OPACITY))->default('40')->advanced(),
            Field::select('bg_position')->options(array_keys(self::POSITION))->default('center')->advanced(),
            Field::color('text_color')->allowCustom()->advanced(),
            Field::select('padding')->options(Tokens::keys(Tokens::SECTION_PADDING))->default('md')->responsive()->preview('space')->advanced(),
            Field::select('rows_gap')->options(Tokens::SPACE)->default('md')->responsive()->preview('space')->advanced(),
            Field::select('container')->options(Tokens::keys(Tokens::CONTAINER))->default('boxed')->advanced(),
            Field::text('anchor')->advanced()->max(64)->rules(['alpha_dash']),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  bool  $withImage  A background photo is rendered inside the section.
     * @return array{section: string, container: string, style: string}
     */
    public static function classes(array $settings, bool $withImage = false): array
    {
        $bg = Tokens::color($settings['bg'] ?? null, 'bg');
        $text = Tokens::color($settings['text_color'] ?? null, 'text');

        return [
            'section' => Tokens::join('relative w-full', $withImage ? 'isolate overflow-hidden' : '', $bg['class'], $text['class'], Tokens::responsive(Tokens::SECTION_PADDING_RESPONSIVE, $settings['padding'] ?? null, 'md')),
            'container' => Tokens::join(
                Tokens::pick(Tokens::CONTAINER, $settings['container'] ?? null, 'boxed'),
                'flex flex-col',
                Tokens::responsive(Tokens::ROWS_GAP_RESPONSIVE, $settings['rows_gap'] ?? null, 'md'),
            ),
            'style' => Tokens::join($bg['style'], $text['style']),
        ];
    }

    /**
     * Data for the background photo of a section (rendered by layout/section).
     *
     * @param  array<string, mixed>  $settings
     * @return array{url: string, srcset: ?string, sizes: string, class: string, overlay: array{class: string, style: string}|null}
     */
    public static function background(array $settings, MediaItem $media): array
    {
        $overlay = Tokens::color($settings['bg_overlay'] ?? null, 'bg');

        return [
            'url' => $media->url,
            'srcset' => $media->srcset,
            'sizes' => '100vw',
            'class' => Tokens::pick(self::POSITION, $settings['bg_position'] ?? null, 'center'),
            'overlay' => $overlay['class'] === '' ? null : [
                'class' => Tokens::join($overlay['class'], Tokens::pick(self::OVERLAY_OPACITY, $settings['bg_overlay_opacity'] ?? null, '40')),
                'style' => $overlay['style'],
            ],
        ];
    }
}
