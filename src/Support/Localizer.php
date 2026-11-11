<?php

namespace Godrade\Trame\Support;

use Illuminate\Contracts\Config\Repository;

final class Localizer
{
    public function __construct(private readonly Repository $config) {}

    /** @return array<int, string> */
    public function locales(): array
    {
        $locales = array_values(array_unique(array_map(strval(...), (array) $this->config->get('trame.locales', ['en']))));

        return $locales === [] ? ['en'] : $locales;
    }

    public function fallback(): string
    {
        $fallback = (string) $this->config->get('trame.fallback_locale');

        return in_array($fallback, $this->locales(), true) ? $fallback : $this->locales()[0];
    }

    /** 'fallback' | 'hide' */
    public function mode(): string
    {
        return $this->config->get('trame.missing_translation') === 'hide' ? 'hide' : 'fallback';
    }

    public function current(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return in_array($locale, $this->locales(), true) ? $locale : $this->fallback();
    }

    /** Resolve a value according to the configured missing-translation mode. */
    public function resolve(mixed $value, string $locale): ?string
    {
        return $this->translate($value, $locale, $this->mode() === 'fallback');
    }

    public function translate(mixed $value, string $locale, bool $useFallback = true): ?string
    {
        if (! is_array($value)) {
            return is_scalar($value) && $value !== '' ? (string) $value : null;
        }

        if (isset($value[$locale]) && $value[$locale] !== '') {
            return (string) $value[$locale];
        }

        $fallback = $this->fallback();

        if ($useFallback && isset($value[$fallback]) && $value[$fallback] !== '') {
            return (string) $value[$fallback];
        }

        return null;
    }
}
