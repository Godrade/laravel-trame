<?php

namespace Godrade\Trame\Media;

use Godrade\Trame\Models\MediaLibrary;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Stores the Trame media library with spatie/laravel-medialibrary.
 *
 * Media are attached to one MediaLibrary record (config trame.media.spatie.library)
 * in one collection. find() and list() never return media of other models or
 * collections, so a document cannot reference an unrelated file by id.
 */
final class SpatieMediaProvider implements MediaProvider
{
    private ?MediaLibrary $library = null;

    public function __construct(private readonly Repository $config) {}

    public function store(UploadedFile $file): MediaItem
    {
        [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];

        $media = $this->library()
            ->addMedia($file)
            ->usingName(mb_substr($file->getClientOriginalName(), 0, 255))
            ->withCustomProperties(['width' => $width, 'height' => $height])
            ->toMediaCollection($this->collection(), $this->disk());

        return $this->toItem($media);
    }

    public function find(int|string $id): ?MediaItem
    {
        if (! is_int($id) && ! ctype_digit($id)) {
            return null;
        }

        $media = $this->query()->whereKey((int) $id)->first();

        return $media ? $this->toItem($media) : null;
    }

    public function list(int $page = 1, int $perPage = 30): array
    {
        $paginator = $this->query()->latest('id')->paginate($perPage, ['*'], 'page', max(1, $page));

        return [
            'data' => array_map(fn (Media $media) => $this->toItem($media), $paginator->items()),
            'total' => $paginator->total(),
        ];
    }

    /** @return Builder<Media> */
    private function query(): Builder
    {
        $library = $this->library();
        /** @var class-string<Media> $model */
        $model = $this->config->get('media-library.media_model', Media::class);

        return $model::query()
            ->where('model_type', $library->getMorphClass())
            ->where('model_id', $library->getKey())
            ->where('collection_name', $this->collection());
    }

    private function library(): MediaLibrary
    {
        $model = (string) $this->config->get('trame.media.spatie.model', MediaLibrary::class);

        if (! is_a($model, MediaLibrary::class, true)) {
            throw new InvalidArgumentException("[{$model}] must extend ".MediaLibrary::class.'.');
        }

        return $this->library ??= $model::named((string) $this->config->get('trame.media.spatie.library', 'default'));
    }

    private function collection(): string
    {
        return (string) $this->config->get('trame.media.spatie.collection', 'trame');
    }

    private function disk(): string
    {
        return (string) ($this->config->get('trame.media.spatie.disk') ?? $this->config->get('media-library.disk_name', 'public'));
    }

    private function toItem(Media $media): MediaItem
    {
        $conversion = $this->config->get('trame.media.spatie.conversion');
        $useConversion = is_string($conversion) && $conversion !== '' && $media->hasGeneratedConversion($conversion);
        $width = $media->getCustomProperty('width');
        $height = $media->getCustomProperty('height');

        return new MediaItem(
            id: $media->getKey(),
            url: $useConversion ? $media->getUrl($conversion) : $media->getUrl(),
            name: (string) $media->name,
            mime: (string) $media->mime_type,
            // A conversion may change the size: only expose the original's when serving the original.
            width: $useConversion || ! is_int($width) ? null : $width,
            height: $useConversion || ! is_int($height) ? null : $height,
        );
    }
}
