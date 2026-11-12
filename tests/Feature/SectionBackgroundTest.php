<?php

use Godrade\Trame\Facades\Trame;
use Godrade\Trame\Media\MediaProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

function bgDoc(array $settings): array
{
    $doc = trameDoc([heading(['fr' => 'Salut'])]);
    $doc['sections'][0]['settings'] = $settings;

    return $doc;
}

it('renders a cover image and an overlay in the section', function () {
    $id = app(MediaProvider::class)->store(UploadedFile::fake()->image('hero.jpg', 2000, 1000))->id;

    $html = (string) Trame::render(bgDoc([
        'bg_image' => $id, 'bg_overlay' => '#112233', 'bg_overlay_opacity' => '60', 'bg_position' => 'top', 'text_color' => 'white',
    ]), 'fr');

    expect($html)
        ->toContain('relative w-full isolate overflow-hidden')
        ->toContain('absolute inset-0 -z-10 size-full object-cover object-top')
        ->toContain('alt=""')
        ->toContain('sizes="100vw"')
        ->toContain('srcset=')
        ->toContain('absolute inset-0 -z-10 bg-(--trame-bg) opacity-60')
        ->toContain('--trame-bg: #112233;')
        ->toContain('text-white');
});

it('has no overlay without a colour, and defaults position and opacity', function () {
    $id = app(MediaProvider::class)->store(UploadedFile::fake()->image('hero.jpg', 100, 100))->id;

    $html = (string) Trame::render(bgDoc(['bg_image' => $id]), 'fr');

    expect($html)->toContain('object-center')->not->toContain('aria-hidden="true" class="absolute');

    $html = (string) Trame::render(bgDoc(['bg_image' => $id, 'bg_overlay' => 'black']), 'fr');
    expect($html)->toContain('bg-black opacity-40');
});

it('renders nothing extra when the media is missing', function () {
    $html = (string) Trame::render(bgDoc(['bg_image' => 9999]), 'fr');

    expect($html)->not->toContain('<img')->not->toContain('isolate');
});

it('also renders in the editor canvas', function () {
    $id = app(MediaProvider::class)->store(UploadedFile::fake()->image('hero.jpg', 100, 100))->id;

    expect((string) Trame::render(bgDoc(['bg_image' => $id]), 'fr', true))->toContain('object-cover');
});
