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
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
