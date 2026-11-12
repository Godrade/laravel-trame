<?php

namespace Godrade\Trame\Media;

use JsonSerializable;

final readonly class MediaItem implements JsonSerializable
{
    public function __construct(
        public int|string $id,
        public string $url,
        public string $name,
        public string $mime,
        public ?int $width = null,
        public ?int $height = null,
        /** `srcset` attribute value ('url 480w, url 960w'), or null when there are no variants. */
        public ?string $srcset = null,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
