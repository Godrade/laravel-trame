<?php

namespace Godrade\Trame\Blocks\Core;

use Godrade\Trame\Blocks\Block;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Media\MediaProvider;
use Godrade\Trame\Support\Tokens;

/**
 * A self-contained item (feature, offer, article teaser): optional image,
 * title, short text and link. Cards in the same row stretch to equal height
 * and align their links at the bottom.
 */
final class CardBlock extends Block
{
    public static string $type = 'card';

    public static string $icon = 'card';

    public static string $category = 'content';

    /** Background, border, radius and shadow go on the card itself (the image follows its corners). */
    public static bool $box = true;

    public const RATIOS = ['landscape' => 'aspect-[4/3]', 'wide' => 'aspect-video', 'square' => 'aspect-square'];

    public function __construct(private readonly MediaProvider $media) {}

    public static function label(): string
    {
        return __('trame::blocks.card');
    }

    public function schema(): array
    {
        return [
            Field::image('image'),
            Field::text('alt')->translatable()->max(200),
            Field::select('ratio')->options(array_keys(self::RATIOS))->default('landscape'),
            Field::text('title')->translatable()->required()->max(120),
            Field::textarea('text')->translatable()->max(600),
            Field::text('link_label')->translatable()->max(60),
            Field::url('url')->translatable(),
        ];
    }

    public function with(array $data): array
    {
        $url = (string) $data['url'];

        return [
            'media' => $data['image'] !== null ? $this->media->find($data['image']) : null,
            'imageClasses' => Tokens::join('block w-full object-cover', self::RATIOS[$data['ratio']] ?? self::RATIOS['landscape']),
            // Same allow-list as the url field rule, re-applied for previews of unsaved data.
            'href' => $url !== '' && preg_match('/^(https?:\/\/|\/|#|mailto:|tel:)/i', $url) ? $url : null,
        ];
    }

    public function view(): string
    {
        return 'trame::blocks.card';
    }
}
