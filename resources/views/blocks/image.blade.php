@if($media)
    <img
        src="{{ $media->url }}"
        alt="{{ $alt ?? '' }}"
        @if($media->width && $media->height) width="{{ $media->width }}" height="{{ $media->height }}" @endif
        loading="lazy"
        decoding="async"
        class="{{ $classes }}"
    >
@elseif($editing)
    <div class="flex aspect-video w-full items-center justify-center rounded-md border-2 border-dashed border-slate-300 text-sm text-slate-400">
        {{ __('trame::blocks.image_placeholder') }}
    </div>
@endif
