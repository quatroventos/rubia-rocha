@extends('layouts.site')

@section('content')
@php
  $list = $sections ?? [];
  $total = count($list);
  $index = 0;
@endphp
@while($index < $total)
  @php
    $section = $list[$index];
    $next = $list[$index + 1] ?? null;
    $pair = ($section['type'] ?? null) === 'contact.channels'
      && ($next['type'] ?? null) === 'contact.form';
  @endphp
  @if($pair)
    @include('sections.contact.channels-form', [
      'channelsContent' => $section['view_content'],
      'formContent' => $next['view_content'],
    ])
    @php $index += 2; @endphp
  @else
    @include('sections.'.str_replace('.', '/', $section['type']), ['c' => $section['view_content']])
    @php $index += 1; @endphp
  @endif
@endwhile
@endsection
