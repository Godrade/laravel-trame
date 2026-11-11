<?php

namespace Godrade\Trame\Http\Controllers;

use Godrade\Trame\Document\DocumentNormalizer;
use Godrade\Trame\Document\DocumentValidator;
use Godrade\Trame\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class TemplateController
{
    public function __construct(
        private readonly DocumentValidator $validator,
        private readonly DocumentNormalizer $normalizer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['kind' => ['nullable', Rule::in(Template::KINDS)]]);

        $templates = Template::query()
            ->when($request->input('kind'), fn ($query, $kind) => $query->where('kind', $kind))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $template = Template::query()->create($this->validated($request));

        return response()->json($template, 201);
    }

    public function update(Request $request, Template $template): JsonResponse
    {
        $template->update($this->validated($request, $template));

        return response()->json($template);
    }

    public function destroy(Template $template): Response
    {
        // Pages referencing a deleted global template simply stop rendering it.
        $template->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Template $template = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => [$template ? 'prohibited' : 'required', Rule::in(Template::KINDS)],
            'is_global' => ['boolean'],
            'content' => ['required', 'array'],
        ]);

        $kind = $template?->kind ?? $data['kind'];

        if (($data['content']['type'] ?? null) === 'global') {
            throw ValidationException::withMessages(['content.type' => [__('trame::validation.structure')]]);
        }

        $this->validator->validateNode($kind, $data['content'], 'content');

        return [
            ...$data,
            'kind' => $kind,
            'content' => $this->normalizer->normalizeNode($kind, $data['content']),
        ];
    }
}
