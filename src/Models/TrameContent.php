<?php

namespace Godrade\Trame\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** @return HasMany<Revision, $this> Latest first. */
    public function revisions(): HasMany
    {
        return $this->hasMany(Revision::class, 'trame_content_id')->latest('id');
    }

    /**
     * Snapshot the current draft. Consecutive saves by the same user within
     * trame.revisions.coalesce_minutes are merged into one entry (autosave).
     */
    public function recordRevision(string $kind, int|string|null $userId = null): Revision
    {
        $userId = is_numeric($userId) ? (int) $userId : null;
        $latest = $this->revisions()->first();
        $window = (int) config('trame.revisions.coalesce_minutes', 10);

        if ($kind === Revision::SAVE
            && $latest?->kind === Revision::SAVE
            && $latest->user_id === $userId
            && $latest->created_at->gt(now()->subMinutes($window))) {
            $latest->forceFill(['document' => $this->document, 'revision' => $this->revision])->save();
            $revision = $latest;
        } else {
            $revision = $this->revisions()->create([
                'revision' => $this->revision,
                'kind' => $kind,
                'document' => $this->document,
                'user_id' => $userId,
            ]);
        }

        $keep = max(1, (int) config('trame.revisions.keep', 50));
        $keepIds = $this->revisions()->limit($keep)->pluck('id');
        $this->revisions()->whereNotIn('id', $keepIds)->delete();

        return $revision;
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
