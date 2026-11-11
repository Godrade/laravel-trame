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

    /** Literal classes per variant (Tailwind must see them written out). */
    public const VARIANTS = [
        'solid' => 'rounded-(--trame-radius) px-4 py-2 hover:opacity-85',
        'outline' => 'rounded-(--trame-radius) border px-4 py-2 hover:bg-current/5',
        'link' => 'underline decoration-current/40 underline-offset-4 hover:decoration-current',
    ];

    public function schema(): array
    {
        return [
            Field::text('label')->translatable()->required()->max(80),
            Field::url('url')->translatable()->required(),
            Field::toggle('new_tab'),
            Field::select('variant')->options(array_keys(self::VARIANTS))->default('solid'),
            // No default: solid buttons use the theme accent (--trame-accent), others the text colour.
            Field::color('color'),
            Field::select('align')->options(Tokens::keys(Tokens::JUSTIFY))->default('start')->responsive(),
        ];
    }

    public function with(array $data): array
    {
        $variant = isset(self::VARIANTS[$data['variant']]) ? $data['variant'] : 'solid';
        $color = match ($variant) {
            'solid' => Tokens::color($data['color'], 'bg'),
            'outline' => Tokens::color($data['color'], 'border'),
            default => ['class' => '', 'style' => ''],
        };
        $text = $variant === 'solid' ? ['class' => '', 'style' => ''] : Tokens::color($data['color'], 'text');

        $colorClasses = match (true) {
            $variant === 'solid' && $color['class'] === '' => 'bg-(--trame-accent) text-(--trame-on-accent)',
            $variant === 'solid' => $color['class'].' text-white',
            default => Tokens::join($color['class'], $text['class']),
        };

        $url = (string) $data['url'];

        return [
            // Same allow-list as the url field rule, re-applied for previews of unsaved data.
            'href' => preg_match('/^(https?:\/\/|\/|#|mailto:|tel:)/i', $url) ? $url : '#',
            'wrapper' => Tokens::join('flex', Tokens::responsive(Tokens::JUSTIFY_RESPONSIVE, $data['align'], 'start')),
            'classes' => Tokens::join(
                'inline-flex items-center justify-center gap-2 font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current',
                self::VARIANTS[$variant],
                $colorClasses,
            ),
            'style' => $color['style'].$text['style'],
        ];
    }

    public function view(): string
    {
        return 'trame::blocks.button';
    }
}
