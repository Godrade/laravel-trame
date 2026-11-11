<?php

namespace Godrade\Trame\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Owner of the Trame media library when SpatieMediaProvider is used.
 * Requires spatie/laravel-medialibrary.
 *
 * To register conversions, extend this model, override
 * registerMediaConversions() and set config('trame.media.spatie.model').
 *
 * @property int $id
 * @property string $name
 */
class MediaLibrary extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'trame_media_libraries';

    protected $fillable = ['name'];

    public static function named(string $name = 'default'): static
    {
        return static::query()->firstOrCreate(['name' => $name]);
    }
}
