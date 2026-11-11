@extends(config('trame.pages.layout', 'trame::layouts.page'))

@section('title', $title)

@if($description)
    @section('description', $description)
@endif

@section('content')
    {!! $content !!}
@endsection
