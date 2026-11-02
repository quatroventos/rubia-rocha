<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title ?? 'Quatro Ventos' }}</title>
    <meta name="robots" content="noindex, nofollow" />
    @include('partials.favicon')
    @vite(['resources/css/site.css'])
  </head>
  <body>
    <main id="conteudo">
      <section class="hero not-found">
        <div class="wrap">
          <h1 class="display is-inner">{{ $heading }}</h1>
          <div class="hero-foot">
            <p class="lead">{{ $message }}</p>
          </div>
          <div class="actions">
            <a class="pill line" href="{{ url('/') }}">Voltar ao início</a>
          </div>
        </div>
      </section>
    </main>
  </body>
</html>
