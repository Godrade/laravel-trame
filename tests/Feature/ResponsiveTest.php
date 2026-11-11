<?php

use Godrade\Trame\Document\DocumentNormalizer;
use Godrade\Trame\Document\DocumentValidator;
use Godrade\Trame\Facades\Trame;
use Godrade\Trame\Fields\Field;
use Illuminate\Validation\ValidationException;

function responsiveErrors(array $document): array
{
    try {
        app(DocumentValidator::class)->validate($document);
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

const BLOCK_PATH = 'document.sections.0.rows.0.columns.0.blocks.0';

/* ------------------------------------------------------------ responsive fields */

it('only allows select fields to be responsive', function () {
    Field::text('title')->responsive();
})->throws(InvalidArgumentException::class);

it('normalizes responsive values, reading a legacy scalar as base', function () {
    $normalize = fn (mixed $align) => app(DocumentNormalizer::class)
        ->normalize(trameDoc([heading(['fr' => 'X'], ['align' => $align])]))['sections'][0]['rows'][0]['columns'][0]['blocks'][0]['data']['align'];

    expect($normalize('center'))->toBe(['base' => 'center'])
        ->and($normalize(['base' => 'center', 'md' => 'start', 'lg' => 'nope', 'xl' => 'end']))->toBe(['base' => 'center', 'md' => 'start'])
        ->and($normalize(['md' => 'end']))->toBe(['base' => 'start', 'md' => 'end']);
});

it('validates responsive values', function () {
    expect(responsiveErrors(trameDoc([heading(['fr' => 'X'], ['align' => ['base' => 'center', 'lg' => 'end']])])))->toBe([])
        ->and(responsiveErrors(trameDoc([heading(['fr' => 'X'], ['align' => 'center'])])))->toBe([])
        ->and(responsiveErrors(trameDoc([heading(['fr' => 'X'], ['align' => ['base' => 'center', 'xl' => 'end']])])))->toHaveKey(BLOCK_PATH.'.data.align')
        ->and(responsiveErrors(trameDoc([heading(['fr' => 'X'], ['align' => ['md' => 'middle']])])))->toHaveKey(BLOCK_PATH.'.data.align');
});

it('renders one class per breakpoint for responsive values', function () {
    $html = (string) Trame::render(trameDoc([heading(['fr' => 'X'], ['align' => ['base' => 'center', 'lg' => 'start']])]), 'fr');

    expect($html)->toContain('text-center lg:text-start')
        ->and((string) Trame::render(trameDoc([heading(['fr' => 'X'], ['align' => 'end'])]), 'fr'))->toContain('text-end');
});

it('exposes the responsive flag to the editor', function () {
    expect(Field::select('align')->options(['start'])->responsive()->toArray())->toMatchArray(['responsive' => true])
        ->and(Field::select('align')->options(['start'])->toArray())->not->toHaveKey('responsive');
});

/* ------------------------------------------------------------ hide per breakpoint */

function hiddenDoc(array $hide): array
{
    $doc = trameDoc([[...heading(['fr' => 'Bloc']), 'settings' => ['hide' => $hide]]]);
    $doc['sections'][0]['settings']['hide'] = $hide;
    $doc['sections'][0]['rows'][0]['settings']['hide'] = $hide;
    $doc['sections'][0]['rows'][0]['columns'][0]['settings'] = ['hide' => $hide];

    return $doc;
}

it('stores hide only when not empty, with known breakpoints in order', function () {
    $section = app(DocumentNormalizer::class)->normalize(hiddenDoc(['lg', 'base', 'lg', 'xl']))['sections'][0];
    $column = $section['rows'][0]['columns'][0];

    expect($section['settings']['hide'])->toBe(['base', 'lg'])
        ->and($section['rows'][0]['settings']['hide'])->toBe(['base', 'lg'])
        ->and($column['settings']['hide'])->toBe(['base', 'lg'])
        ->and($column['blocks'][0]['settings'])->toBe(['hide' => ['base', 'lg']]);

    $plain = app(DocumentNormalizer::class)->normalize(hiddenDoc([]))['sections'][0];

    expect($plain['settings'])->not->toHaveKey('hide')
        ->and($plain['rows'][0]['columns'][0]['blocks'][0])->not->toHaveKey('settings');
});

it('rejects unknown breakpoints in hide', function () {
    expect(responsiveErrors(hiddenDoc(['base', 'md'])))->toBe([]);

    $errors = responsiveErrors(hiddenDoc(['mobile']));

    expect($errors)->toHaveKeys([
        'document.sections.0.settings.hide',
        'document.sections.0.rows.0.settings.hide',
        'document.sections.0.rows.0.columns.0.settings.hide',
        BLOCK_PATH.'.settings.hide',
    ]);

    expect(responsiveErrors(trameDoc([[...heading(['fr' => 'X']), 'settings' => 'nope']])))->toHaveKey(BLOCK_PATH.'.settings');
});

it('hides nodes with responsive classes in public output', function () {
    $html = (string) Trame::render(hiddenDoc(['base']), 'fr');

    expect($html)
        ->toMatch('/<section\s+class="[^"]*max-md:hidden"/')
        ->toMatch('/<div class="grid grid-cols-12[^"]*max-md:hidden"/')
        ->toMatch('/<div class="flex min-w-0[^"]*max-md:hidden"/')
        ->toMatch('/<div class="max-md:hidden"><h2/')
        ->not->toContain('data-trame-hide');

    expect((string) Trame::render(hiddenDoc(['md', 'lg']), 'fr'))->toContain('<div class="md:max-lg:hidden lg:hidden"><h2');
});

it('keeps hidden nodes visible but marked in the editor', function () {
    $html = (string) Trame::render(hiddenDoc(['md']), 'fr', editing: true);

    expect($html)
        ->toContain('data-trame-node="section" data-trame-hide="md"')
        ->toContain('data-trame-node="block" data-trame-hide="md"')
        ->not->toContain('md:max-lg:hidden');
});

it('accepts a per-breakpoint default for responsive fields', function () {
    $field = Field::select('columns')->options(['1', '2', '3'])->default(['base' => '1', 'md' => '3', 'xl' => '2'])->responsive();

    expect($field->defaultFor('fr'))->toBe(['base' => '1', 'md' => '3'])
        // Numeric option keys come back as integers (PHP array keys); rendering casts them to strings.
        ->and($field->normalize(['md' => '2'], ['fr'], app(\Godrade\Trame\Support\HtmlSanitizer::class)))->toEqual(['base' => '1', 'md' => '2'])
        ->and($field->normalize('9', ['fr'], app(\Godrade\Trame\Support\HtmlSanitizer::class)))->toEqual(['base' => '1']);
});
