<?php

namespace Godrade\Trame\Concerns;

use Godrade\Trame\Support\Localizer;

/**
 * Minimal JSON translations for model attributes listed in $translatable
 * (cast them to 'array'). No external dependency.
 */
trait HasTranslations
{
    public function getTranslation(string $attribute, ?string $locale = null, bool $useFallback = true): ?string
    {
        return $this->translateValue($this->getAttribute($attribute), $locale, $useFallback);
    }

    public function setTranslation(string $attribute, string $locale, ?string $value): static
    {
        $translations = (array) ($this->getAttribute($attribute) ?? []);

        if ($value === null || $value === '') {
            unset($translations[$locale]);
        } else {
            $translations[$locale] = $value;
        }

        $this->setAttribute($attribute, $translations);

        return $this;
    }

    /** @return array<string, string> */
    public function getTranslations(string $attribute): array
    {
        return (array) ($this->getAttribute($attribute) ?? []);
    }

    /** @return array<int, string> */
    public function getTranslatableAttributes(): array
    {
        return property_exists($this, 'translatable') ? $this->translatable : [];
    }

    protected function translateValue(mixed $value, ?string $locale = null, bool $useFallback = true): ?string
    {
        $localizer = app(Localizer::class);

        return $localizer->translate($value, $localizer->current($locale), $useFallback);
    }
}
