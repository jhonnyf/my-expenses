<!DOCTYPE html>
<html class="h-full" data-kt-theme="true" data-kt-theme-mode="light" dir="ltr" lang="pt-BR">
<head>
    <base href="../../../../../">
    <title>{{ env('APP_NAME') }}</title>
    <meta charset="utf-8" />
    <meta content="noindex, nofollow" name="robots" />
    <link href="{{ url()->current() }}" rel="canonical" />
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport" />
    <meta content="Acesse sua conta para controlar seus gastos pessoais a partir das suas notas fiscais (NFC-e)." name="description" />
    <meta content="summary_large_image" name="twitter:card" />
    <meta content="{{ env('APP_NAME') }}" name="twitter:title" />
    <meta content="Acesse sua conta para controlar seus gastos pessoais a partir das suas notas fiscais (NFC-e)." name="twitter:description" />
    <meta content="{{ asset('assets/media/app/og-image.png') }}" name="twitter:image" />
    <meta content="{{ url()->current() }}" property="og:url" />
    <meta content="pt_BR" property="og:locale" />
    <meta content="website" property="og:type" />
    <meta content="{{ env('APP_NAME') }}" property="og:site_name" />
    <meta content="{{ env('APP_NAME') }}" property="og:title" />
    <meta content="Acesse sua conta para controlar seus gastos pessoais a partir das suas notas fiscais (NFC-e)." property="og:description" />
    <meta content="{{ asset('assets/media/app/og-image.png') }}" property="og:image" />
    <link href="{{ asset('assets/media/app/apple-touch-icon.png') }}" rel="apple-touch-icon" sizes="180x180" />
    <link href="{{ asset('assets/media/app/favicon-32x32.png') }}" rel="icon" sizes="32x32" type="image/png" />
    <link href="{{ asset('assets/media/app/favicon-16x16.png') }}" rel="icon" sizes="16x16" type="image/png" />
    <link href="{{ asset('assets/media/app/favicon.ico') }}" rel="shortcut icon" />
    <link href="{{ asset('manifest.webmanifest') }}" rel="manifest" />
    <meta content="#3b82f6" name="theme-color" />
    <meta content="yes" name="mobile-web-app-capable" />
    <meta content="yes" name="apple-mobile-web-app-capable" />
    <meta content="default" name="apple-mobile-web-app-status-bar-style" />
    <meta content="{{ env('APP_NAME') }}" name="apple-mobile-web-app-title" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="{{ asset('assets/vendors/apexcharts/apexcharts.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/vendors/keenicons/styles.bundle.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/styles.css') }}" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/pwa.js'])
</head>
<body class="antialiased flex h-full text-base text-foreground bg-background">    
    <script>
        const defaultThemeMode = 'light'; // light|dark|system
		let themeMode;
        if (document.documentElement) {
            if (localStorage.getItem('kt-theme')) {
                themeMode = localStorage.getItem('kt-theme');
            } else if (document.documentElement.hasAttribute('data-kt-theme-mode')) {
                themeMode = document.documentElement.getAttribute('data-kt-theme-mode');
            } else {
                themeMode = defaultThemeMode;
            }

            if (themeMode === 'system') {
                themeMode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }

            document.documentElement.classList.add(themeMode);
        }
    </script>
    <style>
        .branded-bg {
            background-image: url('{{ asset('assets/media/images/2600x1600/1.png') }}');
        }
        .dark .branded-bg {
            background-image: url('{{ asset('assets/media/images/2600x1600/1-dark.png') }}');
        }
    </style>
    
    @yield('content')

    <script src="{{ asset('assets/js/core.bundle.js') }}"></script>
    <script src="{{ asset('assets/vendors/ktui/ktui.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/apexcharts/apexcharts.min.js') }}"></script>    
</body>
</html>