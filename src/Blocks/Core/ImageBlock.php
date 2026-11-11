<?php

namespace Godrade\Trame\Blocks\Core;

use Godrade\Trame\Blocks\Block;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Media\MediaProvider;
use Godrade\Trame\Support\Tokens;

final class ImageBlock extends Block
{
    public static string $type = 'image';

    public static string $icon = 'photo';

    public static string $category = 'media';

    public function __construct(private readonly MediaProvider $media) {}

    public static function label(): string
    {
        return __('trame::blocks.image');
    }

    public function schema(): array
    {
        return [
            Field::image('image'),
            Field::text('alt')->translatable(),
            Field::select('rounded')->options(Tokens::keys(Tokens::ROUNDED))->default('none'),
        ];
    }

    public function with(array $data): array
    {
        $item = $data['image'] !== null ? $this->media->find($data['image']) : null;

        return [
            'media' => $item,
            'classes' => Tokens::join('block h-auto w-full max-w-full', Tokens::pick(Tokens::ROUNDED, $data['rounded'], 'none')),
        ];
    }

    public function view(): string
    {
        return 'trame::blocks.image';
    }
}
