<?php

namespace Godrade\Trame\Http\Controllers;

use Godrade\Trame\Document\DocumentNormalizer;
use Godrade\Trame\Document\DocumentValidator;
use Godrade\Trame\Models\Revision;
use Godrade\Trame\Models\TrameContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ContentController
{
    public function __construct(private readonly DocumentNormalizer $normalizer) {}

    public function show(TrameContent $content): JsonResponse
    {
        return $this->respond($content);
    }

    public function update(Request $request, TrameContent $content, DocumentValidator $validator): JsonResponse
    {
        $request->validate(['revision' => ['nullable', 'integer']]);

        // Optimistic locking: refuse to overwrite a draft saved by someone else in between.
        if ($request->filled('revision') && (int) $request->input('revision') !== $content->revision) {
            return response()->json([
                'message' => __('trame::validation.conflict'),
                'revision' => $content->revision,
            ], 409);
        }

        $validator->validate($request->input('document'));

        $content->document = $this->normalizer->normalize($request->input('document'));
        $content->revision++;
        $content->save();
        $content->recordRevision(Revision::SAVE, $request->user()?->getAuthIdentifier());

        return $this->respond($content);
    }

    public function publish(Request $request, TrameContent $content): JsonResponse
    {
        $content->publish();
        $content->recordRevision(Revision::PUBLISH, $request->user()?->getAuthIdentifier());

        return $this->respond($content);
    }

    public function unpublish(TrameContent $content): JsonResponse
    {
        $content->unpublish();

        return $this->respond($content);
    }

    private function respond(TrameContent $content): JsonResponse
    {
        return response()->json([
            'id' => $content->getKey(),
            'key' => $content->key,
            'revision' => $content->revision,
            'document' => $this->normalizer->normalize($content->document),
            'published_at' => $content->published_at?->toIso8601String(),
            'has_unpublished_changes' => $content->hasUnpublishedChanges(),
        ]);
    }
}
