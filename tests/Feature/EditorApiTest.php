<?php

use Godrade\Trame\Models\Page;
use Godrade\Trame\Models\Template;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->page = Page::query()->create(['title' => ['fr' => 'Accueil'], 'slug' => ['fr' => 'accueil']]);
    $this->content = $this->page->trame();
});

it('denies everyone until the host defines the gate', function () {
    $this->actingAs(trameUser())
        ->getJson(route('trame.api.definitions'))
        ->assertForbidden();
});

it('requires authentication', function () {
    $this->getJson(route('trame.api.definitions'))->assertUnauthorized();
});

describe('authorized editor', function () {
    beforeEach(function () {
        Gate::define('trame.edit', fn ($user) => true);
        $this->actingAs(trameUser());
    });

    it('saves a valid draft and increments the revision', function () {
        $this->putJson(route('trame.api.contents.update', $this->content), [
            'revision' => 0,
            'document' => trameDoc([heading(['fr' => 'Bonjour'])]),
        ])
            ->assertOk()
            ->assertJsonPath('revision', 1)
            ->assertJsonPath('document.sections.0.rows.0.columns.0.blocks.0.data.text.fr', 'Bonjour')
            ->assertJsonPath('has_unpublished_changes', true);
    });

    it('returns path-based validation errors', function () {
        $this->putJson(route('trame.api.contents.update', $this->content), [
            'document' => trameDoc([heading(['en' => 'Only english'])]),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document.sections.0.rows.0.columns.0.blocks.0.data.text.fr']);
    });

    it('refuses to overwrite a newer revision', function () {
        $this->content->forceFill(['revision' => 3])->save();

        $this->putJson(route('trame.api.contents.update', $this->content), [
            'revision' => 2,
            'document' => trameDoc([heading(['fr' => 'Stale'])]),
        ])->assertStatus(409)->assertJsonPath('revision', 3);
    });

    it('publishes the draft', function () {
        $this->content->update(['document' => trameDoc([heading(['fr' => 'Live'])])]);

        $this->postJson(route('trame.api.contents.publish', $this->content))
            ->assertOk()
            ->assertJsonPath('has_unpublished_changes', false);

        expect($this->content->fresh()->published)->not->toBeNull();
    });

    it('renders a preview fragment and reports errors without blocking', function () {
        $response = $this->postJson(route('trame.api.preview'), [
            'locale' => 'fr',
            'document' => trameDoc(
                [heading(['fr' => 'Aperçu'])],
                [heading(['en' => 'No french'])],
                [['type' => 'button', 'data' => ['label' => ['fr' => 'Go'], 'url' => ['fr' => 'javascript:alert(1)']]]],
            ),
        ])->assertOk();

        expect($response->json('html'))
            ->toContain('data-trame-type="heading"')
            ->toContain('Aperçu')
            ->toContain('href="#"')
            ->not->toContain('javascript:');

        expect($response->json('errors'))->toHaveKeys([
            'document.sections.0.rows.0.columns.1.blocks.0.data.text.fr',
            'document.sections.0.rows.0.columns.2.blocks.0.data.url.fr',
        ]);
    });

    it('serves the editor page and the preview frame', function () {
        $this->get(route('trame.editor', ['content' => $this->content, 'return' => '/admin/pages']))
            ->assertOk()
            ->assertSee('x-data="trameEditor"', false)
            ->assertSee('window.TrameEditor', false)
            ->assertSee('\/admin\/pages', false);

        $this->content->update(['document' => trameDoc([heading(['fr' => 'Cadre'])])]);

        $this->get(route('trame.editor.frame', ['content' => $this->content, 'locale' => 'fr']))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertSee('data-trame-root', false)
            ->assertSee('Cadre')
            ->assertSee('frame.js', false);
    });

    it('never uses an external return url', function () {
        $this->get(route('trame.editor', ['content' => $this->content, 'return' => '//evil.test/x']))
            ->assertOk()
            ->assertDontSee('evil.test');
    });

    it('serves built assets and refuses traversal', function () {
        $this->get(route('trame.assets', ['file' => 'editor.js']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/javascript; charset=utf-8');

        $this->get('/trame/assets/..%2Fcomposer.json')->assertNotFound();
        $this->get(route('trame.assets', ['file' => 'missing.js']))->assertNotFound();
    });

    it('stores templates and rejects global roots', function () {
        $this->postJson(route('trame.api.templates.store'), [
            'name' => 'Hero', 'kind' => 'section', 'is_global' => true,
            'content' => trameDoc([heading(['fr' => 'Hero'])])['sections'][0],
        ])->assertCreated()->assertJsonPath('kind', 'section');

        $this->postJson(route('trame.api.templates.store'), [
            'name' => 'Bad', 'kind' => 'block', 'content' => ['type' => 'global', 'ref' => 1],
        ])->assertUnprocessable();

        expect(Template::query()->count())->toBe(1);
    });

    it('uploads images and refuses svg', function () {
        Storage::fake('public');

        $id = $this->postJson(route('trame.api.media.store'), ['file' => UploadedFile::fake()->image('photo.jpg', 640, 480)])
            ->assertCreated()
            ->assertJsonPath('width', 640)
            ->assertJsonPath('name', 'photo.jpg')
            ->json('id');

        $this->getJson(route('trame.api.media.show', ['media' => $id]))->assertOk()->assertJsonPath('id', $id);
        $this->getJson(route('trame.api.media.show', ['media' => 999]))->assertNotFound();

        $this->postJson(route('trame.api.media.store'), [
            'file' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ])->assertUnprocessable();
    });
});
