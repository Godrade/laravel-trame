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

    public const WIDTHS = ['readable' => 'max-w-[65ch]', 'full' => ''];

    /** "lead" is an introduction paragraph, one step larger. */
    public const SIZES = ['base' => 'leading-7', 'lead' => 'text-lg leading-8 sm:text-xl sm:leading-9'];

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public static function label(): string
    {
        return __('trame::blocks.text');
    }

    public function schema(): array
    {
        return [
            Field::richText('content')->translatable()->required(),
            // "readable" keeps lines under ~65 characters, whatever the column width.
            Field::select('width')->options(array_keys(self::WIDTHS))->default('readable'),
            Field::select('size')->options(array_keys(self::SIZES))->default('base'),
            Field::select('align')->options(Tokens::keys(Tokens::ALIGN))->default('start')->responsive(),
            Field::color('color'),
        ];
    }

    public function with(array $data): array
    {
        $color = Tokens::color($data['color'], 'text');

        return [
            // Sanitized on save; sanitized again here in case data was written outside the API.
            'html' => $this->sanitizer->sanitize((string) $data['content']),
            'classes' => Tokens::join(
                Tokens::PROSE,
                self::SIZES[$data['size']] ?? '',
                self::WIDTHS[$data['width']] ?? self::WIDTHS['readable'],
                ($data['width'] ?? 'readable') === 'readable' ? Tokens::responsive(Tokens::MEASURE_ALIGN_RESPONSIVE, $data['align'], 'start') : null,
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
