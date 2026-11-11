<?php

namespace Godrade\Trame\Http\Controllers;

use Godrade\Trame\Media\MediaProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MediaController
{
    public function __construct(private readonly MediaProvider $media) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return response()->json($this->media->list((int) $request->input('page', 1)));
    }

    public function show(string $media): JsonResponse
    {
        $item = $this->media->find(ctype_digit($media) ? (int) $media : $media);

        abort_if($item === null, 404);

        return response()->json($item);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // SVG excluded on purpose: it can carry scripts.
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp,avif', 'max:'.(int) config('trame.media.max_kb', 5120)],
        ]);

        return response()->json($this->media->store($request->file('file')), 201);
    }
}
