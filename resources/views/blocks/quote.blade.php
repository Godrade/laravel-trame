<figure class="{{ $figure }}" @if($style) style="{{ $style }}" @endif>
    <blockquote class="{{ $text }}">
        <p class="whitespace-pre-line" {!! $editable('quote') !!}>{{ $quote }}</p>
    </blockquote>
    @if($author || $source)
        <figcaption class="mt-4 text-sm">
            @if($author)<span class="font-medium" {!! $editable('author') !!}>{{ $author }}</span>@endif
            @if($source)<span class="block opacity-70" {!! $editable('source') !!}>{{ $source }}</span>@endif
        </figcaption>
    @endif
</figure>
