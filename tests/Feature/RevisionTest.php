<?php

use Godrade\Trame\Models\Page;
use Godrade\Trame\Models\Revision;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->page = Page::query()->create(['title' => ['fr' => 'Accueil'], 'slug' => ['fr' => 'accueil']]);
    $this->content = $this->page->trame();
    Gate::define('trame.edit', fn ($user) => true);
    $this->user = trameUser();
    $this->actingAs($this->user);

    $this->saveDraft = function (string $text) {
        $this->content->refresh();

        return $this->putJson(route('trame.api.contents.update', $this->content), [
            'revision' => $this->content->revision,
            'document' => trameDoc([heading(['fr' => $text])]),
        ])->assertOk();
    };
});

it('coalesces consecutive saves of the same user', function () {
    ($this->saveDraft)('A');
    ($this->saveDraft)('B');

    expect(Revision::count())->toBe(1);
    $revision = Revision::first();
    expect($revision->kind)->toBe('save')
        ->and($revision->revision)->toBe(2)
        ->and($revision->user_id)->toBe($this->user->id)
        ->and(json_encode($revision->document))->toContain('B');
});

it('creates a new revision for another user or after the window', function () {
    ($this->saveDraft)('A');

    $this->actingAs(trameUser2());
    ($this->saveDraft)('B');
    expect(Revision::count())->toBe(2);

    $this->travel(11)->minutes();
    ($this->saveDraft)('C');
    expect(Revision::count())->toBe(3);
});

it('records publish and restore revisions', function () {
    ($this->saveDraft)('A');
    $this->postJson(route('trame.api.contents.publish', $this->content))->assertOk();
    expect($this->content->revisions()->first()->kind)->toBe('publish');

    $save = Revision::where('kind', 'save')->first();
    $this->postJson(route('trame.api.revisions.restore', [$this->content, $save->id]))->assertOk();
    expect($this->content->revisions()->first()->kind)->toBe('restore')
        ->and(Revision::count())->toBe(3);
});

it('keeps only the configured number of revisions', function () {
    config(['trame.revisions.keep' => 3]);

    foreach (range(1, 5) as $i) {
        $this->postJson(route('trame.api.contents.publish', $this->content))->assertOk();
    }

    expect(Revision::count())->toBe(3);
    expect(Revision::orderBy('id')->first()->id)->toBe(3);
});

it('lists revisions latest first without documents', function () {
    ($this->saveDraft)('A');
    $this->postJson(route('trame.api.contents.publish', $this->content))->assertOk();

    $this->getJson(route('trame.api.revisions.index', $this->content))
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('data.0.kind', 'publish')
        ->assertJsonPath('data.1.kind', 'save')
        ->assertJsonPath('data.0.user', 'Editor')
        ->assertJsonStructure(['data' => [['id', 'kind', 'revision', 'created_at', 'updated_at', 'user']]])
        ->assertJsonMissingPath('data.0.document');
});

it('restores a revision as the new draft', function () {
    ($this->saveDraft)('Old');
    $old = Revision::first();
    $this->travel(11)->minutes();
    ($this->saveDraft)('New');

    $this->postJson(route('trame.api.revisions.restore', [$this->content, $old->id]))
        ->assertOk()
        ->assertJsonPath('revision', 3)
        ->assertJsonPath('document.sections.0.rows.0.columns.0.blocks.0.data.text.fr', 'Old')
        ->assertJsonPath('has_unpublished_changes', true);

    expect($this->content->fresh()->revision)->toBe(3);
});

it('404s on a revision of another content', function () {
    $other = Page::query()->create(['title' => ['fr' => 'Autre'], 'slug' => ['fr' => 'autre']])->trame();
    $foreign = $other->recordRevision('save', $this->user->id);

    $this->postJson(route('trame.api.revisions.restore', [$this->content, $foreign->id]))->assertNotFound();
});

it('lets viewers list but not restore', function () {
    ($this->saveDraft)('A');
    $revision = Revision::first();

    Gate::define('trame.edit', fn ($user) => false);
    Gate::define('trame.view', fn ($user = null) => true);

    $this->getJson(route('trame.api.revisions.index', $this->content))->assertOk();
    $this->postJson(route('trame.api.revisions.restore', [$this->content, $revision->id]))->assertForbidden();
});

function trameUser2()
{
    $class = get_class(test()->user);
    $user = new $class;
    $user->forceFill(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'secret'])->save();

    return $user;
}
