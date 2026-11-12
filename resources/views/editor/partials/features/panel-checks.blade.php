{{-- Feature slot (right panel, nothing selected): content checks. --}}
<section class="space-y-3">
    <h3 class="tr-heading" x-text="t.checks"></h3>

    <p x-show="!checkIssues.length && !untranslatedBlocks.length" class="text-xs text-zinc-500" x-text="t.checks_none"></p>

    <template x-if="untranslatedBlocks.length">
        <div class="space-y-1">
            <p class="text-xs font-medium text-amber-800" x-text="t.check_untranslated_count.replace(':count', untranslatedBlocks.length).replace(':locale', locale.toUpperCase())"></p>
            <template x-for="block in untranslatedBlocks" :key="block.id">
                <button type="button" class="block w-full truncate rounded px-2 py-1 text-start text-xs text-zinc-700 hover:bg-amber-50" @click="select(block.id, true)" x-text="block.label"></button>
            </template>
        </div>
    </template>

    <template x-if="checkIssues.length">
        <div class="space-y-1">
            <template x-for="(issue, i) in checkIssues" :key="issue.id + issue.code + i">
                <button type="button" class="flex w-full items-start gap-2 rounded px-2 py-1.5 text-start text-xs text-zinc-700 hover:bg-orange-50" @click="select(issue.id, true)">
                    <span class="mt-px shrink-0 font-bold text-orange-600" aria-hidden="true">!</span>
                    <span x-text="issue.message"></span>
                </button>
            </template>
        </div>
    </template>
</section>
