<?php

namespace Godrade\Trame\Http\Controllers;

use Godrade\Trame\Links\LinkResolver;
use Godrade\Trame\Support\Localizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LinkController
{
    public function __construct(
        private readonly LinkResolver $links,
        private readonly Localizer $localizer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', 'max:12'],
        ]);

        return response()->json([
            'data' => array_slice(
                $this->links->options($this->localizer->current($request->input('locale')), $request->input('q')),
                0,
                50,
            ),
        ]);
    }
}
