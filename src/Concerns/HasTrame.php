<?php

namespace Godrade\Trame\Concerns;

use Godrade\Trame\Models\TrameContent;
use Godrade\Trame\Trame;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\HtmlString;

/**
 * Attach one or more page-builder documents to any Eloquent model.
 *
 *   $post->trame()                 // default document
 *   $product->trame('description') // several documents per model
 */
trait HasTrame
{
    public static function bootHasTrame(): void
    {
        static::deleting(function ($model) {
            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                $model->trameContents()->delete();
            }
        });
    }

    public function trameContents(): MorphMany
    {
        return $this->morphMany(TrameContent::class, 'contentable');
    }

    public function trame(string $key = 'default'): TrameContent
    {
        return $this->trameContents()->firstOrCreate(['key' => $key], ['document' => ['sections' => []]]);
    }

    /** URL of the visual editor for this document. $return: relative path for the "back" button. */
    public function trameEditorUrl(string $key = 'default', ?string $return = null): string
    {
        return route('trame.editor', array_filter(['content' => $this->trame($key), 'return' => $return]));
    }

    public function renderTrame(string $key = 'default', ?string $locale = null, bool $draft = false): HtmlString
    {
        return app(Trame::class)->renderContent($this->trame($key), $locale, $draft);
    }
}
