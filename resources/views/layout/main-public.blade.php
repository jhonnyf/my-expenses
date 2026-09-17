<!DOCTYPE html>
<html class="h-full" data-kt-theme="true" data-kt-theme-mode="light" dir="ltr" lang="pt-BR">
<head>
    <title>@yield('page-title') · {{ env('APP_NAME') }}</title>
    <meta charset="utf-8" />
    <meta content="noindex, nofollow" name="robots" />
    <link href="{{ url()->current() }}" rel="canonical" />
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport" />
    <link href="{{ asset('assets/media/app/apple-touch-icon.png') }}" rel="apple-touch-icon" sizes="180x180" />
    <link href="{{ asset('assets/media/app/favicon-32x32.png') }}" rel="icon" sizes="32x32" type="image/png" />
    <link href="{{ asset('assets/media/app/favicon-16x16.png') }}" rel="icon" sizes="16x16" type="image/png" />
    <link href="{{ asset('assets/media/app/favicon.ico') }}" rel="shortcut icon" />
    <meta content="#4B7672" name="theme-color" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="{{ asset('assets/vendors/keenicons/styles.bundle.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/styles.css') }}" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="antialiased flex flex-col h-full text-base text-foreground bg-background" data-app-env="{{ app()->environment() }}">
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

    <header class="kt-container-fixed py-5">
        <a href="{{ route('dashboard.index') }}">
            <img class="h-[28px] max-w-none" src="{{ asset('assets/media/app/mini-logo.png') }}" />
        </a>
    </header>

    <main class="kt-container-fixed grow py-5">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-2xl font-semibold text-mono mb-8">@yield('page-title')</h1>

            <div class="flex flex-col gap-6 text-sm text-secondary-foreground leading-relaxed [&_h2]:text-base [&_h2]:font-semibold [&_h2]:text-mono [&_h2]:mb-2 [&_p]:mb-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:mb-2 [&_strong]:text-mono">
                @yield('content')
            </div>
        </div>
    </main>

    <footer class="kt-container-fixed py-5">
        <div class="flex justify-center md:justify-start">
            @auth
                <a class="text-sm link" href="{{ route('dashboard.index') }}">&larr; Voltar</a>
            @else
                <a class="text-sm link" href="{{ route('login.index') }}">&larr; Voltar</a>
            @endauth
        </div>
    </footer>

    <script src="{{ asset('assets/js/core.bundle.js') }}"></script>
    <script src="{{ asset('assets/vendors/ktui/ktui.min.js') }}"></script>
</body>
</html>
