<?php

use Godrade\Trame\Blocks\BlockRegistry;
use Godrade\Trame\Facades\Trame;
use Godrade\Trame\Media\MediaProvider;
use Godrade\Trame\Models\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Runs the code samples of README.md, so the documentation cannot drift from the code.
 */
function readmeBlock(string $marker): string
{
    preg_match_all('/```(?:php|blade)\n(.*?)```/s', (string) file_get_contents(__DIR__.'/../../README.md'), $matches);

    foreach ($matches[1] as $code) {
        if (str_contains($code, $marker)) {
            return $code;
        }
    }

    throw new RuntimeException("No README code block contains [{$marker}].");
}

function readmeViews(): string
{
    $dir = sys_get_temp_dir().'/trame-readme-views';

    foreach ([
        'trame/blocks/testimonial.blade.php' => '{{-- resources/views/trame/blocks/testimonial.blade.php --}}',
        'trame/preview.blade.php' => '{{-- resources/views/trame/preview.blade.php --}}',
    ] as $path => $marker) {
        @mkdir(dirname("{$dir}/{$path}"), 0777, true);
        file_put_contents("{$dir}/{$path}", readmeBlock($marker));
    }

    return $dir;
}

beforeEach(function () {
    if (! class_exists('App\Trame\Blocks\TestimonialBlock')) {
        eval(readmeBlock('final class TestimonialBlock'));
    }

    view()->addLocation(readmeViews());
    config(['trame.blocks.register' => ['App\Trame\Blocks\TestimonialBlock']]);
    app()->forgetInstance(BlockRegistry::class);
    Storage::fake('public');
});

it('runs the custom block example', function () {
    $avatar = app(MediaProvider::class)->store(UploadedFile::fake()->image('ada.png', 40, 40));

    $doc = trameDoc([[
        'type' => 'testimonial',
        'data' => [
            'quote' => ['fr' => 'Très utile', 'en' => 'Very useful'],
            'author' => 'Ada Lovelace',
            'avatar' => $avatar->id,
            'align' => ['base' => 'center', 'md' => 'start'],
        ],
    ]]);

    expect((string) Trame::render($doc, 'en'))
        ->toContain('<figure class="text-center md:text-start">')
        ->toContain('<p >Very useful</p>')
        ->toContain('src="'.$avatar->url.'"')
        ->toContain('>Ada Lovelace</span>');

    expect((string) Trame::render($doc, 'fr', editing: true))
        ->toContain('data-trame-field="quote"')
        ->toContain('data-trame-field="author"');

    $doc['sections'][0]['rows'][0]['columns'][0]['blocks'][0]['data']['avatar'] = null;

    expect((string) Trame::render($doc, 'fr'))->toContain('size-10 rounded-full bg-indigo-600');
});

it('validates the custom block example through its schema', function () {
    expect(fn () => app(\Godrade\Trame\Document\DocumentValidator::class)->validate(trameDoc([
        ['type' => 'testimonial', 'data' => ['quote' => ['fr' => str_repeat('a', 501)], 'author' => 'A']],
    ])))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('runs the preview layout example and keeps the editor bridge', function () {
    config(['trame.editor.preview.layout' => 'trame.preview', 'trame.editor.preview.vite' => []]);
    Gate::define('trame.edit', fn () => true);

    $page = Page::query()->create(['title' => ['fr' => 'Accueil'], 'slug' => ['fr' => 'accueil']]);
    $content = $page->trame();

    $this->actingAs(trameUser())
        ->get(route('trame.editor.frame', $content))
        ->assertOk()
        ->assertSee('class="bg-white font-sans antialiased"', false)
        ->assertSee('/frame.js', false);
});
