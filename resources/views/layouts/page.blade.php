<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    @hasSection('description')
        <meta name="description" content="@yield('description')">
    @endif
    @if(! empty(config('trame.editor.preview.vite')))
        @vite(config('trame.editor.preview.vite'))
    @endif
</head>
<body class="antialiased">
    @yield('content')
</body>
</html>
