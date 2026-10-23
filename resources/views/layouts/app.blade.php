<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-environment" content="{{ app()->environment() }}">
    <title>@yield('title', 'Dapur Rapi') · Dapur Rapi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
    @php
        $vite = app(\Illuminate\Foundation\Vite::class);

        if (
            str_ends_with(request()->getHost(), '.trycloudflare.com')
            || request()->headers->has('CF-Ray')
            || request()->headers->has('CF-Connecting-IP')
        ) {
            $vite->useHotFile(storage_path('app/private/vite-tunnel.hot'))
                ->createAssetPathsUsing(fn (string $path): string => '/'.$path);
        }
    @endphp
    {{ $vite(['resources/css/app.css', 'resources/js/app.js']) }}
</head>
<body class="@yield('body-class')">
    @yield('content')
</body>
</html>
