<?php

namespace Godrade\Trame\Layout;

use Godrade\Trame\Fields\Field;
use Godrade\Trame\Support\Tokens;

/**
 * Appearance settings shared by blocks and columns: background, text colour,
 * padding, border, radius, shadow and a free CSS class for the host's own styles.
 * Values are semantic; classes below are written literally for Tailwind.
 */
final class Box
{
    public const PADDING_RESPONSIVE = [
        'base' => ['none' => 'p-0', 'xs' => 'p-2', 'sm' => 'p-4', 'md' => 'p-8', 'lg' => 'p-12', 'xl' => 'p-16', '2xl' => 'p-24'],
        'md' => ['none' => 'md:p-0', 'xs' => 'md:p-2', 'sm' => 'md:p-4', 'md' => 'md:p-8', 'lg' => 'md:p-12', 'xl' => 'md:p-16', '2xl' => 'md:p-24'],
        'lg' => ['none' => 'lg:p-0', 'xs' => 'lg:p-2', 'sm' => 'lg:p-4', 'md' => 'lg:p-8', 'lg' => 'lg:p-12', 'xl' => 'lg:p-16', '2xl' => 'lg:p-24'],
    ];

    public const BORDER = ['none' => '', 'thin' => 'border', 'thick' => 'border-2'];

    public const RADIUS = [
        'none' => 'rounded-none',
        'theme' => 'rounded-(--trame-radius)',
        'sm' => 'rounded-sm',
        'md' => 'rounded-md',
        'lg' => 'rounded-lg',
        'xl' => 'rounded-xl',
        '2xl' => 'rounded-2xl',
        'full' => 'rounded-full',
    ];

    public const SHADOW = [
        'none' => 'shadow-none',
        'sm' => 'shadow-sm',
        'md' => 'shadow-md',
        'lg' => 'shadow-lg',
        'xl' => 'shadow-xl',
        '2xl' => 'shadow-2xl',
    ];

    /** Field names, used to store only the values that are set. */
    public const NAMES = ['padding', 'background', 'text_color', 'border', 'border_color', 'radius', 'shadow', 'class'];

    /** @return array<int, Field> */
    public static function fields(): array
    {
        return [
            Field::color('background')->allowCustom()->group(__('trame::fields.group_appearance')),
            Field::color('text_color')->allowCustom(),
            Field::select('padding')->label(__('trame::fields.box_padding'))->options(Tokens::SPACE)->responsive(),
            Field::select('border')->options(array_keys(self::BORDER)),
            Field::color('border_color')->allowCustom(),
            Field::select('radius')->options(array_keys(self::RADIUS)),
            Field::select('shadow')->options(array_keys(self::SHADOW)),
            // Classes of the host stylesheet. Tailwind classes typed here only work if the host build contains them.
            Field::text('class')->max(200)->rules(['regex:/^[A-Za-z0-9_\-:\/.\[\] ]*$/'])
                ->help(__('trame::fields.class_help')),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{class: string, style: string, framed: bool, padded: bool}
     */
    public static function classes(array $settings): array
    {
        $background = Tokens::color($settings['background'] ?? null, 'bg');
        $text = Tokens::color($settings['text_color'] ?? null, 'text');
        $border = self::BORDER[$settings['border'] ?? 'none'] ?? '';
        $borderColor = $border === '' ? ['class' => '', 'style' => ''] : Tokens::color($settings['border_color'] ?? null, 'border');
        $radius = self::RADIUS[$settings['radius'] ?? ''] ?? '';
        $shadow = self::SHADOW[$settings['shadow'] ?? ''] ?? '';
        $padding = isset($settings['padding']) ? Tokens::responsive(self::PADDING_RESPONSIVE, $settings['padding'], 'none') : '';
        $class = is_string($settings['class'] ?? null) && preg_match('/^[A-Za-z0-9_\-:\/.\[\] ]*$/', $settings['class']) ? trim($settings['class']) : '';

        return [
            'class' => Tokens::join(
                $background['class'],
                $text['class'],
                $padding,
                $border,
                $border === '' ? '' : ($borderColor['class'] ?: Tokens::RULE),
                $radius,
                // Rounded corners clip what is inside (an image at the top of a card).
                $radius !== '' && $radius !== 'rounded-none' ? 'overflow-hidden' : '',
                $shadow,
                $class,
            ),
            'style' => $background['style'].$text['style'].$borderColor['style'],
            'framed' => $background['class'] !== '' || $border !== '' || ($shadow !== '' && $shadow !== 'shadow-none'),
            'padded' => $padding !== '',
        ];
    }
}
