<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'DanaVision') }}</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/images/danavision_icon.png">
    
    <!-- Prevent flash of wrong theme -->
    <script>
        (function() {
            const stored = localStorage.getItem('danavision-theme');
            const theme = stored === 'light' || stored === 'dark' ? stored : 
                (stored === 'system' || !stored) && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.classList.add(theme);
        })();
    </script>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
