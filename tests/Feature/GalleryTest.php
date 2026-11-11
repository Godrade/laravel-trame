<?php

use Godrade\Trame\Facades\Trame;
use Godrade\Trame\Media\MediaProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function galleryBlock(array $data): array
{
    return ['type' => 'gallery', 'data' => $data];
}

function storeImage(string $name = 'a.png'): int
{
    return app(MediaProvider::class)->store(UploadedFile::fake()->image($name, 10, 10))->id;
}

beforeEach(fn () => Storage::fake('public'));

it('renders a grid of figures with responsive columns', function () {
    $a = storeImage('a.png');
    $b = storeImage('b.png');

    $html = (string) Trame::render(trameDoc([galleryBlock([
        'images' => [
            ['image' => $a, 'alt' => ['fr' => 'Chat'], 'caption' => ['fr' => 'Mon chat']],
            ['image' => $b],
        ],
        'columns' => ['base' => '1', 'md' => '2', 'lg' => '4'],
    ])]), 'fr');

    expect($html)
        ->toContain('grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-4')
        ->toContain('alt="Chat"')
        ->toContain('alt=""')
        ->toContain('<figcaption class="mt-2 text-sm opacity-70">Mon chat</figcaption>')
        ->toContain('loading="lazy"')
        ->toContain('rounded-(--trame-radius)')
        ->toContain('width="10" height="10"');

    expect(substr_count($html, '<figure>'))->toBe(2)
        ->and(substr_count($html, '<figcaption'))->toBe(1);
});

it('defaults to two columns on phones, three from tablets, and no ratio', function () {
    $html = (string) Trame::render(trameDoc([galleryBlock(['images' => [['image' => storeImage()]]])]), 'fr');

    expect($html)->toContain('grid gap-4 grid-cols-2 md:grid-cols-3')->not->toContain('aspect-')->not->toContain('object-cover');
});

it('applies the ratio with object-cover', function (string $ratio, string $class) {
    $html = (string) Trame::render(trameDoc([galleryBlock(['images' => [['image' => storeImage()]], 'ratio' => $ratio])]), 'fr');

    expect($html)->toContain($class)->toContain('object-cover');
})->with([
    ['square', 'aspect-square'],
    ['landscape', 'aspect-[4/3]'],
    ['portrait', 'aspect-[3/4]'],
]);

it('skips items whose media is missing', function () {
    $html = (string) Trame::render(trameDoc([galleryBlock(['images' => [
        ['image' => storeImage()],
        ['image' => 99999],
        ['image' => null],
    ]])]), 'fr');

    expect(substr_count($html, '<figure>'))->toBe(1);
});

it('renders nothing without images, except a placeholder when editing', function () {
    $doc = trameDoc([galleryBlock(['images' => []])]);

    expect((string) Trame::render($doc, 'fr'))->not->toContain('<figure')->not->toContain('border-dashed')
        ->and((string) Trame::render($doc, 'fr', editing: true))->toContain('flex aspect-video w-full items-center justify-center border border-dashed border-current/30 text-sm opacity-60');
});
