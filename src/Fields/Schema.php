<?php

namespace Godrade\Trame\Fields;

use Godrade\Trame\Support\HtmlSanitizer;
use Godrade\Trame\Support\Localizer;

/**
 * Operations on a list of fields (a block schema or layout settings).
 */
final class Schema
{
    /**
     * @param  array<int, Field>  $fields
     * @return array<string, array<int, mixed>>
     */
    public static function rules(array $fields, Localizer $localizer): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $rules = [...$rules, ...$field->validationRules($localizer->locales(), $localizer->fallback())];
        }

        return $rules;
    }

    /**
     * Fill defaults, coerce values and drop unknown keys.
     *
     * @param  array<int, Field>  $fields
     * @return array<string, mixed>
     */
    public static function normalize(array $fields, mixed $data, Localizer $localizer, HtmlSanitizer $sanitizer): array
    {
        $data = is_array($data) ? $data : [];
        $out = [];

        foreach ($fields as $field) {
            $out[$field->name] = array_key_exists($field->name, $data)
                ? $field->normalize($data[$field->name], $localizer->locales(), $sanitizer)
                : $field->defaultFor($localizer->fallback());
        }

        return $out;
    }

    /**
     * Resolve translatable values for a locale.
     * Returns null when the block must be hidden ("hide" mode, missing required translation).
     *
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public static function resolve(array $fields, array $data, string $locale, Localizer $localizer): ?array
    {
        $out = [];

        foreach ($fields as $field) {
            $value = $data[$field->name] ?? null;

            if (! $field->isTranslatable()) {
                $out[$field->name] = $value;

                continue;
            }

            $resolved = $localizer->resolve($value, $locale);

            if ($resolved === null && $field->isRequired() && $localizer->mode() === 'hide') {
                return null;
            }

            $out[$field->name] = $resolved;
        }

        return $out;
    }
}
