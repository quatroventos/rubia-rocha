@php($siteName = filled($site?->name ?? null) ? $site->name : 'Rubia Rocha Fotografia')
@php($logo = filled($site?->logo_path ?? null) ? media($site->logo_path) : '/rubia/img/logo-rubia-foto.svg')
@php($cssVersion = @filemtime(public_path('rubia/rubia.css')) ?: null)
@php($fragment = $fragment ?? null)
@php($hasVite = is_file(public_path('hot')) || is_file(public_path('build/manifest.json')))
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $title }} | {{ $siteName }}</title>
    <meta name="robots" content="noindex, nofollow" />
    <meta name="theme-color" content="#f6f1eb" />
    @include('partials.favicon')
    <link rel="preload" href="/rubia/fonts/playfair-400.woff2" as="font" type="font/woff2" crossorigin />
    <link rel="preload" href="/rubia/fonts/work-sans-400.woff2" as="font" type="font/woff2" crossorigin />
    <link rel="stylesheet" href="/rubia/rubia.css{{ $cssVersion ? '?v='.$cssVersion : '' }}" />
    @if($fragment !== null && $hasVite)
      @vite('resources/js/rubia/site.ts')
    @endif
  </head>
  <body>
    @if($fragment !== null)
      {!! $fragment !!}
    @else
      <div class="rr" data-page="erro">
        <header class="bar">
          <a class="logo" href="{{ url('/') }}" aria-label="{{ $siteName }}, início">
            <img src="{{ $logo }}" alt="" width="324" height="71" />
          </a>
        </header>
        <main id="conteudo">
          <section class="lost">
            @if(!empty($kicker))
              <p class="kicker">{{ $kicker }}</p>
            @endif
            <h1>{{ $heading }}</h1>
            <p>{{ $message }}</p>
            <a class="btn is-line" href="{{ url('/') }}">Voltar ao início <span aria-hidden="true">→</span></a>
          </section>
        </main>
      </div>
    @endif
  </body>
</html>
