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
            Field::select('valign')->options(Tokens::keys(Tokens::VALIGN))->default('start'),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, int>  $span
     */
    public static function classes(array $settings, array $span): string
    {
        return Tokens::join(
            'flex min-w-0 flex-col gap-4',
            Grid::span($span),
            Tokens::pick(Tokens::VALIGN, $settings['valign'] ?? null, 'start'),
        );
    }
}
