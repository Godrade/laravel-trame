<aside class="{{ $classes }}" role="note">
    <svg class="{{ $icon }}" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
        @if($tone === 'warning')
            <path d="M10 3.5 2.5 16.5h15L10 3.5Z" stroke-linejoin="round" /><path d="M10 8.5v3.5M10 14.25v.25" />
        @elseif($tone === 'success')
            <circle cx="10" cy="10" r="7.25" /><path d="m6.75 10.25 2.25 2.25 4.25-4.5" stroke-linejoin="round" />
        @else
            <circle cx="10" cy="10" r="7.25" /><path d="M10 9v4.75M10 6.25v.25" />
        @endif
    </svg>
    <div class="min-w-0 flex-1">
        @if($title)
            <p class="mb-1 font-semibold" {!! $editable('title') !!}>{{ $title }}</p>
        @endif
        <div class="{{ $prose }}" {!! $editable('content', rich: true) !!}>{!! $html !!}</div>
    </div>
</aside>
