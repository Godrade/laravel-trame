<div class="{{ $wrapper }}">
    <a
        href="{{ $href }}"
        class="{{ $classes }}"
        @if($style) style="{{ $style }}" @endif
        @if($new_tab) target="_blank" rel="noopener noreferrer" @endif
        {!! $editable('label') !!}
    >{{ $label }}</a>
</div>
