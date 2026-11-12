<?php

namespace Godrade\Trame;

use Godrade\Trame\Blocks\Block;
use Godrade\Trame\Blocks\BlockRegistry;
use Godrade\Trame\Document\Renderer;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Http\Controllers\PageController;
use Godrade\Trame\Layout\BlockLayout;
use Godrade\Trame\Layout\ColumnLayout;
use Godrade\Trame\Layout\RowLayout;
use Godrade\Trame\Layout\SectionLayout;
use Godrade\Trame\Models\TrameContent;
use Godrade\Trame\Patterns\Patterns;
use Godrade\Trame\Support\Grid;
use Godrade\Trame\Support\Localizer;
use Godrade\Trame\Support\Palette;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;

class Trame
{
    private const CACHE_VERSION_KEY = 'trame:cache-version';

    public function __construct(
        private readonly Container $app,
        private readonly BlockRegistry $registry,
        private readonly Localizer $localizer,
    ) {}

    /** @param array<int, class-string<Block>> $classes */
    public function blocks(array $classes): void
    {
        foreach ($classes as $class) {
            $this->registry->register($class);
        }
    }

    public function registry(): BlockRegistry
    {
        return $this->registry;
    }

    public function localizer(): Localizer
    {
        return $this->localizer;
    }

    public function render(mixed $document, ?string $locale = null, bool $editing = false): HtmlString
    {
        return new HtmlString(
            $this->app->make(Renderer::class)->render($document, $this->localizer->current($locale), $editing)
        );
    }

    /** Render the published document (or the draft), cached per revision and locale. */
    public function renderContent(TrameContent $content, ?string $locale = null, bool $draft = false): HtmlString
    {
        $locale = $this->localizer->current($locale);
        $document = $draft ? $content->document : $content->published;

        if (! is_array($document)) {
            return new HtmlString('');
        }

        if ($draft || ! config('trame.cache.enabled', true)) {
            return $this->render($document, $locale);
        }

        $key = implode(':', [
            'trame',
            $this->cacheVersion(),
            $content->getKey(),
            // Hash of the published document: two publications in the same second still differ.
            md5((string) json_encode($document)),
            $locale,
        ]);

        $render = fn () => (string) $this->render($document, $locale);
        $ttl = config('trame.cache.ttl');

        return new HtmlString($ttl
            ? $this->cache()->remember($key, (int) $ttl, $render)
            : $this->cache()->rememberForever($key, $render));
    }

    /** Definitions consumed by the editor (blocks, layout settings, palette, locales). */
    public function definitions(): array
    {
        $fields = fn (array $schema) => array_map(fn (Field $field) => $field->toArray(), $schema);

        return [
            'locales' => $this->localizer->locales(),
            'fallback_locale' => $this->localizer->fallback(),
            'missing_translation' => $this->localizer->mode(),
            'palette' => Palette::colors(),
            'grid' => [
                'columns' => Grid::COLUMNS,
                'breakpoints' => array_keys(Grid::BREAKPOINTS),
                'max_columns_per_row' => RowLayout::MAX_COLUMNS,
            ],
            'layout' => [
                'section' => $fields(SectionLayout::schema()),
                'row' => $fields(RowLayout::schema()),
                'column' => $fields(ColumnLayout::schema()),
                'block' => $fields(BlockLayout::schema()),
            ],
            'blocks' => array_values(array_map(fn (Block $block) => $block->definition(), $this->registry->all())),
            'patterns' => $this->app->make(Patterns::class)->definitions(),
        ];
    }

    /**
     * Register public page routes. Call it at the END of routes/web.php:
     * the {slug} route matches almost anything.
     */
    public function pageRoutes(string $prefix = ''): void
    {
        Route::middleware(config('trame.pages.middleware', ['web']))
            ->prefix($prefix)
            ->group(function () {
                Route::get('{slug}', PageController::class)
                    ->where('slug', '[A-Za-z0-9\-_/]+')
                    ->name('trame.page');
            });
    }

    public function cacheVersion(): int
    {
        return (int) $this->cache()->get(self::CACHE_VERSION_KEY, 1);
    }

    public function bumpCacheVersion(): void
    {
        $this->cache()->forever(self::CACHE_VERSION_KEY, $this->cacheVersion() + 1);
    }

    private function cache(): Cache
    {
        return CacheFacade::store(config('trame.cache.store'));
    }
}
