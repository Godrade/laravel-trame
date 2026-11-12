{{-- Feature slot: see resources/js/editor/features/index.js --}}
<section class="space-y-2" x-show="patternList.length" x-cloak>
    <h3 class="tr-heading" x-text="t.patterns_title"></h3>
    <div class="space-y-1.5">
        <template x-for="pattern in patternList" :key="pattern.key">
            <button type="button" @click="insertPattern(pattern)"
                    class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-start transition hover:border-indigo-400 hover:bg-indigo-50/40">
                <span class="block text-sm font-medium text-zinc-800" x-text="pattern.label"></span>
                <span class="block text-xs text-zinc-500" x-text="pattern.description"></span>
            </button>
        </template>
    </div>
</section>
