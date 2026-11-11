<?php

use Godrade\Trame\Blocks\BlockRegistry;
use Godrade\Trame\Exceptions\InvalidBlockException;
use Godrade\Trame\Facades\Trame;

it('registers core blocks and discovers application blocks', function () {
    expect(array_keys(app(BlockRegistry::class)->all()))
        ->toContain('heading', 'text', 'image', 'button', 'spacer', 'legacy-quote');
});

it('exposes definitions for the editor', function () {
    $definitions = Trame::definitions();

    expect($definitions['locales'])->toBe(['fr', 'en'])
        ->and($definitions['grid']['breakpoints'])->toBe(['base', 'md', 'lg'])
        ->and(collect($definitions['blocks'])->firstWhere('type', 'heading')['fields'][0])
        ->toMatchArray(['name' => 'text', 'translatable' => true, 'required' => true]);
});

it('rejects reserved block types', function () {
    $class = new class extends \Godrade\Trame\Blocks\Block
    {
        public static string $type = 'global';

        public function schema(): array
        {
            return [];
        }

        public function view(): string
        {
            return '';
        }
    };

    Trame::blocks([$class::class]);
})->throws(InvalidBlockException::class);
