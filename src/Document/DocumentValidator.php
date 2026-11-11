<?php

namespace Godrade\Trame\Document;

use Godrade\Trame\Blocks\BlockRegistry;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Fields\Schema;
use Godrade\Trame\Layout\ColumnLayout;
use Godrade\Trame\Layout\RowLayout;
use Godrade\Trame\Layout\SectionLayout;
use Godrade\Trame\Models\Template;
use Godrade\Trame\Support\Grid;
use Godrade\Trame\Support\Localizer;
use Godrade\Trame\Support\Tokens;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates a document coming from the editor. Error keys are full paths,
 * e.g. "document.sections.0.rows.1.columns.0.blocks.2.data.text.fr".
 */
final class DocumentValidator
{
    public const MAX_SECTIONS = 200;

    /** @var array<string, array<int, string>> */
    private array $errors = [];

    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly Localizer $localizer,
    ) {}

    /** @throws ValidationException */
    public function validate(mixed $document, string $path = 'document'): void
    {
        $this->errors = [];

        if (! is_array($document) || ! is_array($document['sections'] ?? null) || ! array_is_list($document['sections'])) {
            throw ValidationException::withMessages(["{$path}.sections" => [__('trame::validation.structure')]]);
        }

        if (count($document['sections']) > self::MAX_SECTIONS) {
            throw ValidationException::withMessages(["{$path}.sections" => [__('trame::validation.too_many', ['max' => self::MAX_SECTIONS])]]);
        }

        foreach ($document['sections'] as $i => $section) {
            $this->section($section, "{$path}.sections.{$i}");
        }

        $this->throwIfErrors();
    }

    /** @throws ValidationException */
    public function validateNode(string $kind, mixed $node, string $path = 'content'): void
    {
        $this->errors = [];

        match ($kind) {
            'section' => $this->section($node, $path),
            'row' => $this->row($node, $path),
            'block' => $this->block($node, $path),
            default => $this->fail($path, 'structure'),
        };

        $this->throwIfErrors();
    }

    private function section(mixed $node, string $path): void
    {
        if (! is_array($node)) {
            $this->fail($path, 'structure');

            return;
        }

        $type = $node['type'] ?? 'section';

        if ($type === 'global') {
            $this->globalRef($node, 'section', $path);

            return;
        }

        if ($type !== 'section') {
            $this->fail("{$path}.type", 'structure');

            return;
        }

        $this->settings(SectionLayout::schema(), $node['settings'] ?? [], "{$path}.settings");
        $this->hide($node, $path);

        $rows = $node['rows'] ?? [];

        if (! is_array($rows) || ! array_is_list($rows)) {
            $this->fail("{$path}.rows", 'structure');

            return;
        }

        foreach ($rows as $j => $row) {
            $this->row($row, "{$path}.rows.{$j}");
        }
    }

    private function row(mixed $node, string $path): void
    {
        if (! is_array($node)) {
            $this->fail($path, 'structure');

            return;
        }

        $type = $node['type'] ?? 'row';

        if ($type === 'global') {
            $this->globalRef($node, 'row', $path);

            return;
        }

        if ($type !== 'row') {
            $this->fail("{$path}.type", 'structure');

            return;
        }

        $this->settings(RowLayout::schema(), $node['settings'] ?? [], "{$path}.settings");
        $this->hide($node, $path);

        $columns = $node['columns'] ?? null;

        if (! is_array($columns) || ! array_is_list($columns) || $columns === [] || count($columns) > RowLayout::MAX_COLUMNS) {
            $this->fail("{$path}.columns", 'columns', ['max' => RowLayout::MAX_COLUMNS]);

            return;
        }

        foreach ($columns as $k => $column) {
            $this->column($column, "{$path}.columns.{$k}");
        }
    }

    private function column(mixed $node, string $path): void
    {
        if (! is_array($node)) {
            $this->fail($path, 'structure');

            return;
        }

        $span = $node['span'] ?? [];

        if (! is_array($span) || array_diff(array_keys($span), array_keys(Grid::BREAKPOINTS)) !== []) {
            $this->fail("{$path}.span", 'span');
        } else {
            foreach ($span as $breakpoint => $value) {
                if (! is_int($value) || $value < 1 || $value > Grid::COLUMNS) {
                    $this->fail("{$path}.span.{$breakpoint}", 'span');
                }
            }
        }

        $this->settings(ColumnLayout::schema(), $node['settings'] ?? [], "{$path}.settings");
        $this->hide($node, $path);

        $blocks = $node['blocks'] ?? [];

        if (! is_array($blocks) || ! array_is_list($blocks)) {
            $this->fail("{$path}.blocks", 'structure');

            return;
        }

        foreach ($blocks as $b => $block) {
            $this->block($block, "{$path}.blocks.{$b}");
        }
    }

    private function block(mixed $node, string $path): void
    {
        if (! is_array($node) || ! is_string($node['type'] ?? null)) {
            $this->fail($path, 'structure');

            return;
        }

        if ($node['type'] === 'global') {
            $this->globalRef($node, 'block', $path);

            return;
        }

        if (! $this->blocks->has($node['type'])) {
            $this->fail("{$path}.type", 'unknown_block', ['type' => $node['type']]);

            return;
        }

        $data = $node['data'] ?? [];

        if (! is_array($data)) {
            $this->fail("{$path}.data", 'structure');

            return;
        }

        $block = $this->blocks->get($node['type']);
        $storedVersion = max(1, (int) ($node['v'] ?? $block::$version));

        if ($storedVersion < $block::$version) {
            $data = $block->migrate($data, $storedVersion);
        }

        $this->settings($block->fields(), $data, "{$path}.data");

        if (array_key_exists('settings', $node) && ! is_array($node['settings'])) {
            $this->fail("{$path}.settings", 'structure');

            return;
        }

        $this->hide($node, $path);
    }

    /** settings.hide: null or a list of breakpoints (base, md, lg). */
    private function hide(array $node, string $path): void
    {
        $hide = is_array($node['settings'] ?? null) ? ($node['settings']['hide'] ?? null) : null;

        if ($hide === null) {
            return;
        }

        if (
            ! is_array($hide)
            || ! array_is_list($hide)
            || array_filter($hide, fn (mixed $breakpoint) => ! is_string($breakpoint) || ! isset(Tokens::HIDE[$breakpoint])) !== []
        ) {
            $this->fail("{$path}.settings.hide", 'breakpoint');
        }
    }

    /** @param array<int, Field> $fields */
    private function settings(array $fields, mixed $data, string $path): void
    {
        if (! is_array($data)) {
            $this->fail($path, 'structure');

            return;
        }

        $validator = Validator::make($data, Schema::rules($fields, $this->localizer));

        foreach ($validator->errors()->messages() as $key => $messages) {
            $this->errors["{$path}.{$key}"] = $messages;
        }
    }

    private function globalRef(array $node, string $kind, string $path): void
    {
        $ref = $node['ref'] ?? null;

        if (! is_int($ref) || ! Template::query()->whereKey($ref)->where('kind', $kind)->where('is_global', true)->exists()) {
            $this->fail("{$path}.ref", 'global_ref', ['kind' => $kind]);
        }
    }

    /** @param array<string, mixed> $replace */
    private function fail(string $path, string $message, array $replace = []): void
    {
        $this->errors[$path][] = __("trame::validation.{$message}", $replace);
    }

    private function throwIfErrors(): void
    {
        if ($this->errors !== []) {
            $errors = $this->errors;
            $this->errors = [];

            throw ValidationException::withMessages($errors);
        }
    }
}
