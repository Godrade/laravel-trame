<?php

namespace Godrade\Trame\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void blocks(array $classes)
 * @method static \Godrade\Trame\Blocks\BlockRegistry registry()
 * @method static \Illuminate\Support\HtmlString render(array $document, ?string $locale = null, bool $editing = false)
 * @method static \Illuminate\Support\HtmlString renderContent(\Godrade\Trame\Models\TrameContent $content, ?string $locale = null, bool $draft = false)
 * @method static array definitions()
 * @method static void pageRoutes(string $prefix = '')
 * @method static void bumpCacheVersion()
 *
 * @see \Godrade\Trame\Trame
 */
class Trame extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Godrade\Trame\Trame::class;
    }
}
