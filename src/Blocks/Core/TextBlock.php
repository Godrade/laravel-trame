<?php

namespace Godrade\Trame\Blocks\Core;

use Godrade\Trame\Blocks\Block;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Support\HtmlSanitizer;
use Godrade\Trame\Support\Tokens;

final class TextBlock extends Block
{
    public static string $type = 'text';

    public static string $icon = 'text';

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public static function label(): string
    {
        return __('trame::blocks.text');
    }

    public function schema(): array
    {
        return [
            Field::richText('content')->translatable()->required(),
            Field::select('align')->options(Tokens::keys(Tokens::ALIGN))->default('start')->responsive(),
            Field::color('color')->default('slate-700'),
        ];
    }

    public function with(array $data): array
    {
        $color = Tokens::color($data['color'], 'text');

        return [
            // Sanitized on save; sanitized again here in case data was written outside the API.
            'html' => $this->sanitizer->sanitize((string) $data['content']),
            'classes' => Tokens::join(
                'space-y-4 leading-relaxed',
                Tokens::responsive(Tokens::ALIGN_RESPONSIVE, $data['align'], 'start'),
                $color['class'],
            ),
            'style' => $color['style'],
        ];
    }

    public function view(): string
    {
        return 'trame::blocks.text';
    }
}
