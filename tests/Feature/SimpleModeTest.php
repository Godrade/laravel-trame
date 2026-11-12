<?php

use Godrade\Trame\Facades\Trame;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Models\Page;
use Illuminate\Support\Facades\Gate;

function editorConfig(): array
{
    $content = Page::query()->firstOrCreate(['id' => 1], ['title' => ['fr' => 'Accueil'], 'slug' => ['fr' => 'accueil']])->trame();
    $html = test()->get(route('trame.editor', $content))->assertOk()->getContent();

    preg_match('/window\.TrameEditor = (\{.*?\});<\/script>/s', $html, $match);

    return json_decode($match[1] ?? 'null', true) ?? [];
}

beforeEach(function () {
    Gate::define('trame.edit', fn ($user) => true);
    $this->actingAs(trameUser());
});

it('keeps editors in simple mode unless the host grants trame.advanced', function () {
    $config = editorConfig();

    expect($config['canAdvanced'])->toBeFalse()
        ->and($config['simple']['blocks'])->toBe(['heading', 'text', 'image', 'button', 'card', 'quote', 'question'])
        ->and($config['simple']['layouts'])->toBe(['12', '6-6', '4-4-4', '8-4']);

    Gate::define('trame.advanced', fn ($user) => true);

    expect(editorConfig()['canAdvanced'])->toBeTrue();
});

it('flags advanced fields and visual previews for the editor', function () {
    $definitions = Trame::definitions();
    $field = fn (array $fields, string $name) => collect($fields)->firstWhere('name', $name);
    $heading = collect($definitions['blocks'])->firstWhere('type', 'heading')['fields'];

    expect($field($heading, 'text'))->not->toHaveKey('advanced')
        ->and($field($heading, 'size'))->toMatchArray(['advanced' => true])
        ->and($field($definitions['layout']['block'], 'radius'))->toMatchArray(['advanced' => true, 'preview' => 'radius'])
        ->and($field($definitions['layout']['block'], 'shadow'))->toMatchArray(['preview' => 'shadow'])
        ->and($field($definitions['layout']['block'], 'margin_top'))->toMatchArray(['preview' => 'space'])
        ->and($field($definitions['layout']['section'], 'bg'))->not->toHaveKey('advanced')
        ->and($field($definitions['layout']['section'], 'padding'))->toMatchArray(['advanced' => true]);

    // Every column and row setting is advanced: the simple mode shows none of them.
    expect(collect($definitions['layout']['column'])->every(fn ($f) => ($f['advanced'] ?? false) === true))->toBeTrue()
        ->and(collect($definitions['layout']['row'])->every(fn ($f) => ($f['advanced'] ?? false) === true))->toBeTrue();
});

it('refuses unknown preview kinds', function () {
    Field::select('x')->preview('gradient');
})->throws(InvalidArgumentException::class);

it('falls back to the default simple lists when a published config lacks them', function () {
    config(['trame.editor' => ['prefix' => 'trame', 'middleware' => ['web', 'auth']]]);

    expect(editorConfig()['simple']['blocks'])->toBe(['heading', 'text', 'image', 'button', 'card', 'quote', 'question']);

    config(['trame.editor.simple.blocks' => []]);

    expect(editorConfig()['simple']['blocks'])->toBe([]);
});
