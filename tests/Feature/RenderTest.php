<?php

use Godrade\Trame\Facades\Trame;
use Godrade\Trame\Models\Page;
use Godrade\Trame\Models\Template;

beforeEach(fn () => view()->addNamespace('fixtures', __DIR__.'/../Fixtures/views'));

it('renders the grid and blocks in the requested locale', function () {
    $html = (string) Trame::render(trameDoc(
        [heading(['fr' => 'Bonjour', 'en' => 'Hello'], ['level' => 1])],
        [['type' => 'quote', 'data' => ['quote' => ['fr' => 'Citation']]]],
    ), 'en');

    expect($html)
        ->toContain('<h1 class="font-bold tracking-tight text-3xl text-start text-slate-900">Hello</h1>')
        ->toContain('grid grid-cols-12 gap-x-3 gap-y-6 md:gap-x-6')
        ->toContain('col-span-12 md:col-span-6')
        ->toContain('<blockquote>Citation</blockquote>')
        ->not->toContain('data-trame-id');
});

it('falls back to the fallback locale by default', function () {
    expect((string) Trame::render(trameDoc([heading(['fr' => 'Bonjour'])]), 'en'))->toContain('>Bonjour</h2>');
});

it('hides blocks with missing required translations in hide mode', function () {
    config(['trame.missing_translation' => 'hide']);

    expect((string) Trame::render(trameDoc([heading(['fr' => 'Bonjour'])]), 'en'))->not->toContain('<h2');
});

it('adds editing attributes only in editing mode', function () {
    expect((string) Trame::render(trameDoc([heading(['fr' => 'X'])]), 'fr', editing: true))
        ->toContain('data-trame-node="section"')
        ->toContain('data-trame-type="heading"');
});

it('escapes plain text fields', function () {
    expect((string) Trame::render(trameDoc([heading(['fr' => '<img src=x onerror=alert(1)>'])]), 'fr'))
        ->toContain('&lt;img src=x onerror=alert(1)&gt;');
});

it('resolves global templates and guards against recursion', function () {
    $block = Template::query()->create([
        'name' => 'CTA title', 'kind' => 'block', 'is_global' => true,
        'content' => heading(['fr' => 'Global']),
    ]);

    $loop = Template::query()->create(['name' => 'Loop', 'kind' => 'block', 'is_global' => true, 'content' => ['type' => 'heading', 'data' => []]]);
    $loop->update(['content' => ['type' => 'global', 'ref' => $loop->id]]);

    $html = (string) Trame::render(trameDoc([
        ['type' => 'global', 'ref' => $block->id],
        ['type' => 'global', 'ref' => $loop->id],
    ]), 'fr');

    expect($html)->toContain('>Global</h2>');
});

it('renders published content through HasTrame and caches per publication', function () {
    $page = Page::query()->create(['title' => ['fr' => 'Accueil'], 'slug' => ['fr' => 'accueil']]);
    $content = $page->trame();

    expect((string) $page->renderTrame())->toBe('');

    $content->update(['document' => trameDoc([heading(['fr' => 'V1'])])]);
    $content->publish();
    expect((string) $page->renderTrame(locale: 'fr'))->toContain('V1');

    $content->update(['document' => trameDoc([heading(['fr' => 'V2'])])]);
    expect((string) $page->renderTrame(locale: 'fr'))->toContain('V1')
        ->and((string) $page->renderTrame(locale: 'fr', draft: true))->toContain('V2');

    $content->publish();
    expect((string) $page->fresh()->renderTrame(locale: 'fr'))->toContain('V2');
});

it('serves published pages by translated slug', function () {
    Trame::pageRoutes('pages');

    $page = Page::query()->create([
        'title' => ['fr' => 'À propos', 'en' => 'About'],
        'slug' => ['fr' => 'a-propos', 'en' => 'about'],
        'status' => Page::STATUS_PUBLISHED,
    ]);
    $page->trame()->fill(['document' => trameDoc([heading(['fr' => 'Qui', 'en' => 'Who'])])])->publish();

    app()->setLocale('en');

    $this->get('/pages/about')->assertOk()->assertSee('Who')->assertSee('<title>About</title>', false);
    $this->get('/pages/a-propos')->assertNotFound();
});
