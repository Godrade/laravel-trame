<?php

namespace Godrade\Trame\Patterns;

use Godrade\Trame\Document\DocumentNormalizer;
use Godrade\Trame\Support\Localizer;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/** The patterns offered to the editor: shipped defaults plus the host's own. */
final class Patterns
{
    public function __construct(
        private readonly Container $app,
        private readonly Localizer $localizer,
        private readonly DocumentNormalizer $normalizer,
    ) {}

    /** @return array<int, Pattern> */
    public function all(): array
    {
        $patterns = [];

        if (config('trame.patterns.defaults', true)) {
            foreach (DefaultPattern::KEYS as $key) {
                $patterns[] = new DefaultPattern($key);
            }
        }

        foreach ((array) config('trame.patterns.extra', []) as $class) {
            $pattern = is_string($class) ? $this->app->make($class) : $class;

            if (! $pattern instanceof Pattern) {
                throw new InvalidArgumentException('Trame patterns must implement '.Pattern::class.'.');
            }

            $patterns[] = $pattern;
        }

        return $patterns;
    }

    /** @return array<int, array{key: string, label: string, description: string, content: array<string, mixed>}> */
    public function definitions(): array
    {
        $definitions = [];

        foreach ($this->all() as $pattern) {
            $section = $this->normalizer->normalizeNode('section', $pattern->content($this->localizer->locales(), $this->localizer->fallback()));

            if ($section === null) {
                continue;
            }

            $definitions[$pattern->key()] = [
                'key' => $pattern->key(),
                'label' => $pattern->label(),
                'description' => $pattern->description(),
                'content' => $section,
            ];
        }

        return array_values($definitions);
    }
}
