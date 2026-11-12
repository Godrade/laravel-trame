<?php

use Godrade\Trame\Facades\Trame;
use Godrade\Trame\Media\MediaProvider;
use Godrade\Trame\Media\Sizes;
use Godrade\Trame\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

it('generates resized variants and a srcset', function () {
    $item = app(MediaProvider::class)->store(UploadedFile::fake()->image('a.jpg', 2000, 1000));
    $variants = Media::query()->find($item->id)->variants;

    expect(array_column($variants, 'width'))->toBe([480, 960, 1600]);
    foreach ($variants as $v) {
        Storage::disk('public')->assertExists($v['path']);
    }
    expect(getimagesizefromstring(Storage::disk('public')->get($variants[0]['path'])))->toMatchArray([480, 240])
        ->and($item->srcset)->toContain('-480w.jpg 480w')->toEndWith($item->url.' 2000w')
        ->and(app(MediaProvider::class)->find($item->id)->srcset)->toBe($item->srcset);
});

it('only makes variants narrower than the original, and uses the configured widths', function () {
    config(['trame.media.widths' => [300, 1000]]);
    $item = app(MediaProvider::class)->store(UploadedFile::fake()->image('a.png', 500, 500));

    expect(array_column(Media::query()->find($item->id)->variants, 'width'))->toBe([300]);
});

it('skips variants for small images and unsupported formats', function () {
    $small = app(MediaProvider::class)->store(UploadedFile::fake()->image('s.jpg', 300, 300));
    $gif = app(MediaProvider::class)->store(UploadedFile::fake()->image('g.gif', 2000, 1000));

    expect($small->srcset)->toBeNull()->and($gif->srcset)->toBeNull();
});

it('outputs srcset and sizes from the column span', function () {
    $id = app(MediaProvider::class)->store(UploadedFile::fake()->image('a.jpg', 2000, 1000))->id;
    $doc = trameDoc([['type' => 'image', 'data' => ['image' => $id]]], [['type' => 'card', 'data' => ['image' => $id, 'title' => ['fr' => 'T']]]], [['type' => 'gallery', 'data' => ['images' => [['image' => $id]], 'columns' => ['base' => '2', 'md' => '4']]]]);
    $doc['sections'][0]['rows'][0]['columns'] = array_map(fn ($c) => [...$c, 'span' => ['base' => 12, 'md' => 6, 'lg' => 4]], $doc['sections'][0]['rows'][0]['columns']);

    $html = (string) Trame::render($doc, 'fr');

    expect($html)
        ->toContain('sizes="(min-width: 1024px) 33vw, (min-width: 768px) 50vw, 100vw"')
        ->toContain('sizes="(min-width: 1024px) 8vw, (min-width: 768px) 13vw, 50vw"')
        ->toContain('srcset="');
});

it('computes sizes', function () {
    expect(Sizes::string(Sizes::fromSpan(['base' => 12])))->toBe('100vw')
        ->and(Sizes::string(Sizes::fromSpan(['base' => 12, 'md' => 6])))->toBe('(min-width: 768px) 50vw, 100vw')
        ->and(Sizes::string(Sizes::divide(Sizes::fromSpan(['base' => 12]), ['base' => 2, 'md' => 3])))->toBe('(min-width: 768px) 33vw, 50vw');
});

it('skips resized copies of photos above the pixel budget', function () {
    Storage::fake('public');
    config(['trame.media.max_pixels' => 1000]);

    $item = app(\Godrade\Trame\Media\MediaProvider::class)->store(\Illuminate\Http\UploadedFile::fake()->image('big.jpg', 2000, 1000));

    expect($item->srcset)->toBeNull()
        ->and(\Godrade\Trame\Models\Media::query()->find($item->id)->variants)->toBeNull();
});
