@php
  $faviconHref = filled($site?->favicon_path ?? null)
      ? media($site->favicon_path)
      : asset('favicon.ico');
  $path = strtolower((string) (parse_url($faviconHref, PHP_URL_PATH) ?: ''));
  $faviconType = match (true) {
      str_ends_with($path, '.svg') => 'image/svg+xml',
      str_ends_with($path, '.png') => 'image/png',
      str_ends_with($path, '.ico') => 'image/x-icon',
      default => null,
  };
@endphp
<link rel="icon" href="{{ $faviconHref }}"@if($faviconType) type="{{ $faviconType }}"@endif />
<link rel="apple-touch-icon" href="{{ $faviconHref }}" />
