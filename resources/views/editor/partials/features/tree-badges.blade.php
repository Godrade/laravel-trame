{{-- Feature slot (structure tree item): checks markers. Variable: item. --}}
<span x-show="isUntranslated(item.id)" class="ms-auto shrink-0 rounded bg-amber-100 px-1 text-[10px] font-semibold uppercase text-amber-800" :title="t.check_untranslated" x-text="locale"></span>
<span x-show="hasIssue(item.id)" class="shrink-0 rounded bg-orange-100 px-1 text-[10px] font-bold text-orange-700" :class="isUntranslated(item.id) ? '' : 'ms-auto'" :title="t.check_issue" aria-hidden="true">!</span>
