<?php

use Godrade\Trame\Document\DocumentNormalizer;
use Godrade\Trame\Document\DocumentValidator;
use Illuminate\Validation\ValidationException;

function validationErrors(array $document): array
{
    try {
        app(DocumentValidator::class)->validate($document);
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('accepts a valid document', function () {
    expect(validationErrors(trameDoc([heading(['fr' => 'Bonjour'])])))->toBe([]);
});

it('requires the fallback locale for required translatable fields', function () {
    $errors = validationErrors(trameDoc([heading(['en' => 'Hello'])]));

    expect($errors)->toHaveKey('document.sections.0.rows.0.columns.0.blocks.0.data.text.fr');
});

it('rejects unknown locales, colours, block types and spans', function () {
    $doc = trameDoc([
        heading(['fr' => 'Ok', 'de' => 'Hallo']),
        heading(['fr' => 'Ok'], ['color' => 'pink-400']),
        ['type' => 'carousel', 'data' => []],
    ]);
    $doc['sections'][0]['rows'][0]['columns'][0]['span'] = ['base' => 13];

    $errors = validationErrors($doc);
    $base = 'document.sections.0.rows.0.columns.0';

    expect($errors)->toHaveKeys([
        "{$base}.blocks.0.data.text",
        "{$base}.blocks.1.data.color",
        "{$base}.blocks.2.type",
        "{$base}.span.base",
    ]);
});

it('rejects javascript urls in buttons', function () {
    $errors = validationErrors(trameDoc([
        ['type' => 'button', 'data' => ['label' => ['fr' => 'Go'], 'url' => ['fr' => 'javascript:alert(1)']]],
    ]));

    expect($errors)->toHaveKey('document.sections.0.rows.0.columns.0.blocks.0.data.url.fr');
});

it('normalizes ids, defaults and spans', function () {
    $doc = app(DocumentNormalizer::class)->normalize([
        'sections' => [['rows' => [['columns' => [['span' => ['md' => 6], 'blocks' => [heading(['fr' => 'Salut'])]]]]]]],
    ]);

    $section = $doc['sections'][0];
    $column = $section['rows'][0]['columns'][0];
    $block = $column['blocks'][0];

    expect($section['id'])->toStartWith('sec_')
        ->and($section['settings'])->toMatchArray(['padding' => ['base' => 'md'], 'rows_gap' => ['base' => 'md'], 'container' => 'boxed'])
        ->and($column['span'])->toBe(['base' => 12, 'md' => 6])
        ->and($block)->toMatchArray(['type' => 'heading', 'v' => 1])
        ->and($block['data'])->toMatchArray(['text' => ['fr' => 'Salut'], 'level' => 2, 'align' => ['base' => 'start']]);
});

it('migrates blocks stored with an older version', function () {
    $doc = app(DocumentNormalizer::class)->normalize(trameDoc([
        ['type' => 'legacy-quote', 'v' => 1, 'data' => ['text' => ['fr' => 'Citation']]],
    ]));

    expect($doc['sections'][0]['rows'][0]['columns'][0]['blocks'][0])
        ->toMatchArray(['v' => 2, 'data' => ['quote' => ['fr' => 'Citation'], 'author' => null]]);
});

it('keeps unknown blocks untouched so data is not lost', function () {
    $doc = app(DocumentNormalizer::class)->normalize(trameDoc([
        ['id' => 'blk_x', 'type' => 'removed-block', 'data' => ['foo' => 'bar']],
    ]));

    expect($doc['sections'][0]['rows'][0]['columns'][0]['blocks'][0])
        ->toBe(['id' => 'blk_x', 'type' => 'removed-block', 'data' => ['foo' => 'bar']]);
});

it('sanitizes rich text', function () {
    $doc = app(DocumentNormalizer::class)->normalize(trameDoc([
        ['type' => 'text', 'data' => ['content' => ['fr' => '<p onclick="x()">Hi<script>alert(1)</script></p>']]],
    ]));

    expect($doc['sections'][0]['rows'][0]['columns'][0]['blocks'][0]['data']['content']['fr'])->toBe('<p>Hi</p>');
});
