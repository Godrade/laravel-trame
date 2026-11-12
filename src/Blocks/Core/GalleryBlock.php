<?php

namespace Godrade\Trame\Blocks\Core;

use Godrade\Trame\Blocks\Block;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Media\MediaProvider;
use Godrade\Trame\Support\Tokens;

final class GalleryBlock extends Block
{
    // Literal class names: Tailwind scans src, so never build these by concatenation.
    private const COLUMNS = [
        'base' => ['1' => 'grid-cols-1', '2' => 'grid-cols-2', '3' => 'grid-cols-3', '4' => 'grid-cols-4'],
        'md' => ['1' => 'md:grid-cols-1', '2' => 'md:grid-cols-2', '3' => 'md:grid-cols-3', '4' => 'md:grid-cols-4'],
        'lg' => ['1' => 'lg:grid-cols-1', '2' => 'lg:grid-cols-2', '3' => 'lg:grid-cols-3', '4' => 'lg:grid-cols-4'],
    ];

    private const RATIO = [
        'auto' => '',
        'square' => 'aspect-square object-cover',
        'landscape' => 'aspect-[4/3] object-cover',
        'portrait' => 'aspect-[3/4] object-cover',
    ];

    public static string $type = 'gallery';

    public static string $icon = 'photo';

    public static string $category = 'media';

    public function __construct(private readonly MediaProvider $media) {}

    public static function label(): string
    {
        return __('trame::blocks.gallery');
    }

    public function schema(): array
    {
        return [
            Field::repeater('images')->fields([
                Field::image('image')->required(),
                Field::text('alt')->translatable(),
                Field::text('caption')->translatable(),
            ])->max(24),
            // Two per row on phones, three from tablets up.
            Field::select('columns')->options(['1', '2', '3', '4'])->default(['base' => '2', 'md' => '3'])->responsive()->advanced(),
            Field::select('ratio')->options(['auto', 'square', 'landscape', 'portrait'])->default('auto')->advanced(),
        ];
    }

    public function with(array $data): array
    {
        $images = [];

        foreach ($data['images'] ?? [] as $item) {
            $media = ($item['image'] ?? null) !== null ? $this->media->find($item['image']) : null;

            if ($media) {
                $images[] = ['media' => $media, 'alt' => $item['alt'] ?? '', 'caption' => $item['caption'] ?? null];
            }
        }

        return [
            'images' => $images,
            'columns' => Tokens::responsive(self::COLUMNS, $data['columns'] ?? null, '2'),
            'imageClasses' => Tokens::join('block h-auto w-full', self::RATIO[$data['ratio'] ?? 'auto'] ?? '', 'rounded-(--trame-radius)'),
        ];
    }

    public function view(): string
    {
        return 'trame::blocks.gallery';
    }
}
