<?php

namespace Godrade\Trame\Document;

use Godrade\Trame\Blocks\BlockRegistry;
use Godrade\Trame\Fields\Schema;
use Godrade\Trame\Layout\BlockLayout;
use Godrade\Trame\Layout\Box;
use Godrade\Trame\Layout\ColumnLayout;
use Godrade\Trame\Layout\RowLayout;
use Godrade\Trame\Layout\SectionLayout;
use Godrade\Trame\Support\Grid;
use Godrade\Trame\Support\HtmlSanitizer;
use Godrade\Trame\Support\Localizer;
use Godrade\Trame\Support\Tokens;
use Illuminate\Support\Str;

/**
 * Produces the canonical document shape: ids, defaults, current block
 * versions (migrate()), clamped spans, sanitized rich text.
 *
 * Unknown block types are preserved untouched so data is not lost when a
 * block class is temporarily missing; the renderer skips them.
 */
final class DocumentNormalizer
{
    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly Localizer $localizer,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /** @return array{sections: array<int, array<string, mixed>>} */
    public function normalize(mixed $document): array
    {
        $sections = is_array($document) && is_array($document['sections'] ?? null) ? $document['sections'] : [];

        return ['sections' => $this->list($sections, fn ($node) => $this->section($node))];
    }

    /** @return array<string, mixed>|null */
    public function normalizeNode(string $kind, mixed $node): ?array
    {
        return match ($kind) {
            'section' => $this->section($node),
            'row' => $this->row($node),
            'block' => $this->block($node),
            default => null,
        };
    }

    /** @return array<string, mixed>|null */
    private function section(mixed $node): ?array
    {
        if (! is_array($node)) {
            return null;
        }

        if (($node['type'] ?? null) === 'global') {
            return $this->global($node, 'sec');
        }

        return [
            'id' => $this->id($node, 'sec'),
            'type' => 'section',
            'settings' => $this->settings(SectionLayout::schema(), $node),
            'rows' => $this->list($node['rows'] ?? [], fn ($row) => $this->row($row)),
        ];
    }

    /** @return array<string, mixed>|null */
    private function row(mixed $node): ?array
    {
        if (! is_array($node)) {
            return null;
        }

        if (($node['type'] ?? null) === 'global') {
            return $this->global($node, 'row');
        }

        $columns = $this->list($node['columns'] ?? [], fn ($column) => $this->column($column));

        return [
            'id' => $this->id($node, 'row'),
            'type' => 'row',
            'settings' => $this->settings(RowLayout::schema(), $node),
            'columns' => array_slice($columns, 0, RowLayout::MAX_COLUMNS),
        ];
    }

    /** @return array<string, mixed>|null */
    private function column(mixed $node): ?array
    {
        if (! is_array($node)) {
            return null;
        }

        return [
            'id' => $this->id($node, 'col'),
            'span' => Grid::normalizeSpan($node['span'] ?? []),
            'settings' => $this->settings(ColumnLayout::schema(), $node),
            'blocks' => $this->list($node['blocks'] ?? [], fn ($block) => $this->block($block)),
        ];
    }

    /** @return array<string, mixed>|null */
    private function block(mixed $node): ?array
    {
        if (! is_array($node) || ! is_string($node['type'] ?? null)) {
            return null;
        }

        if ($node['type'] === 'global') {
            return $this->global($node, 'blk');
        }

        if (! $this->blocks->has($node['type'])) {
            return [...$node, 'id' => $this->id($node, 'blk')];
        }

        $block = $this->blocks->get($node['type']);
        $version = $block::$version;
        $storedVersion = max(1, (int) ($node['v'] ?? 1));
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        if ($storedVersion < $version) {
            $data = $block->migrate($data, $storedVersion);
        }

        // Block settings are optional overrides: unset values are not stored.
        $settings = array_filter($this->settings(BlockLayout::schema(), $node), fn ($value) => $value !== null);

        return [
            'id' => $this->id($node, 'blk'),
            'type' => $node['type'],
            'v' => $version,
            'data' => Schema::normalize($block->fields(), $data, $this->localizer, $this->sanitizer),
            ...($settings === [] ? [] : ['settings' => $settings]),
        ];
    }

    /**
     * Schema settings plus "hide" (breakpoints the node is hidden on),
     * stored only when not empty.
     *
     * @param  array<int, \Godrade\Trame\Fields\Field>  $schema
     * @return array<string, mixed>
     */
    private function settings(array $schema, array $node): array
    {
        $settings = is_array($node['settings'] ?? null) ? $node['settings'] : [];
        $hide = Tokens::hiddenOn($settings['hide'] ?? null);

        $normalized = Schema::normalize($schema, $settings, $this->localizer, $this->sanitizer);

        // Appearance values are optional overrides: unset ones are not stored.
        foreach (Box::NAMES as $name) {
            if (array_key_exists($name, $normalized) && ($normalized[$name] === null || $normalized[$name] === '')) {
                unset($normalized[$name]);
            }
        }

        return [...$normalized, ...($hide === [] ? [] : ['hide' => $hide])];
    }

    /** @return array<string, mixed>|null */
    private function global(array $node, string $prefix): ?array
    {
        if (! is_numeric($node['ref'] ?? null)) {
            return null;
        }

        return ['id' => $this->id($node, $prefix), 'type' => 'global', 'ref' => (int) $node['ref']];
    }

    /** @return array<int, array<string, mixed>> */
    private function list(mixed $items, callable $map): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map($map, array_values($items))));
    }

    private function id(array $node, string $prefix): string
    {
        $id = $node['id'] ?? null;

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id)
            ? $id
            : $prefix.'_'.Str::lower(Str::random(10));
    }
}
