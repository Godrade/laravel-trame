<?php

namespace Godrade\Trame\Links;

use Godrade\Trame\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;

/** Resolves "page:{id}" to the packaged Page model (published and draft pages). */
class PageLinkResolver implements LinkResolver
{
    public const LIMIT = 50;

    public function resolve(string $ref, string $locale): ?string
    {
        if (! preg_match('/^page:(\d+)$/D', $ref, $match)) {
            return null;
        }

        $page = Page::query()->find((int) $match[1]);

        return $page ? $this->url($page, $locale) : null;
    }

    public function options(string $locale, ?string $search): array
    {
        $query = Page::query()->orderBy('id');
        $search = trim((string) $search);

        if ($search !== '' && $this->validLocale($locale)) {
            $like = '%'.addcslashes($search, '%_\\').'%';

            $query->where(fn (Builder $q) => $q
                ->where("title->{$locale}", 'like', $like)
                ->orWhere("slug->{$locale}", 'like', $like));
        }

        $options = [];

        foreach ($query->limit(self::LIMIT)->get() as $page) {
            $options[] = [
                'ref' => 'page:'.$page->getKey(),
                'label' => $page->getTranslation('title', $locale) ?: '#'.$page->getKey(),
                'url' => $this->url($page, $locale) ?? '#',
            ];
        }

        return $options;
    }

    protected function url(Page $page, string $locale): ?string
    {
        $custom = config('trame.links.page_url');

        if (is_callable($custom)) {
            $url = $custom($page, $locale);

            return is_string($url) && $url !== '' ? $url : null;
        }

        $slug = $page->getTranslation('slug', $locale);

        if ($slug === null || $slug === '') {
            return null;
        }

        return Route::has('trame.page') ? route('trame.page', ['slug' => $slug], false) : '/'.ltrim($slug, '/');
    }

    private function validLocale(string $locale): bool
    {
        return (bool) preg_match('/^[a-z]{2,3}(?:[-_][A-Za-z]{2,4})?$/', $locale);
    }
}
