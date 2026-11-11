<?php

namespace Godrade\Trame\Media;

use Godrade\Trame\Models\Media;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class LocalDiskProvider implements MediaProvider
{
    public function __construct(private readonly Repository $config) {}

    public function store(UploadedFile $file): MediaItem
    {
        $disk = (string) $this->config->get('trame.media.disk', 'public');
        $size = @getimagesize($file->getRealPath()) ?: [null, null];

        $media = Media::query()->create([
            'disk' => $disk,
            'path' => $file->store((string) $this->config->get('trame.media.directory', 'trame'), $disk),
            'name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'width' => $size[0],
            'height' => $size[1],
        ]);

        return $this->toItem($media);
    }

    public function find(int|string $id): ?MediaItem
    {
        $media = Media::query()->find($id);

        return $media ? $this->toItem($media) : null;
    }

    public function list(int $page = 1, int $perPage = 30): array
    {
        $paginator = Media::query()->latest('id')->paginate($perPage, ['*'], 'page', max(1, $page));

        return [
            'data' => array_map(fn (Media $media) => $this->toItem($media), $paginator->items()),
            'total' => $paginator->total(),
        ];
    }

    private function toItem(Media $media): MediaItem
    {
        return new MediaItem(
            id: $media->id,
            url: Storage::disk($media->disk)->url($media->path),
            name: $media->name,
            mime: $media->mime,
            width: $media->width,
            height: $media->height,
        );
    }
}
