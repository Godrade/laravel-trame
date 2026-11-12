<?php

namespace Godrade\Trame\Blocks\Core;

use Godrade\Trame\Blocks\Block;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Support\Tokens;

/**
 * Quotation or testimonial: the quoted text, who said it and where.
 * No quotation marks are added: editors write them if the language needs them.
 */
final class QuoteBlock extends Block
{
    public static string $type = 'quote';

    public static string $icon = 'quote';

    public static string $category = 'content';

    /** Literal classes per size (Tailwind must see them written out). */
    public const SIZES = [
        'md' => 'text-lg leading-8',
        'lg' => 'text-2xl leading-snug font-medium tracking-tight sm:text-3xl',
    ];

    /** A highlighted quote takes the theme accent for its rule; a regular one a hairline. */
    public const RULES = [
        'md' => 'border-s-2 border-current/20',
        'lg' => 'border-s-4 border-(--trame-accent)',
    ];

    public static function label(): string
    {
        return __('trame::blocks.quote');
    }

    public function schema(): array
    {
        return [
            Field::textarea('quote')->translatable()->required()->max(1000),
            Field::text('author')->max(120),
            Field::text('source')->translatable()->max(160),
            Field::select('size')->options(['md' => __('trame::options.labels.normal'), 'lg' => __('trame::options.labels.highlighted')])->default('md')->advanced(),
            Field::color('color')->advanced(),
        ];
    }

    public function with(array $data): array
    {
        $color = Tokens::color($data['color'], 'text');

        return [
            'figure' => Tokens::join('ps-6', self::RULES[$data['size']] ?? self::RULES['md'], $color['class']),
            'style' => $color['style'],
            'text' => Tokens::join('text-pretty', self::SIZES[$data['size']] ?? self::SIZES['md']),
        ];
    }

    public function view(): string
    {
        return 'trame::blocks.quote';
    }
}
