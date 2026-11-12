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

        $path = $file->store((string) $this->config->get('trame.media.directory', 'trame'), $disk);

        $media = Media::query()->create([
            'disk' => $disk,
            'path' => $path,
            'name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'width' => $size[0],
            'height' => $size[1],
            'variants' => $this->variants($disk, $path, (string) $file->getMimeType(), $size[0] ?? null, $size[1] ?? null),
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

    /**
     * Resized copies (jpeg, png, webp) narrower than the original, stored next to it.
     * Never throws: without GD or on a broken image the upload simply has no variants.
     *
     * @return array<int, array{path: string, width: int}>|null
     */
    private function variants(string $disk, string $path, string $mime, ?int $width, ?int $height = null): ?array
    {
        // GD decodes the whole image in memory (~4 bytes per pixel): an out-of-memory error is
        // fatal and cannot be caught, so very large photos are stored without resized copies.
        if ($width && $height && $width * $height > (int) $this->config->get('trame.media.max_pixels', 24_000_000)) {
            return null;
        }

        $create = match ($mime) {
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
            default => null,
        };
        $widths = array_filter(array_map('intval', (array) $this->config->get('trame.media.widths', [480, 960, 1600])), fn (int $w) => $w > 0 && $w < (int) $width);

        if ($create === null || ! $width || $widths === [] || ! function_exists($create) || ! function_exists('imagescale') || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $storage = Storage::disk($disk);
            $source = @imagecreatefromstring((string) $storage->get($path));

            if ($source === false) {
                return null;
            }

            $variants = [];
            $info = pathinfo($path);

            foreach (array_unique($widths) as $target) {
                $copy = @imagescale($source, $target, -1, IMG_BICUBIC);

                if ($copy === false) {
                    continue;
                }

                if ($mime === 'image/png' || $mime === 'image/webp') {
                    imagealphablending($copy, false);
                    imagesavealpha($copy, true);
                }

                ob_start();
                $ok = match ($mime) {
                    'image/jpeg' => imagejpeg($copy, null, 82),
                    'image/png' => imagepng($copy),
                    default => imagewebp($copy, null, 82),
                };
                $data = (string) ob_get_clean();

                if ($ok && $data !== '') {
                    $variant = ($info['dirname'] !== '.' ? $info['dirname'].'/' : '').$info['filename'].'-'.$target.'w.'.($info['extension'] ?? 'img');
                    $storage->put($variant, $data);
                    $variants[] = ['path' => $variant, 'width' => $target];
                }
            }

            return $variants === [] ? null : $variants;
        } catch (\Throwable) {
            return null;
        }
    }

    private function toItem(Media $media): MediaItem
    {
        $storage = Storage::disk($media->disk);
        $url = $storage->url($media->path);
        $srcset = null;

        if (! empty($media->variants) && $media->width) {
            $candidates = array_map(fn (array $v) => $storage->url($v['path']).' '.$v['width'].'w', $media->variants);
            $candidates[] = $url.' '.$media->width.'w';
            $srcset = implode(', ', $candidates);
        }

        return new MediaItem(
            id: $media->id,
            url: $url,
            name: $media->name,
            mime: $media->mime,
            width: $media->width,
            height: $media->height,
            srcset: $srcset,
        );
    }
}
