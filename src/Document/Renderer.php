<?php

namespace Godrade\Trame\Document;

use Godrade\Trame\Blocks\BlockRegistry;
use Godrade\Trame\Fields\Schema;
use Godrade\Trame\Layout\BlockLayout;
use Godrade\Trame\Layout\Box;
use Godrade\Trame\Layout\ColumnLayout;
use Godrade\Trame\Layout\RowLayout;
use Godrade\Trame\Layout\SectionLayout;
use Godrade\Trame\Models\Template;
use Godrade\Trame\Support\Localizer;
use Godrade\Trame\Support\Tokens;
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
        $classes['section'] = Tokens::join($classes['section'], $this->hideClasses($node));

        return $this->view->make('trame::layout.section', [
            ...$classes,
            'anchor' => $node['settings']['anchor'] ?: null,
            'attributes' => $this->attributes($node['id'], 'section', $node),
            'rows' => implode('', array_map(fn (array $row) => $this->row($row), $node['rows'])),
        ])->render();
    }

    private function row(array $node): string
    {
        if ($node['type'] === 'global') {
            return $this->global($node, 'row', fn (array $inner) => $this->row($inner));
        }

        return $this->view->make('trame::layout.row', [
            'classes' => Tokens::join(RowLayout::classes($node['settings']), $this->hideClasses($node)),
            'attributes' => $this->attributes($node['id'], 'row', $node),
            'columns' => implode('', array_map(fn (array $column) => $this->column($column), $node['columns'])),
        ])->render();
    }

    private function column(array $node): string
    {
        return $this->view->make('trame::layout.column', [
            'classes' => Tokens::join(ColumnLayout::classes($node['settings'], $node['span']), $this->hideClasses($node)),
            'style' => Box::classes($node['settings'])['style'],
            'attributes' => $this->attributes($node['id'], 'column', $node),
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

        $settings = $node['settings'] ?? [];
        $box = Box::classes($settings);
        // A block declaring $box applies the appearance to its own root element (e.g. a card,
        // so its image follows the rounded corners); other blocks get it on the wrapper.
        $ownsBox = $block::$box;

        $html = $this->view->make($block->view(), [
            ...$data,
            ...$block->with($data),
            'box' => $box,
            'editing' => $this->editing,
            'locale' => $this->locale,
            'editable' => $this->editable(...),
        ])->render();

        // Wrapper only when needed: margins, appearance, and hide classes in public output.
        $classes = Tokens::join(
            // A block owning its box fills the column height (cards of a row end level), wrapper or not.
            $ownsBox && ($this->editing || BlockLayout::classes($settings) !== '' || $this->hideClasses($node) !== '') ? 'flex flex-1 flex-col' : '',
            BlockLayout::classes($settings),
            $ownsBox ? '' : $box['class'],
            $this->hideClasses($node),
        );
        $style = $ownsBox ? '' : $box['style'];
        $attributes = ($classes === '' ? '' : ' class="'.$classes.'"').($style === '' ? '' : ' style="'.e($style).'"');

        if ($this->editing) {
            return '<div'.$attributes.' '.$this->attributes($node['id'], 'block', $node).' data-trame-type="'.e($node['type']).'">'.$html.'</div>';
        }

        return $attributes === '' ? $html : '<div'.$attributes.'>'.$html.'</div>';
    }

    /**
     * Public output hides the node with responsive classes. The editor keeps it
     * visible (dimmed via data-trame-hide) so it can still be selected.
     */
    private function hideClasses(array $node): string
    {
        return $this->editing ? '' : Tokens::hide($node['settings']['hide'] ?? null);
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

    private function attributes(string $id, string $kind, array $node = []): string
    {
        if (! $this->editing) {
            return '';
        }

        $hide = Tokens::hiddenOn($node['settings']['hide'] ?? null);

        return 'data-trame-id="'.e($id).'" data-trame-node="'.$kind.'"'
            .($hide === [] ? '' : ' data-trame-hide="'.implode(' ', $hide).'"');
    }
}
