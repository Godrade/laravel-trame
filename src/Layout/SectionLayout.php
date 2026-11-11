<?php

namespace Godrade\Trame\Layout;

use Godrade\Trame\Fields\Field;
use Godrade\Trame\Support\Tokens;

final class SectionLayout
{
    /** @return array<int, Field> */
    public static function schema(): array
    {
        return [
            Field::color('bg')->allowCustom(),
            Field::select('padding')->options(Tokens::keys(Tokens::SECTION_PADDING))->default('md')->responsive(),
            Field::select('rows_gap')->options(Tokens::SPACE)->default('none')->responsive(),
            Field::select('container')->options(Tokens::keys(Tokens::CONTAINER))->default('boxed'),
            Field::text('anchor')->max(64)->rules(['alpha_dash']),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{section: string, container: string, style: string}
     */
    public static function classes(array $settings): array
    {
        $bg = Tokens::color($settings['bg'] ?? null, 'bg');

        return [
            'section' => Tokens::join('relative w-full', $bg['class'], Tokens::responsive(Tokens::SECTION_PADDING_RESPONSIVE, $settings['padding'] ?? null, 'md')),
            'container' => Tokens::join(
                Tokens::pick(Tokens::CONTAINER, $settings['container'] ?? null, 'boxed'),
                'flex flex-col',
                Tokens::responsive(Tokens::ROWS_GAP_RESPONSIVE, $settings['rows_gap'] ?? null, 'none'),
            ),
            'style' => $bg['style'],
        ];
    }
}
