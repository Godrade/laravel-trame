<?php

use Godrade\Trame\Facades\Trame;

it('shows a placeholder for an empty block in the editor only', function () {
    $doc = trameDoc([['type' => 'text', 'data' => []]]);

    $editor = (string) Trame::render($doc, 'fr', true);
    expect($editor)->toContain('dashed currentColor')->toContain('block is empty');

    $public = (string) Trame::render($doc, 'fr');
    expect($public)->not->toContain('dashed')->not->toContain('block is empty');
});

it('does not add a placeholder to filled blocks or blocks with their own', function () {
    expect((string) Trame::render(trameDoc([heading(['fr' => 'Salut'])]), 'fr', true))->not->toContain('dashed currentColor');

    $html = (string) Trame::render(trameDoc([['type' => 'image', 'data' => []], ['type' => 'gallery', 'data' => []]]), 'fr', true);
    expect($html)->not->toContain('dashed currentColor')->toContain('border-dashed');
});

it('does not turn spacers into placeholders', function () {
    expect((string) Trame::render(trameDoc([['type' => 'spacer', 'data' => []]]), 'fr', true))->not->toContain('dashed currentColor');
});
