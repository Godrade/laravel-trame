<aside class="{{ $classes }}" role="note">
    @if($title)
        <p class="mb-2 font-semibold" {!! $editable('title') !!}>{{ $title }}</p>
    @endif
    <div class="{{ $prose }}" {!! $editable('content', rich: true) !!}>{!! $html !!}</div>
</aside>
