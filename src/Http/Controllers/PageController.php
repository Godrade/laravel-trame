<?php

namespace Godrade\Trame\Http\Controllers;

use Godrade\Trame\Models\Page;
use Godrade\Trame\Support\Localizer;
use Illuminate\Contracts\View\View;

final class PageController
{
    public function __invoke(string $slug, Localizer $localizer): View
    {
        $locale = $localizer->current();
        $page = Page::findPublishedBySlug($slug, $locale) ?? abort(404);

        return view('trame::page', [
            'page' => $page,
            'title' => $page->getTranslation('title', $locale),
            'description' => $page->metaFor('description', $locale),
            'content' => $page->renderTrame(locale: $locale),
        ]);
    }
}
