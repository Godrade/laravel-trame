<?php

namespace Godrade\Trame\Http\Controllers;

use Godrade\Trame\Document\DocumentValidator;
use Godrade\Trame\Support\Localizer;
use Godrade\Trame\Trame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Renders an unsaved document for the editor iframe and reports validation
 * errors without blocking the render (live validation while editing).
 *
 * Rendering unvalidated input is safe: the normalizer sanitizes rich text,
 * Blade escapes plain text, tokens only emit known classes and URLs are
 * filtered by the blocks themselves.
 */
final class PreviewController
{
    public function __invoke(Request $request, Trame $trame, DocumentValidator $validator, Localizer $localizer): JsonResponse
    {
        $request->validate([
            'locale' => ['required', Rule::in($localizer->locales())],
            'document' => ['required', 'array'],
        ]);

        $errors = [];

        try {
            $validator->validate($request->input('document'));
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }

        return response()->json([
            'html' => (string) $trame->render($request->input('document'), $request->input('locale'), editing: true),
            'errors' => (object) $errors,
        ], 200, ['Cache-Control' => 'no-store']);
    }
}
