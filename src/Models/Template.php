<?php

namespace Godrade\Trame\Models;

use Godrade\Trame\Trame;
use Illuminate\Database\Eloquent\Model;

/**
 * Reusable node saved by editors.
 *
 * kind: section | row | block
 * is_global = false: inserting copies the node into the page.
 * is_global = true : the page stores {"type":"global","ref":id}; edits propagate everywhere.
 *
 * @property int $id
 * @property string $name
 * @property string $kind
 * @property array<string, mixed> $content
 * @property bool $is_global
 */
class Template extends Model
{
    public const KINDS = ['section', 'row', 'block'];

    protected $table = 'trame_templates';

    protected $fillable = ['name', 'kind', 'content', 'is_global'];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_global' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Global templates are resolved at render time: invalidate every cached page.
        static::saved(fn () => app(Trame::class)->bumpCacheVersion());
        static::deleted(fn () => app(Trame::class)->bumpCacheVersion());
    }
}
