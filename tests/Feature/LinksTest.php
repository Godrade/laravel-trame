<?php

use Godrade\Trame\Document\DocumentValidator;
use Godrade\Trame\Facades\Trame;
use Godrade\Trame\Fields\Field;
use Godrade\Trame\Fields\Schema;
use Godrade\Trame\Links\LinkResolver;
use Godrade\Trame\Models\Page;
use Godrade\Trame\Support\Localizer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

function linkPage(array $title = ['fr' => 'Contact', 'en' => 'Contact us'], array $slug = ['fr' => 'contact', 'en' => 'contact-us'], string $status = 'published'): Page
{
    return Page::query()->create(['title' => $title, 'slug' => $slug, 'status' => $status]);
}

function buttonDoc(string $url): array
{
    return trameDoc([['type' => 'button', 'data' => ['label' => ['fr' => 'Go'], 'url' => ['fr' => $url, 'en' => $url]]]]);
}

function linkErrors(string $url): array
{
    try {
        app(DocumentValidator::class)->validate(buttonDoc($url));
    } catch (ValidationException $e) {
        return array_keys($e->errors());
    }

    return [];
}

describe('validation', function () {
    it('accepts a page reference', function () {
        expect(linkErrors('page:12'))->toBe([])
            ->and(linkErrors('https://example.test'))->toBe([])
            ->and(linkErrors('/about'))->toBe([]);
    });

    it('rejects malformed references', function (string $url) {
        expect(linkErrors($url))->not->toBe([]);
    })->with(['page:abc', 'page:', 'page:12x', 'page:1 2', 'pages:3', 'javascript:alert(1)']);
});

describe('resolution', function () {
    it('renders a page reference in a button', function () {
        $page = linkPage();

        $html = (string) Trame::render(buttonDoc("page:{$page->id}"), 'fr');
        $en = (string) Trame::render(buttonDoc("page:{$page->id}"), 'en');

        expect($html)->toContain('href="/contact"')->not->toContain('page:')
            ->and($en)->toContain('href="/contact-us"');
    });

    it('uses # for a missing page', function () {
        expect((string) Trame::render(buttonDoc('page:9999'), 'fr'))->toContain('href="#"');
    });

    it('links draft pages too', function () {
        $page = linkPage(status: 'draft');

        expect(app(LinkResolver::class)->resolve("page:{$page->id}", 'fr'))->toBe('/contact');
    });

    it('uses the page_url callable of the host', function () {
        $page = linkPage();
        config(['trame.links.page_url' => fn (Page $page, string $locale) => "https://site.test/{$locale}/p/".$page->getTranslation('slug', $locale)]);

        expect((string) Trame::render(buttonDoc("page:{$page->id}"), 'en'))->toContain('href="https://site.test/en/p/contact-us"');
    });

    it('uses the trame.page route when it exists', function () {
        Trame::pageRoutes('pages');
        app('router')->getRoutes()->refreshNameLookups();
        $page = linkPage();

        expect(app(LinkResolver::class)->resolve("page:{$page->id}", 'fr'))->toBe('/pages/contact');
    });

    it('resolves url fields inside repeater items', function () {
        $page = linkPage();
        $fields = [
            Field::repeater('links')->fields([
                Field::text('label')->translatable(),
                Field::url('url'),
            ]),
        ];
        $data = ['links' => [
            ['id' => 'a', 'label' => ['fr' => 'Un'], 'url' => "page:{$page->id}"],
            ['id' => 'b', 'label' => ['fr' => 'Deux'], 'url' => 'page:404'],
            ['id' => 'c', 'label' => ['fr' => 'Trois'], 'url' => 'https://example.test'],
        ]];

        $resolved = Schema::resolve($fields, $data, 'fr', app(Localizer::class));

        expect(array_column($resolved['links'], 'url'))->toBe(['/contact', '#', 'https://example.test']);
    });

    it('honours a custom resolver class', function () {
        config(['trame.links.resolver' => FakeLinkResolver::class]);
        app()->forgetInstance(LinkResolver::class);

        expect((string) Trame::render(buttonDoc('page:1'), 'fr'))->toContain('href="/fake/1"');
    });
});

describe('picker endpoint', function () {
    beforeEach(function () {
        $this->contact = linkPage();
        $this->about = linkPage(['fr' => 'À propos', 'en' => 'About'], ['fr' => 'a-propos', 'en' => 'about']);
    });

    it('is denied without the gate', function () {
        $this->actingAs(trameUser())->getJson(route('trame.api.links'))->assertForbidden();
    });

    it('lists pages for a viewer', function () {
        Gate::define('trame.view', fn ($user) => true);

        $this->actingAs(trameUser())->getJson(route('trame.api.links', ['locale' => 'en']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', ['ref' => "page:{$this->contact->id}", 'label' => 'Contact us', 'url' => '/contact-us']);
    });

    it('filters by search', function () {
        Gate::define('trame.edit', fn ($user) => true);

        $this->actingAs(trameUser())->getJson(route('trame.api.links', ['q' => 'propos', 'locale' => 'fr']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ref', "page:{$this->about->id}");
    });

    it('returns at most 50 pages', function () {
        Gate::define('trame.edit', fn ($user) => true);

        foreach (range(1, 55) as $i) {
            linkPage(['fr' => "Page {$i}"], ['fr' => "page-{$i}"]);
        }

        $this->actingAs(trameUser())->getJson(route('trame.api.links'))->assertJsonCount(50, 'data');
    });

    it('refuses a malformed locale', function () {
        Gate::define('trame.edit', fn ($user) => true);

        $this->actingAs(trameUser())->getJson(route('trame.api.links', ['q' => 'x', 'locale' => "fr') or 1=1 --"]))->assertUnprocessable();
    });
});

class FakeLinkResolver implements LinkResolver
{
    public function resolve(string $ref, string $locale): ?string
    {
        return '/fake/'.substr($ref, 5);
    }

    public function options(string $locale, ?string $search): array
    {
        return [];
    }
}
