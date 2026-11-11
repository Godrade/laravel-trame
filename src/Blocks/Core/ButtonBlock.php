<?php

namespace Godrade\Trame\Blocks\Core;

use Godrade\Trame\Blocks\Block;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Support\Tokens;

final class ButtonBlock extends Block
{
    public static string $type = 'button';

    public static string $icon = 'cursor-click';

    public static function label(): string
    {
        return __('trame::blocks.button');
    }

    public function schema(): array
    {
        return [
            Field::text('label')->translatable()->required()->max(80),
            Field::url('url')->translatable()->required(),
            Field::toggle('new_tab'),
            Field::select('variant')->options(['solid', 'outline'])->default('solid'),
            Field::color('color')->default('indigo-600'),
            Field::select('align')->options(Tokens::keys(Tokens::JUSTIFY))->default('start'),
        ];
    }

    public function with(array $data): array
    {
        $outline = $data['variant'] === 'outline';
        $bg = Tokens::color($data['color'], 'bg');
        $border = Tokens::color($data['color'], 'border');
        $text = Tokens::color($data['color'], 'text');

        $url = (string) $data['url'];

        return [
            // Same allow-list as the url field rule, re-applied for previews of unsaved data.
            'href' => preg_match('/^(https?:\/\/|\/|#|mailto:|tel:)/i', $url) ? $url : '#',
            'wrapper' => Tokens::join('flex', Tokens::pick(Tokens::JUSTIFY, $data['align'], 'start')),
            'classes' => Tokens::join(
                'inline-flex items-center rounded-md px-5 py-2.5 text-sm font-semibold transition hover:opacity-90',
                $outline ? 'border-2 bg-transparent' : 'text-white',
                $outline ? $border['class'] : $bg['class'],
                $outline ? $text['class'] : null,
            ),
            'style' => $outline ? $border['style'].$text['style'] : $bg['style'],
        ];
    }

    public function view(): string
    {
        return 'trame::blocks.button';
    }
}
