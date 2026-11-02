@php($seo = $payload['seo'] ?? \App\Support\Seo::resolve($page ?? null, $site ?? null, isset($page) ? url($page->publicPath()) : null))
@php($htmlLang = \App\Support\Locales::hreflang(\App\Support\Locales::normalize($payload['locale'] ?? null)))
<!doctype html>
<html lang="{{ $htmlLang }}">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $seo['title'] }}</title>
    @if(!empty($seo['description']))
      <meta name="description" content="{{ $seo['description'] }}" />
    @endif
    <meta name="robots" content="{{ $seo['robots'] }}" />
    @include('partials.favicon')
    <link rel="canonical" href="{{ $seo['canonical'] }}" />
    @foreach($seo['hreflang'] ?? [] as $alternate)
      <link rel="alternate" hreflang="{{ $alternate['hreflang'] }}" href="{{ $alternate['href'] }}" />
    @endforeach
    <meta property="og:type" content="website" />
    <meta property="og:locale" content="{{ $seo['og_locale'] }}" />
    @foreach($seo['og_locale_alternates'] ?? [] as $ogAlternate)
      <meta property="og:locale:alternate" content="{{ $ogAlternate }}" />
    @endforeach
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
    @if(!empty($payload['jsonld']))
      <script type="application/ld+json">{!! json_encode($payload['jsonld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endif
    @if(!empty($cmsPreview))
      @viteReactRefresh
      @vite(['resources/css/site.css', 'resources/js/site/main.tsx'])
    @else
      @vite(['resources/css/site.css', 'resources/js/site/behaviors.js'])
    @endif
    {!! consent_tags($site?->tag_header) !!}
  </head>
  <body>
    {!! consent_tags($site?->tag_body) !!}
    @if(!empty($cmsPreview))
      <div id="site-root"></div>
      <script>
        @php($previewBoot = ['page' => $payload, 'site' => $sitePayload, 'preview' => true, 'draftUrl' => $previewDraftUrl ?? null, 'og' => $ogCard ?? null])
        window.__SITE__ = @json($previewBoot);
      </script>
    @else
      {!! $fragment ?? '' !!}
    @endif
    @if(empty($cmsPreview))
      <script type="text/plain" data-consent="analytics" data-src="{{ asset('js/t.js') }}"></script>
    @endif
    {!! consent_tags($site?->tag_footer) !!}
  </body>
</html>
