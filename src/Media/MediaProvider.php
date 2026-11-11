<?php

namespace Godrade\Trame\Media;

use Illuminate\Http\UploadedFile;

/**
 * Bridge between Trame and the host's media storage.
 * Bind your own implementation via config('trame.media.provider').
 */
interface MediaProvider
{
    public function store(UploadedFile $file): MediaItem;

    public function find(int|string $id): ?MediaItem;

    /** @return array{data: array<int, MediaItem>, total: int} */
    public function list(int $page = 1, int $perPage = 30): array;
}
