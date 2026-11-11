<?php

namespace Godrade\Trame\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the prebuilt editor assets from dist/ (no vendor:publish, no build step on the host).
 */
final class AssetController
{
    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless((bool) preg_match('/^[A-Za-z0-9_-]+\.(js|css)$/', $file), 404);

        $dist = realpath(self::distPath());
        $path = $dist ? realpath($dist.DIRECTORY_SEPARATOR.$file) : false;

        abort_unless($path && str_starts_with($path, $dist.DIRECTORY_SEPARATOR) && is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => str_ends_with($file, '.js') ? 'text/javascript; charset=utf-8' : 'text/css; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /** Versioned URL: the hash changes whenever the built file changes. */
    public static function url(string $file): string
    {
        static $versions = [];

        $path = self::distPath().DIRECTORY_SEPARATOR.$file;
        $versions[$file] ??= is_file($path) ? substr((string) md5_file($path), 0, 12) : 'missing';

        return route('trame.assets', ['file' => $file, 'v' => $versions[$file]]);
    }

    public static function distPath(): string
    {
        return dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'dist';
    }
}
