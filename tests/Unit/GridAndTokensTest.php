<?php

use Godrade\Trame\Support\Grid;
use Godrade\Trame\Support\Tokens;

it('builds responsive span classes and defaults base to 12', function () {
    expect(Grid::span(['md' => 6, 'lg' => 4]))->toBe('col-span-12 md:col-span-6 lg:col-span-4');
});

it('clamps spans and ignores unknown breakpoints', function () {
    expect(Grid::normalizeSpan(['base' => 0, 'md' => 40, 'xl' => 3]))->toBe(['base' => 1, 'md' => 12]);
});

it('maps palette colours to classes and hex colours to css variables', function () {
    expect(Tokens::color('slate-900', 'text'))->toBe(['class' => 'text-slate-900', 'style' => ''])
        ->and(Tokens::color('#ff3366', 'bg'))->toBe(['class' => 'bg-(--trame-bg)', 'style' => '--trame-bg: #ff3366;']);
});

it('refuses colours outside the palette or with injected css', function () {
    expect(Tokens::color('red-500 hidden', 'text'))->toBe(['class' => '', 'style' => ''])
        ->and(Tokens::color('#fff;display:none', 'bg'))->toBe(['class' => '', 'style' => '']);
});
