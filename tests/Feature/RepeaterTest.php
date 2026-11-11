<?php

use Godrade\Trame\Document\DocumentNormalizer;
use Godrade\Trame\Document\DocumentValidator;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Fields\Schema;
use Godrade\Trame\Support\HtmlSanitizer;
use Godrade\Trame\Support\Localizer;
use Illuminate\Validation\ValidationException;

const GALLERY_PATH = 'document.sections.0.rows.0.columns.0.blocks.0.data';

function galleryDoc(array $data): array
{
    return trameDoc([['type' => 'gallery', 'data' => $data]]);
}

function galleryErrors(array $data): array
{
    try {
        app(DocumentValidator::class)->validate(galleryDoc($data));
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

function normalizedImages(array $data): array
{
    return app(DocumentNormalizer::class)->normalize(galleryDoc($data))['sections'][0]['rows'][0]['columns'][0]['blocks'][0]['data']['images'];
}

function repeaterField(): Field
{
    return Field::repeater('items')->fields([
        Field::text('title')->translatable()->required(),
        Field::toggle('featured'),
    ]);
}

it('defaults to an empty list and exposes its definition', function () {
    $field = Field::repeater('images')->fields([Field::image('image')->required(), Field::text('alt')->translatable()])->min(1)->max(5);
    $definition = $field->toArray();

    expect($field->defaultFor('fr'))->toBe([])
        ->and($definition)->toMatchArray(['name' => 'images', 'type' => 'repeater', 'min' => 1, 'max' => 5])
        ->and(array_column($definition['fields'], 'type', 'name'))->toBe(['image' => 'image', 'alt' => 'text'])
        ->and(Field::repeater('x')->toArray()['max'])->toBe(50)
        ->and(in_array('repeater', Field::TYPES))->toBeTrue();
});

it('rejects unsupported sub-fields', function (Field $sub) {
    Field::repeater('items')->fields([$sub]);
})->with([
    'rich text' => [fn () => Field::richText('body')],
    'nested repeater' => [fn () => Field::repeater('nested')],
    'responsive select' => [fn () => Field::select('size')->options(['a'])->responsive()],
])->throws(InvalidArgumentException::class);

it('cannot itself be translatable or responsive', function () {
    expect(fn () => Field::repeater('items')->translatable())->toThrow(InvalidArgumentException::class)
        ->and(fn () => Field::repeater('items')->responsive())->toThrow(InvalidArgumentException::class)
        ->and(fn () => Field::text('x')->fields([]))->toThrow(InvalidArgumentException::class);
});

it('normalizes items: ids kept or generated, junk and unknown keys dropped', function () {
    $images = normalizedImages(['images' => [
        ['id' => 'itm_ab12cd34ef', 'image' => '3', 'alt' => ['fr' => ' Chat ', 'de' => 'Katze'], 'rogue' => 'x'],
        'junk',
        ['id' => 'bad id!', 'image' => 4],
        ['id' => 'itm_ab12cd34ef', 'image' => 5],
        ['image' => null],
    ], 'columns' => '3']);

    expect($images)->toHaveCount(4)
        ->and($images[0])->toBe(['id' => 'itm_ab12cd34ef', 'image' => 3, 'alt' => ['fr' => 'Chat'], 'caption' => []])
        ->and($images[1]['id'])->toMatch('/^itm_[a-z0-9]{10}$/')
        ->and($images[2]['id'])->toMatch('/^itm_[a-z0-9]{10}$/')
        ->and(collect($images)->pluck('id')->unique())->toHaveCount(4)
        ->and($images[3]['image'])->toBeNull();
});

it('turns a non-array value into an empty list and truncates to max', function () {
    expect(normalizedImages(['images' => 'nope']))->toBe([]);

    $many = array_map(fn (int $i) => ['image' => $i], range(1, 30));

    expect(normalizedImages(['images' => $many]))->toHaveCount(24);
});

it('validates the list with paths per item and sub-field', function () {
    $errors = galleryErrors(['images' => [
        ['id' => 'itm_aaaaaaaaaa', 'image' => 1, 'alt' => ['fr' => 'ok']],
        ['id' => 'itm_bbbbbbbbbb', 'image' => null],
    ]]);

    expect($errors)->toHaveKey(GALLERY_PATH.'.images.1.image')
        ->and($errors)->not->toHaveKey(GALLERY_PATH.'.images.0.image');
});

it('requires the fallback locale of required translatable sub-fields per item', function () {
    $rules = Schema::rules([repeaterField()], app(Localizer::class));
    $validator = validator(['items' => [['title' => ['en' => 'Hi']]]], $rules);

    expect($validator->errors()->keys())->toContain('items.0.title.fr');
});

it('rejects non-list values and counts outside min and max', function () {
    $field = Field::repeater('items')->fields([Field::text('t')])->min(1)->max(2);
    $rules = $field->validationRules(['fr'], 'fr');
    $check = fn (array $data) => validator($data, $rules)->errors()->keys();

    expect($check(['items' => [['t' => 'a']]]))->toBe([])
        ->and($check(['items' => [['t' => 'a'], ['t' => 'b'], ['t' => 'c']]]))->toContain('items')
        ->and($check(['items' => []]))->toContain('items')
        ->and($check(['items' => ['a' => ['t' => 'x']]]))->toContain('items')
        ->and($check(['items' => 'x']))->toContain('items');
});

it('resolves item translations for a locale', function () {
    $fields = [repeaterField()];
    $data = ['items' => [
        ['id' => 'itm_aaaaaaaaaa', 'title' => ['fr' => 'Bonjour', 'en' => 'Hello'], 'featured' => true],
        ['id' => 'itm_bbbbbbbbbb', 'title' => ['fr' => 'Salut'], 'featured' => false],
    ]];

    $resolved = Schema::resolve($fields, $data, 'en', app(Localizer::class));

    expect($resolved['items'])->toBe([
        ['id' => 'itm_aaaaaaaaaa', 'title' => 'Hello', 'featured' => true],
        ['id' => 'itm_bbbbbbbbbb', 'title' => 'Salut', 'featured' => false],
    ]);
});

it('drops items with a missing required translation in hide mode without hiding the block', function () {
    config(['trame.missing_translation' => 'hide']);

    $data = ['items' => [
        ['id' => 'itm_aaaaaaaaaa', 'title' => ['fr' => 'Bonjour', 'en' => 'Hello'], 'featured' => true],
        ['id' => 'itm_bbbbbbbbbb', 'title' => ['fr' => 'Salut'], 'featured' => false],
    ]];

    $resolved = Schema::resolve([repeaterField()], $data, 'en', app(Localizer::class));

    expect($resolved)->not->toBeNull()
        ->and(array_column($resolved['items'], 'id'))->toBe(['itm_aaaaaaaaaa']);
});

it('fills defaults in Schema::normalize', function () {
    $out = Schema::normalize([repeaterField()], [], app(Localizer::class), app(HtmlSanitizer::class));

    expect($out)->toBe(['items' => []]);
});
