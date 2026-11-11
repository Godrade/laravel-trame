<?php

namespace Godrade\Trame\Fields;

use Godrade\Trame\Support\Grid;
use Godrade\Trame\Support\HtmlSanitizer;
use Godrade\Trame\Support\Palette;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class Field
{
    public const TYPES = ['text', 'textarea', 'rich_text', 'url', 'number', 'toggle', 'select', 'color', 'image'];

    private ?string $label = null;

    private mixed $default = null;

    private bool $required = false;

    private bool $translatable = false;

    private bool $responsive = false;

    /** @var array<int|string, string> value => label */
    private array $options = [];

    private bool $allowCustomColor = false;

    private int|float|null $min = null;

    private int|float|null $max = null;

    private ?string $help = null;

    /** @var array<int, mixed> */
    private array $extraRules = [];

    private function __construct(
        public readonly string $name,
        public readonly string $type,
    ) {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Invalid field name [{$name}]: use snake_case.");
        }
    }

    public static function text(string $name): self
    {
        return new self($name, 'text');
    }

    public static function textarea(string $name): self
    {
        return new self($name, 'textarea');
    }

    public static function richText(string $name): self
    {
        return new self($name, 'rich_text');
    }

    public static function url(string $name): self
    {
        return new self($name, 'url');
    }

    public static function number(string $name): self
    {
        return new self($name, 'number');
    }

    public static function toggle(string $name): self
    {
        return (new self($name, 'toggle'))->default(false);
    }

    public static function select(string $name): self
    {
        return new self($name, 'select');
    }

    public static function color(string $name): self
    {
        return new self($name, 'color');
    }

    public static function image(string $name): self
    {
        return new self($name, 'image');
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;

        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function translatable(bool $translatable = true): self
    {
        if ($translatable && ! in_array($this->type, ['text', 'textarea', 'rich_text', 'url'], true)) {
            throw new InvalidArgumentException("Field type [{$this->type}] cannot be translatable.");
        }

        $this->translatable = $translatable;

        return $this;
    }

    /**
     * One value per breakpoint, edited per device in the editor:
     * {"base": "center", "md": "start"}. md and lg are optional and inherit
     * from the breakpoint below. A plain scalar is read as the base value.
     * Select fields only; render with Tokens::responsive().
     */
    public function responsive(bool $responsive = true): self
    {
        if ($responsive && $this->type !== 'select') {
            throw new InvalidArgumentException("Field type [{$this->type}] cannot be responsive.");
        }

        $this->responsive = $responsive;

        return $this;
    }

    /** @param array<int|string, string> $options list of values, or value => label */
    public function options(array $options): self
    {
        $this->options = array_is_list($options)
            ? array_combine(array_map(strval(...), $options), array_map(strval(...), $options))
            : $options;

        return $this;
    }

    /** Accept a free hex colour (#rrggbb) in addition to the palette. */
    public function allowCustom(bool $allow = true): self
    {
        $this->allowCustomColor = $allow;

        return $this;
    }

    public function min(int|float $min): self
    {
        $this->min = $min;

        return $this;
    }

    public function max(int|float $max): self
    {
        $this->max = $max;

        return $this;
    }

    public function help(string $help): self
    {
        $this->help = $help;

        return $this;
    }

    /** @param array<int, mixed> $rules Additional Laravel validation rules. */
    public function rules(array $rules): self
    {
        $this->extraRules = [...$this->extraRules, ...$rules];

        return $this;
    }

    public function isTranslatable(): bool
    {
        return $this->translatable;
    }

    public function isResponsive(): bool
    {
        return $this->responsive;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function defaultFor(string $fallback): mixed
    {
        if ($this->translatable) {
            return $this->default === null ? [] : [$fallback => $this->default];
        }

        if ($this->responsive) {
            return $this->default === null ? null : ['base' => $this->default];
        }

        return $this->default;
    }

    /**
     * Validation rules, keyed relative to the block data.
     *
     * @param  array<int, string>  $locales
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(array $locales, string $fallback): array
    {
        $base = $this->baseRules();

        if ($this->responsive) {
            return [$this->name => [$this->required ? 'required' : 'nullable', $this->responsiveRule()]];
        }

        if (! $this->translatable) {
            return [$this->name => [$this->required ? 'required' : 'nullable', ...$base]];
        }

        $rules = [
            $this->name => [
                $this->required ? 'required' : 'nullable',
                'array',
                function (string $attribute, mixed $value, \Closure $fail) use ($locales): void {
                    if (is_array($value) && array_diff(array_map(strval(...), array_keys($value)), $locales) !== []) {
                        $fail(__('trame::validation.unknown_locale'));
                    }
                },
            ],
            $this->name.'.*' => ['nullable', ...$base],
        ];

        if ($this->required) {
            $rules[$this->name.'.'.$fallback] = ['required', ...$base];
        }

        return $rules;
    }

    /**
     * Coerce and sanitize a stored value. Unknown locales are dropped.
     *
     * @param  array<int, string>  $locales
     */
    public function normalize(mixed $value, array $locales, HtmlSanitizer $sanitizer): mixed
    {
        if ($this->translatable) {
            $out = [];

            foreach ($locales as $locale) {
                if (is_array($value) && isset($value[$locale]) && $value[$locale] !== '') {
                    $cast = $this->cast($value[$locale], $sanitizer);

                    if ($cast !== null && $cast !== '') {
                        $out[$locale] = $cast;
                    }
                }
            }

            return $out;
        }

        if ($this->responsive) {
            return $this->normalizeResponsive($value);
        }

        return $value === null ? null : $this->cast($value, $sanitizer);
    }

    /** @return array<string, int|string>|null */
    private function normalizeResponsive(mixed $value): ?array
    {
        $value = is_array($value) ? $value : ['base' => $value];
        $base = $this->castOption($value['base'] ?? null);

        if ($base === null) {
            return null;
        }

        $out = ['base' => $base];

        foreach (array_keys(Grid::BREAKPOINTS) as $breakpoint) {
            if ($breakpoint !== 'base' && isset($value[$breakpoint]) && $this->hasOption($value[$breakpoint])) {
                $out[$breakpoint] = $this->castOption($value[$breakpoint]);
            }
        }

        return $out;
    }

    private function hasOption(mixed $value): bool
    {
        return is_scalar($value) && in_array((string) $value, array_map(strval(...), array_keys($this->options)), true);
    }

    private function responsiveRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $values = is_array($value) ? $value : ['base' => $value];

            if (array_diff(array_map(strval(...), array_keys($values)), array_keys(Grid::BREAKPOINTS)) !== []) {
                $fail(__('trame::validation.breakpoint'));

                return;
            }

            if ($this->required && ($values['base'] ?? null) === null) {
                $fail(__('trame::validation.option'));

                return;
            }

            foreach ($values as $item) {
                if ($item !== null && ! $this->hasOption($item)) {
                    $fail(__('trame::validation.option'));

                    return;
                }
            }
        };
    }

    /** @return array<string, mixed> Definition consumed by the editor. */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type,
            'label' => $this->label ?? $this->defaultLabel(),
            'default' => $this->default,
            'required' => $this->required,
            'translatable' => $this->translatable,
            'responsive' => $this->responsive ?: null,
            'options' => $this->type === 'select'
                ? array_map(fn ($value, $label) => ['value' => $value, 'label' => $label], array_keys($this->options), $this->options)
                : null,
            'palette' => $this->type === 'color' ? Palette::colors() : null,
            'allow_custom' => $this->type === 'color' ? $this->allowCustomColor : null,
            'min' => $this->min,
            'max' => $this->max,
            'help' => $this->help,
        ], fn ($value) => $value !== null);
    }

    /** @return array<int, mixed> */
    private function baseRules(): array
    {
        $rules = match ($this->type) {
            'text' => ['string', 'max:'.($this->max ?? 255)],
            'textarea' => ['string', 'max:'.($this->max ?? 5000)],
            'rich_text' => ['string', 'max:'.($this->max ?? 50000)],
            'url' => ['string', 'max:2048', 'regex:/^(https?:\/\/|\/|#|mailto:|tel:)/i'],
            'number' => array_values(array_filter([
                'numeric',
                $this->min !== null ? 'min:'.$this->min : null,
                $this->max !== null ? 'max:'.$this->max : null,
            ])),
            'toggle' => ['boolean'],
            'select' => [Rule::in(array_map(strval(...), array_keys($this->options)))],
            'color' => [$this->colorRule()],
            'image' => [function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_int($value) && ! (is_string($value) && $value !== '')) {
                    $fail(__('trame::validation.image'));
                }
            }],
        };

        return [...$rules, ...$this->extraRules];
    }

    private function colorRule(): \Closure
    {
        $allowCustom = $this->allowCustomColor;

        return function (string $attribute, mixed $value, \Closure $fail) use ($allowCustom): void {
            if (is_string($value) && in_array($value, Palette::colors(), true)) {
                return;
            }

            if ($allowCustom && is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                return;
            }

            $fail(__('trame::validation.color'));
        };
    }

    private function cast(mixed $value, HtmlSanitizer $sanitizer): mixed
    {
        return match ($this->type) {
            'text', 'textarea', 'url' => is_scalar($value) ? trim((string) $value) : null,
            'rich_text' => is_string($value) ? $sanitizer->sanitize($value) : null,
            'number' => is_numeric($value) ? $value + 0 : $this->default,
            'toggle' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $this->default,
            'select' => $this->castOption($value),
            'color' => is_string($value) ? $value : $this->default,
            'image' => is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : (is_string($value) && $value !== '' ? $value : null),
        };
    }

    private function castOption(mixed $value): mixed
    {
        foreach (array_keys($this->options) as $key) {
            if ((string) $key === (string) $value) {
                return $key;
            }
        }

        return $this->default;
    }

    private function defaultLabel(): string
    {
        $key = "trame::fields.{$this->name}";

        return Lang::has($key) ? __($key) : Str::headline($this->name);
    }
}
