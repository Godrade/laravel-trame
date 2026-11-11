<?php

use Godrade\Trame\Blocks\BlockRegistry;
use Godrade\Trame\Blocks\Core\CalloutBlock;
use Godrade\Trame\Blocks\Core\QuestionBlock;
use Godrade\Trame\Blocks\Core\QuoteBlock;
use Godrade\Trame\Facades\Trame;
use Godrade\Trame\Media\MediaProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('registers the new core blocks', function () {
    expect(app(BlockRegistry::class)->get('quote'))->toBeInstanceOf(QuoteBlock::class)
        ->and(app(BlockRegistry::class)->get('question'))->toBeInstanceOf(QuestionBlock::class)
        ->and(app(BlockRegistry::class)->get('callout'))->toBeInstanceOf(CalloutBlock::class);
});

function renderBlock(string $type, array $data, bool $editing = false, string $locale = 'fr'): string
{
    return (string) Trame::render(trameDoc([['type' => $type, 'data' => $data]]), $locale, editing: $editing);
}

it('lets headings, text and dividers inherit the text colour by default', function () {
    expect(renderBlock('heading', ['text' => ['fr' => 'Titre']]))->toMatch('/<h2 class="font-semibold text-balance text-3xl tracking-tight text-start"\s*>/')
        ->and(renderBlock('text', ['content' => ['fr' => '<p>Texte</p>']]))->not->toMatch('/text-slate-\d+/')
        ->and(renderBlock('spacer', ['divider' => true]))->toContain('class="w-full border-t border-current/20"');
});

it('styles rich text lists, links and subheadings without the typography plugin', function () {
    expect(renderBlock('text', ['content' => ['fr' => '<ul><li>Un</li></ul><p><a href="/a">lien</a></p>']]))
        // "&" is encoded in the attribute; browsers decode it, Tailwind reads the PHP source.
        ->toContain('[&amp;_ul]:list-disc')
        ->toContain('[&amp;_a]:underline')
        ->toContain('[&amp;_h2]:font-semibold')
        ->toContain('<ul><li>Un</li></ul>');
});

it('renders button variants with the theme accent unless a colour is chosen', function () {
    $button = fn (array $data) => renderBlock('button', ['label' => ['fr' => 'Go'], 'url' => ['fr' => '/go'], ...$data]);

    expect($button([]))->toContain('bg-(--trame-accent) text-(--trame-on-accent)')->toContain('rounded-(--trame-radius)')
        ->and($button(['color' => 'emerald-600']))->toContain('bg-emerald-600 text-white')->not->toContain('--trame-accent')
        ->and($button(['variant' => 'outline']))->toContain('rounded-(--trame-radius) border hover:bg-current/5 px-4 py-2')->not->toContain('bg-(--trame-accent)')
        ->and($button(['size' => 'lg']))->toContain('px-6 py-3 text-lg')
        ->and($button(['variant' => 'link', 'size' => 'lg']))->toContain('hover:decoration-current text-lg')->not->toContain('px-6 py-3')
        ->and($button(['variant' => 'link', 'color' => 'rose-600']))->toContain('underline-offset-4')->toContain('text-rose-600')
        ->and($button(['variant' => 'ghost']))->toContain('bg-(--trame-accent)');
});

it('adds an optional caption to images', function () {
    Storage::fake('public');
    $media = app(MediaProvider::class)->store(UploadedFile::fake()->image('a.png', 20, 10));

    expect(renderBlock('image', ['image' => $media->id, 'alt' => ['fr' => 'Alt'], 'caption' => ['fr' => 'Légende']]))
        ->toContain('<figure>')
        ->toContain('alt="Alt"')
        ->toContain('<figcaption class="mt-2 text-sm opacity-70"')
        ->toContain('>Légende</figcaption>')
        ->and(renderBlock('image', ['image' => $media->id]))->not->toContain('figcaption');
});

it('renders quotes with author and source, escaped', function () {
    $html = renderBlock('quote', ['quote' => ['fr' => 'Simple <b>et</b> clair'], 'author' => 'Ada', 'source' => ['fr' => 'Ingénieure'], 'size' => 'lg']);

    expect($html)
        // The highlighted size takes the theme accent for its rule.
        ->toContain('<figure class="ps-6 border-s-4 border-(--trame-accent)"')
        ->toContain('text-2xl leading-snug font-medium')
        ->toContain('Simple &lt;b&gt;et&lt;/b&gt; clair')
        ->toContain('<span class="font-medium" >Ada</span>')
        ->toContain('Ingénieure')
        ->and(renderBlock('quote', ['quote' => ['fr' => 'Seule']]))->not->toContain('figcaption');
});

it('renders questions as native details, open in the editor', function () {
    $data = ['question' => ['fr' => 'Pourquoi ?'], 'answer' => ['fr' => '<p>Parce que.</p><script>x</script>']];

    expect(renderBlock('question', $data))
        ->toMatch('/<details class="group border-b py-4 border-current\/20"\s*>/')
        ->toContain('Pourquoi ?')
        ->toContain('<p>Parce que.</p>')
        ->not->toContain('<script>')
        ->and(renderBlock('question', [...$data, 'open' => true]))->toMatch('/<details[^>]*\sopen\s*>/')
        ->and(renderBlock('question', $data, editing: true))->toMatch('/<details[^>]*\sopen\s*>/')->toContain('data-trame-field="answer" data-trame-rich');
});

it('renders callouts with a tone rule and keeps the previous demo data shape', function () {
    $html = renderBlock('callout', ['title' => ['fr' => 'Attention'], 'content' => ['fr' => '<p>Texte</p>'], 'tone' => 'warning']);

    expect($html)
        ->toContain('<aside class="flex gap-3 border-s-4 bg-current/5 px-5 py-4 border-amber-500" role="note">')
        ->toContain('class="mt-1 size-5 shrink-0 text-amber-600"')
        ->toContain('M10 3.5 2.5 16.5h15L10 3.5Z')
        ->toContain('>Attention</p>')
        ->and(renderBlock('callout', ['content' => ['fr' => '<p>Note</p>']]))->toContain('border-current/40')->not->toContain('mb-1 font-semibold');
});

it('keeps text to a readable width, positioned by its alignment', function () {
    $text = fn (array $data) => renderBlock('text', ['content' => ['fr' => '<p>Texte</p>'], ...$data]);

    expect($text([]))->toContain('max-w-[65ch] ms-0 me-auto')->toContain('leading-7')
        ->and($text(['align' => ['base' => 'start', 'md' => 'center']]))->toContain('max-w-[65ch] ms-0 me-auto md:mx-auto text-start md:text-center')
        ->and($text(['width' => 'full']))->not->toContain('max-w-[65ch]')->not->toContain('me-auto')
        ->and($text(['size' => 'lead']))->toContain('text-lg leading-8 sm:text-xl sm:leading-9')->not->toContain('leading-7');
});
