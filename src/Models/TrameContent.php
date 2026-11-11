<?php

namespace Godrade\Trame\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $key
 * @property array<string, mixed> $document Draft document (edited).
 * @property array<string, mixed>|null $published Published document (rendered publicly).
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int $revision Incremented on every draft save (optimistic locking).
 */
class TrameContent extends Model
{
    protected $table = 'trame_contents';

    protected $fillable = ['key', 'document'];

    protected $attributes = [
        'key' => 'default',
        'document' => '{"sections":[]}',
        'revision' => 0,
    ];

    protected function casts(): array
    {
        return [
            'document' => 'array',
            'published' => 'array',
            'published_at' => 'datetime',
            'revision' => 'integer',
        ];
    }

    public function contentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPublished(): bool
    {
        return $this->published !== null;
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->published !== $this->document;
    }

    public function publish(): static
    {
        $this->published = $this->document;
        $this->published_at = now();
        $this->save();

        return $this;
    }

    public function unpublish(): static
    {
        $this->published = null;
        $this->published_at = null;
        $this->save();

        return $this;
    }
}
