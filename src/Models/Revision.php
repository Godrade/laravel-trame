<?php

namespace Godrade\Trame\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $trame_content_id
 * @property int $revision Content revision this snapshot was taken at.
 * @property string $kind save|publish|restore
 * @property array<string, mixed> $document
 * @property int|null $user_id
 */
class Revision extends Model
{
    public const SAVE = 'save';

    public const PUBLISH = 'publish';

    public const RESTORE = 'restore';

    protected $table = 'trame_revisions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document' => 'array',
            'revision' => 'integer',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(TrameContent::class, 'trame_content_id');
    }
}
