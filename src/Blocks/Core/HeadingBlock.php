<?php

namespace Godrade\Trame\Blocks\Core;

use Godrade\Trame\Blocks\Block;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Support\Tokens;

final class HeadingBlock extends Block
{
    public static string $type = 'heading';

    public static string $icon = 'heading';

    public static function label(): string
    {
        return __('trame::blocks.heading');
    }

    public function schema(): array
    {
        return [
            Field::text('text')->translatable()->required(),
            Field::select('level')->options([1 => 'H1', 2 => 'H2', 3 => 'H3', 4 => 'H4', 5 => 'H5', 6 => 'H6'])->default(2),
            Field::select('size')->options(Tokens::keys(Tokens::HEADING_SIZE))->default('3xl'),
            Field::select('align')->options(Tokens::keys(Tokens::ALIGN))->default('start'),
            Field::color('color')->default('slate-900'),
        ];
    }

    public function with(array $data): array
    {
        $color = Tokens::color($data['color'], 'text');

        return [
            'tag' => 'h'.max(1, min(6, (int) $data['level'])),
            'classes' => Tokens::join(
                'font-bold tracking-tight',
                Tokens::pick(Tokens::HEADING_SIZE, $data['size'], '3xl'),
                Tokens::pick(Tokens::ALIGN, $data['align'], 'start'),
                $color['class'],
            ),
            'style' => $color['style'],
        ];
    }

    public function view(): string
    {
        return 'trame::blocks.heading';
    }
}
