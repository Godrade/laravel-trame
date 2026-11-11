<?php

use Godrade\Trame\Facades\Trame;
use Godrade\Trame\Media\MediaProvider;
use Godrade\Trame\Media\SpatieMediaProvider;
use Godrade\Trame\Models\MediaLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Library model registering a "thumb" conversion, as a host would. */
class ThumbMediaLibrary extends MediaLibrary
{
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit(Fit::Crop, 100, 100)->nonQueued();
    }
}

beforeEach(function () {
    (include __DIR__.'/../../vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();

    Storage::fake('public');
    config(['media-library.disk_name' => 'public', 'trame.media.provider' => SpatieMediaProvider::class]);
    app()->forgetInstance(MediaProvider::class);
});

function spatieProvider(): SpatieMediaProvider
{
    return app(MediaProvider::class);
}

it('is bound from the config', function () {
    expect(app(MediaProvider::class))->toBeInstanceOf(SpatieMediaProvider::class);
});

it('stores uploads in the library collection with their size', function () {
    $item = spatieProvider()->store(UploadedFile::fake()->image('photo.jpg', 640, 480));
    $media = Media::query()->findOrFail($item->id);

    expect($item->name)->toBe('photo.jpg')
        ->and($item->mime)->toBe('image/jpeg')
        ->and([$item->width, $item->height])->toBe([640, 480])
        ->and($item->url)->toContain('/'.$media->id.'/')
        ->and($media->collection_name)->toBe('trame')
        ->and($media->model_type)->toBe((new MediaLibrary)->getMorphClass())
        ->and($media->model_id)->toBe(MediaLibrary::named()->id);

    Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
});

it('finds and lists only media of the library collection', function () {
    $item = spatieProvider()->store(UploadedFile::fake()->image('a.png', 10, 10));
    spatieProvider()->store(UploadedFile::fake()->image('b.png', 10, 10));

    $other = MediaLibrary::named('other')->addMedia(UploadedFile::fake()->image('other.png', 10, 10))->toMediaCollection('trame');
    $otherCollection = MediaLibrary::named()->addMedia(UploadedFile::fake()->image('avatar.png', 10, 10))->toMediaCollection('avatars');

    expect(spatieProvider()->find($item->id)?->name)->toBe('a.png')
        ->and(spatieProvider()->find((string) $item->id)?->id)->toBe($item->id)
        ->and(spatieProvider()->find($other->id))->toBeNull()
        ->and(spatieProvider()->find($otherCollection->id))->toBeNull()
        ->and(spatieProvider()->find('abc'))->toBeNull()
        ->and(spatieProvider()->find(999))->toBeNull();

    $list = spatieProvider()->list();

    expect($list['total'])->toBe(2)
        ->and(array_map(fn ($media) => $media->name, $list['data']))->toBe(['b.png', 'a.png']);
});

it('serves a generated conversion instead of the original', function () {
    config(['trame.media.spatie.model' => ThumbMediaLibrary::class, 'trame.media.spatie.conversion' => 'thumb']);
    app()->forgetInstance(MediaProvider::class);

    $item = spatieProvider()->store(UploadedFile::fake()->image('photo.jpg', 640, 480));

    expect($item->url)->toContain('conversions/')->toContain('-thumb')
        ->and($item->width)->toBeNull();
});

it('falls back to the original when the conversion is not generated', function () {
    config(['trame.media.spatie.conversion' => 'missing']);
    app()->forgetInstance(MediaProvider::class);

    $item = spatieProvider()->store(UploadedFile::fake()->image('photo.jpg', 640, 480));

    expect($item->url)->not->toContain('conversions/')
        ->and($item->width)->toBe(640);
});

it('refuses a library model that does not extend MediaLibrary', function () {
    config(['trame.media.spatie.model' => stdClass::class]);
    app()->forgetInstance(MediaProvider::class);

    spatieProvider()->list();
})->throws(InvalidArgumentException::class);

it('works through the editor API and the image block', function () {
    Gate::define('trame.edit', fn () => true);
    $this->actingAs(trameUser());

    $id = $this->postJson(route('trame.api.media.store'), ['file' => UploadedFile::fake()->image('photo.jpg', 640, 480)])
        ->assertCreated()
        ->assertJsonPath('width', 640)
        ->json('id');

    $this->getJson(route('trame.api.media.index'))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $id);

    $html = (string) Trame::render(trameDoc([['type' => 'image', 'data' => ['image' => $id, 'alt' => ['fr' => 'Photo']]]]), 'fr');

    expect($html)->toContain(spatieProvider()->find($id)->url)->toContain('width="640"');
});
