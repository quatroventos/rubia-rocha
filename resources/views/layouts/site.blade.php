<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    @php($seo = \App\Support\Seo::resolve($page ?? null, $site ?? null))
    <title>{{ $seo['title'] }}</title>
    @if(!empty($seo['description']))
      <meta name="description" content="{{ $seo['description'] }}" />
    @endif
    <meta name="robots" content="{{ $seo['robots'] }}" />
    @include('partials.favicon')
    <link rel="canonical" href="{{ $seo['canonical'] }}" />
    <meta property="og:type" content="website" />
    <meta property="og:locale" content="{{ $seo['og_locale'] }}" />
    <meta property="og:site_name" content="{{ $seo['og_site_name'] }}" />
    <meta property="og:title" content="{{ $seo['title'] }}" />
    @if(!empty($seo['description']))
      <meta property="og:description" content="{{ $seo['description'] }}" />
    @endif
    <meta property="og:url" content="{{ $seo['canonical'] }}" />
    @if(!empty($seo['og_image']))
      <meta property="og:image" content="{{ media($seo['og_image']) }}" />
      @if(!empty($seo['og_image_alt']))
        <meta property="og:image:alt" content="{{ $seo['og_image_alt'] }}" />
      @endif
    @endif
    <meta name="twitter:card" content="{{ $seo['twitter_card'] }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/site-base.css') }}" />
    @foreach(($cssBundles ?? []) as $bundle)
      <link rel="stylesheet" href="{{ asset('css/'.$bundle.'.css') }}" />
    @endforeach
    <link rel="stylesheet" href="{{ asset('css/footer-compliance.css') }}" />
    @if(request()->boolean('cms_preview') || !empty($cmsPreview))
      <style>
        .reveal,
        .reveal--left,
        .reveal--right,
        .reveal--scale {
          opacity: 1 !important;
          transform: none !important;
          animation: none !important;
        }
      </style>
    @endif
    {!! consent_tags($site?->tag_header) !!}
  </head>
  <body>
    {!! consent_tags($site?->tag_body) !!}
    @include('partials.header')
    <main>
      @yield('content')
    </main>
    @include('partials.footer')
    <script src="{{ asset('js/site.js') }}"></script>
    @foreach(($jsBundles ?? []) as $bundle)
      <script src="{{ asset('js/'.$bundle.'.js') }}"></script>
    @endforeach
    <script src="{{ asset('js/reveal.js') }}"></script>
    @if(empty($cmsPreview) && !request()->boolean('cms_preview'))
      <script type="text/plain" data-consent="analytics" data-src="{{ asset('js/t.js') }}"></script>
    @endif
    {!! consent_tags($site?->tag_footer) !!}
  </body>
</html>
