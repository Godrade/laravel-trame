{{--
    $box: appearance settings (class, style). A card with a background, border or shadow
    and no explicit padding gets a comfortable one around its text; the image stays full-bleed.
--}}
<article class="flex flex-1 flex-col {{ $box['class'] }}" @if($box['style']) style="{{ $box['style'] }}" @endif>
    @if($media)
        <img
            src="{{ $media->url }}"
            alt="{{ $alt ?? '' }}"
            @if($media->width && $media->height) width="{{ $media->width }}" height="{{ $media->height }}" @endif
            loading="lazy"
            decoding="async"
            class="{{ $imageClasses }}"
        >
    @endif
    <div class="flex flex-1 flex-col @if($box['framed'] && ! $box['padded']) p-6 @elseif($media) pt-4 @endif">
        <h3 class="text-lg font-semibold leading-snug text-balance" {!! $editable('title') !!}>{{ $title }}</h3>
        @if($text)
            <p class="mt-2 leading-7 text-pretty text-current/75" {!! $editable('text') !!}>{{ $text }}</p>
        @endif
        @if($href && $link_label)
            <p class="mt-auto pt-5">
                <a href="{{ $href }}" class="font-medium underline decoration-current/30 underline-offset-4 transition-colors hover:decoration-current" {!! $editable('link_label') !!}>{{ $link_label }}</a>
            </p>
        @endif
    </div>
</article>
