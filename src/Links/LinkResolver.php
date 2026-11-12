<?php

namespace Godrade\Trame\Links;

/**
 * Turns internal link references ("page:12") stored in url fields into URLs,
 * and lists what the editor link picker offers.
 *
 * Bind your own implementation with config('trame.links.resolver').
 */
interface LinkResolver
{
    /** URL for a reference, or null when it cannot be resolved (the renderer then uses "#"). */
    public function resolve(string $ref, string $locale): ?string;

    /**
     * Targets offered by the picker (50 at most).
     *
     * @return array<int, array{ref: string, label: string, url: string}>
     */
    public function options(string $locale, ?string $search): array;
}
