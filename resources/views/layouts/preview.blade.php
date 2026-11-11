<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Trame preview</title>
    @if(! empty($vite))
        @vite($vite)
    @endif
</head>
<body class="antialiased">
    {!! $content !!}

    {{-- Editor bridge (selection, drag & drop). Keep this line if you publish/override the layout. --}}
    {!! $bridge ?? '' !!}
</body>
</html>
