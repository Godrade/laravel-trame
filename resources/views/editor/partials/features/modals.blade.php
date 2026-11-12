{{-- Revision history --}}
<div x-show="revs.open" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/40 p-6" @click.self="closeRevisions()" @keydown.escape.window="closeRevisions()">
    <div class="flex max-h-full w-full max-w-lg flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
        <div class="flex items-center gap-3 border-b border-zinc-200 px-4 py-3">
            <h2 class="me-auto text-sm font-semibold" x-text="t.history"></h2>
            <button type="button" class="tr-btn-outline text-xs" x-show="!readOnly && publishedRevision" @click="restoreRevision(publishedRevision)" :disabled="revs.restoring" x-text="t.revert_to_published"></button>
            <button type="button" class="tr-icon-btn" @click="closeRevisions()" :title="t.close">✕</button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto">
            <p x-show="revs.loading" class="py-10 text-center text-sm text-zinc-500" x-text="t.history_loading"></p>
            <p x-show="!revs.loading && revs.failed" class="py-10 text-center text-sm text-rose-600" x-text="t.history_failed"></p>
            <p x-show="!revs.loading && !revs.failed && !revs.items.length" class="py-10 text-center text-sm text-zinc-500" x-text="t.history_empty"></p>
            <ul class="divide-y divide-zinc-100" x-show="!revs.loading">
                <template x-for="item in revs.items" :key="item.id">
                    <li class="flex items-center gap-3 px-4 py-2.5">
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-medium" x-text="revisionKind(item.kind)"></div>
                            <div class="truncate text-xs text-zinc-500">
                                <span x-text="revisionAge(item)" :title="new Date(item.updated_at).toLocaleString(document.documentElement.lang || undefined)"></span>
                                <span x-show="item.user" x-text="'· ' + item.user"></span>
                            </div>
                        </div>
                        <template x-if="!readOnly">
                            <div class="flex shrink-0 items-center gap-2">
                                <span x-show="revs.confirming === item.id" class="max-w-48 text-xs text-amber-700" x-text="t.history_unsaved_warning"></span>
                                <button type="button" class="tr-btn-outline text-xs" :disabled="revs.restoring" @click="restoreRevision(item)"
                                        x-text="revs.confirming === item.id ? t.history_confirm : t.restore"></button>
                            </div>
                        </template>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>
@include('trame::editor.partials.features.links-modal')
