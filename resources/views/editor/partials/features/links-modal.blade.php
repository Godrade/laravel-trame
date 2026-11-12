{{-- Internal link picker (feature "links"): pages that a url field can point to. --}}
<div x-show="linkPicker.open" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/40 p-6"
     @click.self="linkPicker.open = false" @keydown.escape.window="linkPicker.open = false">
    <div class="flex max-h-full w-full max-w-lg flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
        <div class="flex items-center gap-3 border-b border-zinc-200 px-4 py-3">
            <h2 class="me-auto text-sm font-semibold" x-text="t.links_title"></h2>
            <button type="button" class="tr-icon-btn" @click="linkPicker.open = false" :title="t.close">✕</button>
        </div>
        <div class="border-b border-zinc-200 p-3">
            <input type="search" class="tr-input" x-model="linkPicker.search" @input="searchLinks()" :placeholder="t.links_search"
                   x-effect="linkPicker.open && $nextTick(() => $el.focus())">
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto p-2">
            <p x-show="linkPicker.loading && !linkPicker.items.length" class="py-8 text-center text-sm text-zinc-500" x-text="t.links_loading"></p>
            <p x-show="!linkPicker.loading && !linkPicker.items.length" class="py-8 text-center text-sm text-zinc-500" x-text="t.links_none"></p>
            <ul class="space-y-1">
                <template x-for="item in linkPicker.items" :key="item.ref">
                    <li>
                        <button type="button" @click="pickLink(item)" class="block w-full rounded-md px-3 py-2 text-start hover:bg-indigo-50">
                            <span class="block truncate text-sm font-medium text-zinc-800" x-text="item.label"></span>
                            <span class="block truncate text-xs text-zinc-500" x-text="item.url"></span>
                        </button>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>
