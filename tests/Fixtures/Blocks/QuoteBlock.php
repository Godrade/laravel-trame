<?php

namespace Godrade\Trame\Tests\Fixtures\Blocks;

use Godrade\Trame\Blocks\Block;
use Godrade\Trame\Fields\Field;

/** Discovered automatically; version 2 renamed "text" to "quote". */
final class QuoteBlock extends Block
{
    public static string $type = 'legacy-quote';

    public static int $version = 2;

    public function schema(): array
    {
        return [
            Field::textarea('quote')->translatable()->required(),
            Field::text('author'),
        ];
    }

    public function migrate(array $data, int $fromVersion): array
    {
        if ($fromVersion < 2 && array_key_exists('text', $data)) {
            $data['quote'] = $data['text'];
            unset($data['text']);
        }

        return $data;
    }

    public function view(): string
    {
        return 'fixtures::quote';
    }
}
