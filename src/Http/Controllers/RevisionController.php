<?php

namespace Godrade\Trame\Http\Controllers;

use Godrade\Trame\Document\DocumentNormalizer;
use Godrade\Trame\Models\Revision;
use Godrade\Trame\Models\TrameContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RevisionController
{
    public function index(TrameContent $content): JsonResponse
    {
        $page = $content->revisions()
            ->select(['id', 'kind', 'revision', 'user_id', 'created_at', 'updated_at'])
            ->paginate(25);

        $names = $this->userNames($page->getCollection()->pluck('user_id')->filter()->unique()->all());

        return response()->json([
            'data' => $page->getCollection()->map(fn (Revision $revision) => [
                'id' => $revision->id,
                'kind' => $revision->kind,
                'revision' => $revision->revision,
                'created_at' => $revision->created_at?->toIso8601String(),
                'updated_at' => $revision->updated_at?->toIso8601String(),
                'user' => $names[$revision->user_id] ?? null,
            ])->values(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    public function restore(Request $request, TrameContent $content, int $revision, DocumentNormalizer $normalizer): JsonResponse
    {
        $snapshot = $content->revisions()->findOrFail($revision);

        $content->document = $normalizer->normalize($snapshot->document);
        $content->revision++;
        $content->save();
        $content->recordRevision(Revision::RESTORE, $request->user()?->getAuthIdentifier());

        return app(ContentController::class)->show($content);
    }

    /**
     * Resolve author names through the host's auth provider model, if any.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function userNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $guard = config('auth.defaults.guard');
        $provider = config("auth.guards.{$guard}.provider");
        $model = config("auth.providers.{$provider}.model");

        if (! is_string($model) || ! class_exists($model)) {
            return [];
        }

        try {
            return $model::query()->whereIn((new $model)->getKeyName(), $ids)->get()
                ->mapWithKeys(fn ($user) => [$user->getKey() => (string) ($user->name ?? '')])
                ->filter()->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
