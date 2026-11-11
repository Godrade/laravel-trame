<?php

namespace Godrade\Trame\Layout;

use Godrade\Trame\Fields\Field;
use Godrade\Trame\Support\Tokens;

/**
 * Settings shared by every block, stored in the block's "settings" next to
 * "hide" (its own fields live in "data"). Unset values are not stored.
 */
final class BlockLayout
{
    /** @return array<int, Field> */
    public static function schema(): array
    {
        return [
            Field::select('margin_top')->options(Tokens::SPACE)->responsive()->group(__('trame::fields.group_spacing')),
            Field::select('margin_bottom')->options(Tokens::SPACE)->responsive(),
            ...Box::fields(),
        ];
    }

    /**
     * Margin classes only; appearance classes come from Box::classes().
     *
     * @param  array<string, mixed>  $settings
     */
    public static function classes(array $settings): string
    {
        return Tokens::join(
            isset($settings['margin_top']) ? Tokens::responsive(Tokens::MARGIN_TOP_RESPONSIVE, $settings['margin_top'], 'none') : '',
            isset($settings['margin_bottom']) ? Tokens::responsive(Tokens::MARGIN_BOTTOM_RESPONSIVE, $settings['margin_bottom'], 'none') : '',
        );
    }
}
