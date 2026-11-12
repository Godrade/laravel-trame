@if(count($images))
    @php($gallerySizes = \Godrade\Trame\Media\Sizes::string(\Godrade\Trame\Media\Sizes::divide($vw ?? ['base' => 100], $columnCounts)))
    <div class="grid gap-4 {{ $columns }}">
        @foreach($images as $image)
            <figure>
                <img
                    src="{{ $image['media']->url }}"
                    @if($image['media']->srcset) srcset="{{ $image['media']->srcset }}" sizes="{{ $gallerySizes }}" @endif
                    alt="{{ $image['alt'] }}"
                    @if($image['media']->width && $image['media']->height) width="{{ $image['media']->width }}" height="{{ $image['media']->height }}" @endif
                    loading="lazy"
                    decoding="async"
                    class="{{ $imageClasses }}"
                >
                @if($image['caption'])
                    <figcaption class="mt-2 text-sm opacity-70">{{ $image['caption'] }}</figcaption>
                @endif
            </figure>
        @endforeach
    </div>
@elseif($editing)
    <div class="flex aspect-video w-full items-center justify-center border border-dashed border-current/30 text-sm opacity-60">
        {{ __('trame::blocks.gallery_placeholder') }}
    </div>
@endif
