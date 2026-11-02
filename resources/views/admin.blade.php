<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $branding['name'] ?? 'Quatro Ventos' }}</title>
    @php($adminFavicon = $branding['favicon'] ?? null)
    <link rel="icon" href="{{ $adminFavicon ?: asset('favicon.ico') }}" />
    @viteReactRefresh
    @vite(['resources/css/admin.css', 'resources/js/admin/main.tsx'])
</head>
<body>
    <div id="admin-root"></div>
    <script>
        window.__ADMIN__ = {
            path: @json($adminPath),
            csrf: @json(csrf_token()),
            branding: @json($branding ?? ['name' => 'Quatro Ventos', 'logo' => null, 'favicon' => null])
        };
    </script>
</body>
</html>
