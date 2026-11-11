<?php

use Godrade\Trame\Document\DocumentNormalizer;
use Godrade\Trame\Document\DocumentValidator;
use Godrade\Trame\Facades\Trame;
use Godrade\Trame\Layout\Box;
use Godrade\Trame\Media\MediaProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function appearanceDoc(array $block, array $blockSettings = [], array $columnSettings = []): array
{
    $doc = trameDoc([[...$block, ...($blockSettings === [] ? [] : ['settings' => $blockSettings])]]);
    $doc['sections'][0]['rows'][0]['columns'][0]['settings'] = $columnSettings;

    return $doc;
}

function appearanceErrors(array $document): array
{
    try {
        app(DocumentValidator::class)->validate($document);
    } catch (ValidationException $e) {
        return array_keys($e->errors());
    }

    return [];
}

it('maps appearance settings to classes and custom colours to variables', function () {
    $box = Box::classes([
        'background' => '#fef3c7', 'text_color' => 'slate-900', 'padding' => ['base' => 'sm', 'lg' => 'lg'],
        'border' => 'thin', 'radius' => 'xl', 'shadow' => 'lg', 'class' => 'brand-card is-featured',
    ]);

    expect($box['class'])->toBe('bg-(--trame-bg) text-slate-900 p-4 lg:p-12 border border-current/20 rounded-xl overflow-hidden shadow-lg brand-card is-featured')
        ->and($box['style'])->toBe('--trame-bg: #fef3c7;')
        ->and($box['framed'])->toBeTrue()
        ->and($box['padded'])->toBeTrue()
        ->and(Box::classes([]))->toBe(['class' => '', 'style' => '', 'framed' => false, 'padded' => false])
        ->and(Box::classes(['class' => 'x" onclick="y'])['class'])->toBe('');
});

it('wraps plain blocks with their appearance', function () {
    $html = (string) Trame::render(appearanceDoc(heading(['fr' => 'Titre']), ['background' => 'slate-900', 'text_color' => 'white', 'padding' => ['base' => 'md'], 'radius' => 'theme']), 'fr');

    expect($html)->toMatch('/<div class="bg-slate-900 text-white p-8 rounded-\(--trame-radius\) overflow-hidden"><h2/');
});

it('styles columns', function () {
    $html = (string) Trame::render(appearanceDoc(heading(['fr' => 'Titre']), columnSettings: ['background' => '#112233', 'shadow' => 'md', 'padding' => ['base' => 'lg']]), 'fr');

    expect($html)->toMatch('/<div class="flex min-w-0 flex-col[^"]* bg-\(--trame-bg\) p-12 shadow-md"\s+style="--trame-bg: #112233;"/');
});

it('stores only the appearance values that are set', function () {
    $section = app(DocumentNormalizer::class)->normalize(appearanceDoc(heading(['fr' => 'X']), ['shadow' => 'md', 'class' => ''], ['valign' => 'center']))['sections'][0];
    $column = $section['rows'][0]['columns'][0];

    expect($column['settings'])->toBe(['valign' => 'center', 'gap' => ['base' => 'sm']])
        ->and($column['blocks'][0]['settings'])->toBe(['shadow' => 'md']);
});

it('validates appearance values', function () {
    expect(appearanceErrors(appearanceDoc(heading(['fr' => 'X']), ['background' => '#abcdef', 'radius' => 'full', 'class' => 'a b-c md:x'])))->toBe([]);

    expect(appearanceErrors(appearanceDoc(heading(['fr' => 'X']), ['background' => 'url(x)', 'shadow' => 'huge', 'class' => '<script>'], ['border' => 'dotted'])))
        ->toContain('document.sections.0.rows.0.columns.0.blocks.0.settings.background')
        ->toContain('document.sections.0.rows.0.columns.0.blocks.0.settings.shadow')
        ->toContain('document.sections.0.rows.0.columns.0.blocks.0.settings.class')
        ->toContain('document.sections.0.rows.0.columns.0.settings.border');
});

it('puts the appearance on the card itself and pads framed cards', function () {
    Storage::fake('public');
    $media = app(MediaProvider::class)->store(UploadedFile::fake()->image('a.png', 40, 30));
    $card = ['type' => 'card', 'data' => ['image' => $media->id, 'title' => ['fr' => 'Offre'], 'text' => ['fr' => 'Détail'], 'link_label' => ['fr' => 'Voir'], 'url' => ['fr' => '/offre']]];

    $framed = (string) Trame::render(appearanceDoc($card, ['background' => 'white', 'radius' => '2xl', 'shadow' => 'xl']), 'fr');

    expect($framed)
        ->toContain('<article class="flex flex-1 flex-col bg-white rounded-2xl overflow-hidden shadow-xl"')
        ->not->toMatch('/<div class="[^"]*bg-white[^"]*"><article/')
        ->toContain('class="flex flex-1 flex-col  p-6 "')
        ->toContain('<a href="/offre"')
        ->toContain('aspect-[4/3]');

    $plain = (string) Trame::render(appearanceDoc($card), 'fr');

    expect($plain)->toContain('<article class="flex flex-1 flex-col "')->toContain('pt-4');

    $padded = (string) Trame::render(appearanceDoc($card, ['background' => 'white', 'padding' => ['base' => 'sm']]), 'fr');

    expect($padded)->not->toContain(' p-6 ');
});
