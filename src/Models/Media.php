<?php

namespace Godrade\Trame\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Storage used by LocalDiskProvider only.
 *
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string $name
 * @property string $mime
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property array<int, array{path: string, width: int}>|null $variants Resized copies, same disk.
 */
class Media extends Model
{
    protected $table = 'trame_media';

    protected $fillable = ['disk', 'path', 'name', 'mime', 'size', 'width', 'height', 'variants'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'variants' => 'array',
        ];
    }
}
