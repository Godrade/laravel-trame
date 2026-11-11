@if($media)
    <figure>
        <img
            src="{{ $media->url }}"
            alt="{{ $alt ?? '' }}"
            @if($media->width && $media->height) width="{{ $media->width }}" height="{{ $media->height }}" @endif
            loading="lazy"
            decoding="async"
            class="{{ $classes }}"
        >
        @if($caption)
            <figcaption class="mt-2 text-sm opacity-70" {!! $editable('caption') !!}>{{ $caption }}</figcaption>
        @endif
    </figure>
@elseif($editing)
    <div class="flex aspect-video w-full items-center justify-center border border-dashed border-current/30 text-sm opacity-60">
        {{ __('trame::blocks.image_placeholder') }}
    </div>
@endif
