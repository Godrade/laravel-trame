<?php

namespace Godrade\Trame\Blocks\Core;

use Godrade\Trame\Blocks\Block;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Support\HtmlSanitizer;
use Godrade\Trame\Support\Tokens;

/**
 * A note set apart from the text. The tone only changes the side rule,
 * so the callout stays readable on any section background.
 */
final class CalloutBlock extends Block
{
    public static string $type = 'callout';

    public static string $icon = 'note';

    public static string $category = 'content';

    /** Tone => [side rule, icon colour] (literal classes). The icon tells the kind of note. */
    public const TONES = [
        'info' => ['border-current/40', 'text-current/60'],
        'success' => ['border-emerald-600', 'text-emerald-600'],
        'warning' => ['border-amber-500', 'text-amber-600'],
    ];

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public static function label(): string
    {
        return __('trame::blocks.callout');
    }

    public function schema(): array
    {
        return [
            Field::text('title')->translatable()->max(120),
            Field::richText('content')->translatable()->required(),
            Field::select('tone')->options(array_keys(self::TONES))->default('info'),
        ];
    }

    public function with(array $data): array
    {
        return [
            'html' => $this->sanitizer->sanitize((string) $data['content']),
            'tone' => isset(self::TONES[$data['tone']]) ? $data['tone'] : 'info',
            'classes' => Tokens::join('flex gap-3 border-s-4 bg-current/5 px-5 py-4', (self::TONES[$data['tone']] ?? self::TONES['info'])[0]),
            'icon' => Tokens::join('mt-1 size-5 shrink-0', (self::TONES[$data['tone']] ?? self::TONES['info'])[1]),
            'prose' => Tokens::join('leading-7', Tokens::PROSE),
        ];
    }

    public function view(): string
    {
        return 'trame::blocks.callout';
    }
}
