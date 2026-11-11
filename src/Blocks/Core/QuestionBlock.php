<?php

namespace Godrade\Trame\Blocks\Core;

use Godrade\Trame\Blocks\Block;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Support\HtmlSanitizer;
use Godrade\Trame\Support\Tokens;

/**
 * One question and its answer in a native <details> element: accessible,
 * searchable by the browser, no JavaScript. Stack several for a FAQ.
 */
final class QuestionBlock extends Block
{
    public static string $type = 'question';

    public static string $icon = 'question';

    public static string $category = 'content';

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public static function label(): string
    {
        return __('trame::blocks.question');
    }

    public function schema(): array
    {
        return [
            Field::text('question')->translatable()->required()->max(200),
            Field::richText('answer')->translatable()->required(),
            Field::toggle('open'),
        ];
    }

    public function with(array $data): array
    {
        return [
            'answer' => $this->sanitizer->sanitize((string) $data['answer']),
            'classes' => Tokens::join('group border-b py-4', Tokens::RULE),
            'prose' => Tokens::join('mt-3 leading-7', Tokens::PROSE),
        ];
    }

    public function view(): string
    {
        return 'trame::blocks.question';
    }
}
