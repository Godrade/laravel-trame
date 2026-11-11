<?php

namespace Godrade\Trame\Models;

use Godrade\Trame\Concerns\HasTrame;
use Godrade\Trame\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Standalone page shipped with the package. Any model can use HasTrame instead.
 *
 * @property int $id
 * @property array<string, string> $title
 * @property array<string, string> $slug
 * @property array<string, mixed>|null $meta
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $published_at
 */
class Page extends Model
{
    use HasTrame;
    use HasTranslations;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $table = 'trame_pages';

    protected $fillable = ['title', 'slug', 'meta', 'status', 'published_at'];

    /** @var array<int, string> */
    protected array $translatable = ['title', 'slug'];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'slug' => 'array',
            'meta' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /** @param Builder<static> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public static function findPublishedBySlug(string $slug, string $locale): ?self
    {
        if (! preg_match('/^[a-z]{2,3}(?:[-_][A-Za-z]{2,4})?$/', $locale)) {
            return null;
        }

        return static::query()->published()->where("slug->{$locale}", $slug)->first();
    }

    public function metaFor(string $key, ?string $locale = null): ?string
    {
        return $this->translateValue($this->meta[$key] ?? null, $locale);
    }
}
