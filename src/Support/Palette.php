<?php

namespace Godrade\Trame\Support;

final class Palette
{
    /** @return array<int, string> Valid Tailwind colour tokens from config. */
    public static function colors(): array
    {
        return array_values(array_filter(
            (array) config('trame.palette', []),
            fn ($color) => is_string($color) && preg_match('/^[a-z0-9-]+$/', $color),
        ));
    }
}
