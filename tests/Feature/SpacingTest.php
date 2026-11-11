<?php

use Godrade\Trame\Document\DocumentNormalizer;
use Godrade\Trame\Document\DocumentValidator;
use Godrade\Trame\Facades\Trame;
use Illuminate\Validation\ValidationException;

function spacingDoc(array $section = [], array $column = [], array $block = []): array
{
    $doc = trameDoc([[...heading(['fr' => 'Bloc']), ...($block === [] ? [] : ['settings' => $block])]]);
    $doc['sections'][0]['settings'] = $section;
    $doc['sections'][0]['rows'][0]['columns'][0]['settings'] = $column;

    return $doc;
}

function spacingErrors(array $document): array
{
    try {
        app(DocumentValidator::class)->validate($document);
    } catch (ValidationException $e) {
        return array_keys($e->errors());
    }

    return [];
}

it('uses the default spacing', function () {
    $html = (string) Trame::render(spacingDoc(), 'fr');

    expect($html)
        ->toMatch('/<section\s+class="relative w-full py-12"/')
        ->toContain('class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 flex flex-col gap-8"')
        ->toContain('class="flex min-w-0 flex-col gap-4 col-span-12')
        ->toMatch('/<div class="flex min-w-0[^"]*"\s*><h2/');
});

it('renders section padding, rows gap and blocks gap per breakpoint', function () {
    $html = (string) Trame::render(spacingDoc(
        section: ['padding' => ['base' => 'sm', 'lg' => 'xl'], 'rows_gap' => ['base' => 'md', 'md' => 'lg']],
        column: ['gap' => ['base' => 'xs', 'lg' => '2xl']],
    ), 'fr');

    expect($html)
        ->toContain('py-8 lg:py-32')
        ->toContain('flex flex-col gap-8 md:gap-12')
        ->toContain('flex min-w-0 flex-col gap-2 lg:gap-24');
});

it('reads a legacy scalar section padding as base', function () {
    expect((string) Trame::render(spacingDoc(section: ['padding' => 'lg']), 'fr'))->toMatch('/<section\s+class="relative w-full py-20"/');
});

it('wraps blocks with margins, in public output and in the editor', function () {
    $doc = spacingDoc(block: ['margin_top' => ['base' => 'lg'], 'margin_bottom' => ['md' => 'xl'], 'hide' => ['lg']]);

    expect((string) Trame::render($doc, 'fr'))->toContain('<div class="mt-12 mb-0 md:mb-16 lg:hidden"><h2');

    expect((string) Trame::render($doc, 'fr', editing: true))
        ->toContain('<div class="mt-12 mb-0 md:mb-16" data-trame-id=')
        ->not->toContain('lg:hidden');
});

it('stores only the block settings that are set', function () {
    $block = fn (array $settings) => app(DocumentNormalizer::class)
        ->normalize(spacingDoc(block: $settings))['sections'][0]['rows'][0]['columns'][0]['blocks'][0];

    expect($block([]))->not->toHaveKey('settings')
        ->and($block(['margin_top' => null, 'margin_bottom' => ['base' => 'nope']]))->not->toHaveKey('settings')
        ->and($block(['margin_bottom' => ['lg' => 'md'], 'junk' => 1])['settings'])->toBe(['margin_bottom' => ['lg' => 'md']]);
});

it('validates spacing values', function () {
    expect(spacingErrors(spacingDoc(section: ['rows_gap' => ['base' => 'md']], column: ['gap' => 'lg'], block: ['margin_top' => ['md' => 'sm']])))->toBe([]);

    expect(spacingErrors(spacingDoc(section: ['rows_gap' => 'huge'], column: ['gap' => ['xl' => 'sm']], block: ['margin_bottom' => ['base' => '3xl']])))
        ->toContain('document.sections.0.settings.rows_gap')
        ->toContain('document.sections.0.rows.0.columns.0.settings.gap')
        ->toContain('document.sections.0.rows.0.columns.0.blocks.0.settings.margin_bottom');
});

it('exposes block layout fields to the editor', function () {
    expect(array_column(Trame::definitions()['layout']['block'], 'name'))->toBe(['margin_top', 'margin_bottom']);
});
