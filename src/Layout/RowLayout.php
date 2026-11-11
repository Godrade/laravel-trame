<?php

namespace Godrade\Trame\Layout;

use Godrade\Trame\Fields\Field;
use Godrade\Trame\Support\Tokens;

final class RowLayout
{
    public const MAX_COLUMNS = 12;

    /** @return array<int, Field> */
    public static function schema(): array
    {
        return [
            Field::select('gap')->options(Tokens::keys(Tokens::GAP))->default('md'),
        ];
    }

    /** @param array<string, mixed> $settings */
    public static function classes(array $settings): string
    {
        return Tokens::join('grid grid-cols-12', Tokens::pick(Tokens::GAP, $settings['gap'] ?? null, 'md'));
    }
}
