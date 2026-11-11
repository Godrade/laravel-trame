<section @if($anchor) id="{{ $anchor }}" @endif class="{{ $section }}" @if($style) style="{{ $style }}" @endif {!! $attributes !!}>
    <div class="{{ $container }}">{!! $rows !!}</div>
</section>
