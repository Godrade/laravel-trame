<?php

namespace Godrade\Trame\Blocks;

use Godrade\Trame\Fields\Field;
use Illuminate\Support\Str;

abstract class Block
{
    /** Unique identifier stored in documents. Never change it once used in production. */
    public static string $type;

    /** Icon name for the editor palette. */
    public static string $icon = 'square';

    /** Editor category key: content | media | layout | (your own). */
    public static string $category = 'content';

    /**
     * Schema version. Increment it when the schema changes in a way that
     * requires converting stored data, and implement migrate().
     */
    public static int $version = 1;

    /** @var array<int, Field>|null */
    private ?array $resolvedFields = null;

    /** @return array<int, Field> */
    abstract public function schema(): array;

    /** Blade view name. Receives resolved field values + with() + $editing + $locale. */
    abstract public function view(): string;

    public static function label(): string
    {
        return Str::headline(static::$type);
    }

    /**
     * Convert data stored with an older $version to the current schema.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function migrate(array $data, int $fromVersion): array
    {
        return $data;
    }

    /**
     * Extra view variables computed from the resolved (single-locale) data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function with(array $data): array
    {
        return [];
    }

    /** @return array<int, Field> */
    final public function fields(): array
    {
        return $this->resolvedFields ??= array_values($this->schema());
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'type' => static::$type,
            'label' => static::label(),
            'icon' => static::$icon,
            'category' => static::$category,
            'version' => static::$version,
            'fields' => array_map(fn (Field $field) => $field->toArray(), $this->fields()),
        ];
    }
}
