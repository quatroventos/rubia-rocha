<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}" />
    <link rel="stylesheet" href="{{ asset('rubia/rubia.css') }}" />
  </head>
  <body>
    {!! $fragment !!}
    <script src="{{ asset('rubia/behaviors.js') }}" defer></script>
  </body>
</html>
