<?php

namespace Godrade\Trame\Document;

use Godrade\Trame\Blocks\BlockRegistry;
use Godrade\Trame\Fields\Schema;
use Godrade\Trame\Layout\ColumnLayout;
use Godrade\Trame\Layout\RowLayout;
use Godrade\Trame\Layout\SectionLayout;
use Godrade\Trame\Models\Template;
use Godrade\Trame\Support\Localizer;
use Illuminate\Contracts\View\Factory as ViewFactory;

final class Renderer
{
    public const MAX_TEMPLATE_DEPTH = 5;

    private string $locale = 'en';

    private bool $editing = false;

    /** @var array<int, int> */
    private array $templateStack = [];

    /** @var array<string, Template|null> Per-render cache, keyed "kind:id". */
    private array $templates = [];

    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly Localizer $localizer,
        private readonly DocumentNormalizer $normalizer,
        private readonly ViewFactory $view,
    ) {}

    /**
     * Render a document. $editing adds data-trame-* attributes and placeholders
     * used by the editor preview; never enable it for public output.
     */
    public function render(mixed $document, string $locale, bool $editing = false): string
    {
        $previous = [$this->locale, $this->editing, $this->templateStack];
        [$this->locale, $this->editing, $this->templateStack] = [$locale, $editing, []];

        try {
            $document = $this->normalizer->normalize($document);

            return $this->view->make('trame::document', [
                'content' => implode('', array_map(fn (array $section) => $this->section($section), $document['sections'])),
                'editing' => $editing,
                'locale' => $locale,
            ])->render();
        } finally {
            [$this->locale, $this->editing, $this->templateStack] = $previous;
        }
    }

    private function section(array $node): string
    {
        if ($node['type'] === 'global') {
            return $this->global($node, 'section', fn (array $inner) => $this->section($inner));
        }

        $classes = SectionLayout::classes($node['settings']);

        return $this->view->make('trame::layout.section', [
            ...$classes,
            'anchor' => $node['settings']['anchor'] ?: null,
            'attributes' => $this->attributes($node['id'], 'section'),
            'rows' => implode('', array_map(fn (array $row) => $this->row($row), $node['rows'])),
        ])->render();
    }

    private function row(array $node): string
    {
        if ($node['type'] === 'global') {
            return $this->global($node, 'row', fn (array $inner) => $this->row($inner));
        }

        return $this->view->make('trame::layout.row', [
            'classes' => RowLayout::classes($node['settings']),
            'attributes' => $this->attributes($node['id'], 'row'),
            'columns' => implode('', array_map(fn (array $column) => $this->column($column), $node['columns'])),
        ])->render();
    }

    private function column(array $node): string
    {
        return $this->view->make('trame::layout.column', [
            'classes' => ColumnLayout::classes($node['settings'], $node['span']),
            'attributes' => $this->attributes($node['id'], 'column'),
            'blocks' => implode('', array_map(fn (array $block) => $this->block($block), $node['blocks'])),
        ])->render();
    }

    private function block(array $node): string
    {
        if ($node['type'] === 'global') {
            return $this->global($node, 'block', fn (array $inner) => $this->block($inner));
        }

        if (! $this->blocks->has($node['type'])) {
            return $this->editing ? '<!-- trame: unknown block "'.e($node['type']).'" -->' : '';
        }

        $block = $this->blocks->get($node['type']);
        $data = Schema::resolve($block->fields(), $node['data'] ?? [], $this->locale, $this->localizer);

        if ($data === null) {
            return '';
        }

        $html = $this->view->make($block->view(), [
            ...$data,
            ...$block->with($data),
            'editing' => $this->editing,
            'locale' => $this->locale,
            'editable' => $this->editable(...),
        ])->render();

        return $this->editing
            ? '<div '.$this->attributes($node['id'], 'block').' data-trame-type="'.e($node['type']).'">'.$html.'</div>'
            : $html;
    }

    private function global(array $node, string $kind, callable $render): string
    {
        $ref = (int) $node['ref'];

        if (in_array($ref, $this->templateStack, true) || count($this->templateStack) >= self::MAX_TEMPLATE_DEPTH) {
            return '';
        }

        $template = $this->templates["{$kind}:{$ref}"] ??= Template::query()
            ->whereKey($ref)
            ->where('kind', $kind)
            ->where('is_global', true)
            ->first();

        if ($template === null) {
            return '';
        }

        $this->templateStack[] = $ref;

        try {
            $inner = $this->normalizer->normalizeNode($kind, $template->content);
            $html = $inner === null ? '' : $render($inner);
        } finally {
            array_pop($this->templateStack);
        }

        return $this->editing
            ? '<div data-trame-id="'.e($node['id']).'" data-trame-node="global" data-trame-ref="'.$ref.'">'.$html.'</div>'
            : $html;
    }

    /**
     * Marks an element as editable in place in the editor canvas:
     *   <h2 {!! $editable('text') !!}>{{ $text }}</h2>
     *   <div {!! $editable('content', rich: true) !!}>{!! $html !!}</div>
     * Renders nothing outside the editor.
     */
    public function editable(string $field, bool $rich = false): string
    {
        if (! $this->editing || ! preg_match('/^[a-z][a-z0-9_]*$/', $field)) {
            return '';
        }

        return 'data-trame-field="'.$field.'"'.($rich ? ' data-trame-rich' : '');
    }

    private function attributes(string $id, string $node): string
    {
        return $this->editing ? 'data-trame-id="'.e($id).'" data-trame-node="'.$node.'"' : '';
    }
}
