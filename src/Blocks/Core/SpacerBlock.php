<?php

namespace Godrade\Trame\Blocks\Core;

use Godrade\Trame\Blocks\Block;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Support\Tokens;

final class SpacerBlock extends Block
{
    public static string $type = 'spacer';

    public static string $icon = 'arrows-vertical';

    public static string $category = 'layout';

    public static function label(): string
    {
        return __('trame::blocks.spacer');
    }

    public function schema(): array
    {
        return [
            Field::select('size')->options(Tokens::keys(Tokens::SPACER))->default('md'),
            Field::toggle('divider'),
            Field::color('color')->default('slate-200'),
        ];
    }

    public function with(array $data): array
    {
        $border = Tokens::color($data['color'], 'border');

        return [
            'classes' => Tokens::join('flex items-center', Tokens::pick(Tokens::SPACER, $data['size'], 'md')),
            'divider' => (bool) $data['divider'],
            'line' => Tokens::join('w-full border-t', $border['class']),
            'style' => $border['style'],
        ];
    }

    public function view(): string
    {
        return 'trame::blocks.spacer';
    }
}
