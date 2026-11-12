<?php

namespace Godrade\Trame\Layout;

use Godrade\Trame\Fields\Field;
use Godrade\Trame\Support\Grid;
use Godrade\Trame\Support\Tokens;

final class ColumnLayout
{
    /** @return array<int, Field> */
    public static function schema(): array
    {
        return [
            Field::select('valign')->options(Tokens::keys(Tokens::VALIGN))->default('start')->advanced(),
            Field::select('gap')->label(__('trame::fields.blocks_gap'))->options(Tokens::SPACE)->default('sm')->responsive()->preview('space')->advanced(),
            ...Box::fields(),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, int>  $span
     */
    public static function classes(array $settings, array $span): string
    {
        return Tokens::join(
            'flex min-w-0 flex-col',
            Tokens::responsive(Tokens::STACK_GAP_RESPONSIVE, $settings['gap'] ?? null, 'sm'),
            Grid::span($span),
            Tokens::pick(Tokens::VALIGN, $settings['valign'] ?? null, 'start'),
            Box::classes($settings)['class'],
        );
    }
}
