<?php

namespace Godrade\Trame\Http\Controllers;

use Godrade\Trame\Models\TrameContent;
use Godrade\Trame\Support\Localizer;
use Godrade\Trame\Trame;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class EditorController
{
    /** Blocks and column layouts offered in simple mode (see config trame.editor.simple). */
    public const SIMPLE_BLOCKS = ['heading', 'text', 'image', 'button', 'card', 'quote', 'question'];

    public const SIMPLE_LAYOUTS = ['12', '6-6', '4-4-4', '8-4'];

    public function show(Request $request, TrameContent $content, Localizer $localizer): View
    {
        return view('trame::editor.app', [
            'assets' => [
                'css' => AssetController::url('editor.css'),
                'js' => AssetController::url('editor.js'),
            ],
            'config' => [
                'contentId' => $content->getKey(),
                'title' => $this->title($content),
                'locale' => $localizer->current($request->query('locale')),
                'returnUrl' => $this->returnUrl($request),
                // Read-only viewers (trame.view without trame.edit) can try everything; nothing is sent back.
                'canEdit' => Gate::allows('trame.edit'),
                // Simple mode only, unless the host grants "trame.advanced" (then a toggle is shown).
                'canAdvanced' => Gate::allows('trame.advanced'),
                // Defaults apply when a published config predates these keys; an empty list disables the filter.
                'simple' => [
                    'blocks' => array_values((array) config('trame.editor.simple.blocks', self::SIMPLE_BLOCKS)),
                    'layouts' => array_values((array) config('trame.editor.simple.layouts', self::SIMPLE_LAYOUTS)),
                ],
                'endpoints' => [
                    'definitions' => route('trame.api.definitions'),
                    'content' => route('trame.api.contents.show', $content),
                    'publish' => route('trame.api.contents.publish', $content),
                    'unpublish' => route('trame.api.contents.unpublish', $content),
                    'preview' => route('trame.api.preview'),
                    'frame' => route('trame.editor.frame', $content),
                    'media' => route('trame.api.media.index'),
                    'mediaShow' => route('trame.api.media.show', ['media' => '__ID__']),
                    'templates' => route('trame.api.templates.index'),
                    'template' => route('trame.api.templates.update', ['template' => '__ID__']),
                    'revisions' => route('trame.api.revisions.index', $content),
                    'revisionRestore' => route('trame.api.revisions.restore', ['content' => $content, 'revision' => '__ID__']),
                    'links' => route('trame.api.links'),
                ],
                // One lang file per editor feature (editor_history, editor_content, editor_checks).
                'strings' => array_merge(...array_map(
                    fn (string $file) => (array) trans("trame::{$file}"),
                    ['editor', 'editor_history', 'editor_content', 'editor_checks'],
                )),
            ],
        ]);
    }

    /** Full preview document loaded once in the iframe; later updates are pushed as fragments. */
    public function frame(Request $request, TrameContent $content, Trame $trame, Localizer $localizer): Response
    {
        $locale = $localizer->current($request->query('locale'));

        $html = view(config('trame.editor.preview.layout', 'trame::layouts.preview'), [
            'content' => $trame->render($content->document, $locale, editing: true),
            'locale' => $locale,
            'vite' => (array) config('trame.editor.preview.vite', []),
            'bridge' => '<script type="module" src="'.e(AssetController::url('frame.js')).'"></script>',
        ])->render();

        return response($html)
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Cache-Control', 'no-store');
    }

    private function title(TrameContent $content): string
    {
        $model = $content->contentable;

        $name = $model && method_exists($model, 'getTranslation')
            ? ($model->getTranslation('title') ?? class_basename($model).' #'.$model->getKey())
            : class_basename((string) $content->contentable_type).' #'.$content->contentable_id;

        return $content->key === 'default' ? $name : "{$name} · {$content->key}";
    }

    private function returnUrl(Request $request): string
    {
        $return = $request->query('return');

        // Relative paths only: never redirect editors to another host.
        if (is_string($return) && str_starts_with($return, '/') && ! str_starts_with($return, '//') && ! str_contains($return, '\\')) {
            return url($return);
        }

        return url((string) config('trame.editor.return_url', '/'));
    }
}
