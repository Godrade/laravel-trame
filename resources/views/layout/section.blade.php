<section @if($anchor) id="{{ $anchor }}" @endif class="{{ $section }}" @if($style) style="{{ $style }}" @endif {!! $attributes !!}>
    @if($background ?? null)
        <img
            src="{{ $background['url'] }}"
            @if($background['srcset']) srcset="{{ $background['srcset'] }}" sizes="{{ $background['sizes'] }}" @endif
            alt=""
            loading="lazy"
            decoding="async"
            class="absolute inset-0 -z-10 size-full object-cover {{ $background['class'] }}"
        >
        @if($background['overlay'])
            <div aria-hidden="true" class="absolute inset-0 -z-10 {{ $background['overlay']['class'] }}" @if($background['overlay']['style']) style="{{ $background['overlay']['style'] }}" @endif></div>
        @endif
    @endif
    <div class="{{ $container }}">{!! $rows !!}</div>
</section>
