{{-- Always open in the editor so the answer can be edited in place. --}}
<details class="{{ $classes }}" @if($open || $editing) open @endif>
    <summary class="flex cursor-pointer list-none items-start justify-between gap-6 font-medium [&::-webkit-details-marker]:hidden">
        <span {!! $editable('question') !!}>{{ $question }}</span>
        <svg class="mt-1 size-4 shrink-0 transition-transform group-open:rotate-45 motion-reduce:transition-none" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path d="M8 3v10M3 8h10" />
        </svg>
    </summary>
    <div class="{{ $prose }}" {!! $editable('answer', rich: true) !!}>{!! $answer !!}</div>
</details>
