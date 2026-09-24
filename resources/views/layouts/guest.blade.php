<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        :root {
            --brand-primary: #10b981;
            --bg-dark: #020617;
        }
        body {
            font-family: 'IBM Plex Sans Arabic', 'Outfit', sans-serif;
            background: var(--bg-dark);
            color: #f1f5f9;
            margin: 0;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }
        .glass-card {
            background: rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.35);
        }
        .gradient-text {
            background: linear-gradient(135deg, #fff 10%, #34d399 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        ::selection {
            background: rgba(16, 185, 129, 0.35);
            color: #fff;
        }
    </style>
</head>
<body class="min-h-full antialiased">
    @yield('content')
</body>
</html>
