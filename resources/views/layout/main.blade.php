<!DOCTYPE html>
<html class="h-full" data-kt-theme="true" data-kt-theme-mode="light" dir="ltr" lang="en">
<head>
    <base href="../../">
    <title>{{ env('APP_NAME') }}</title>
    <meta charset="utf-8" />
    <meta content="follow, index" name="robots" />
    <link href="{{ url()->current() }}" rel="canonical" />
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport" />
    <meta content="" name="description" />
    <meta content="@keenthemes" name="twitter:site" />
    <meta content="@keenthemes" name="twitter:creator" />
    <meta content="summary_large_image" name="twitter:card" />
    <meta content="{{ env('APP_NAME') }}" name="twitter:title" />
    <meta content="" name="twitter:description" />
    <meta content="{{ asset('assets/media/app/og-image.png') }}" name="twitter:image" />
    <meta content="{{ url()->current() }}" property="og:url" />
    <meta content="en_US" property="og:locale" />
    <meta content="website" property="og:type" />
    <meta content="@keenthemes" property="og:site_name" />
    <meta content="{{ env('APP_NAME') }}" property="og:title" />
    <meta content="" property="og:description" />
    <meta content="{{ asset('assets/media/app/og-image.png') }}" property="og:image" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="{{ asset('assets/media/app/apple-touch-icon.png') }}" rel="apple-touch-icon" sizes="180x180" />
    <link href="{{ asset('assets/media/app/favicon-32x32.png') }}" rel="icon" sizes="32x32" type="image/png" />
    <link href="{{ asset('assets/media/app/favicon-16x16.png') }}" rel="icon" sizes="16x16" type="image/png" />
    <link href="{{ asset('assets/media/app/favicon.ico') }}" rel="shortcut icon" />
    <link href="{{ asset('assets/vendors/apexcharts/apexcharts.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/vendors/keenicons/styles.bundle.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/styles.css') }}" rel="stylesheet" />
</head>

<body class="antialiased flex h-full text-base text-foreground bg-background demo1 kt-sidebar-fixed kt-header-fixed">
    <!-- Theme Mode -->
    <script>
        const defaultThemeMode = 'light'; // light|dark|system
        let themeMode;

        if (document.documentElement) {
            if (localStorage.getItem('kt-theme')) {
                themeMode = localStorage.getItem('kt-theme');
            } else if (document.documentElement.hasAttribute('data-kt-theme-mode')) {
                themeMode =document.documentElement.getAttribute('data-kt-theme-mode');
            } else {
                themeMode = defaultThemeMode;
            }

            if (themeMode === 'system') {
                themeMode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.classList.add(themeMode);
        }
    </script>
    
    <div class="flex grow">
        @include('layout.sidebar')
        <div class="kt-wrapper flex grow flex-col">
            <header class="kt-header fixed top-0 z-10 start-0 end-0 flex items-stretch shrink-0 bg-background" data-kt-sticky="true" data-kt-sticky-class="border-b border-border" data-kt-sticky-name="header" id="header">
                <div class="kt-container-fixed flex justify-between items-stretch lg:gap-4" id="headerContainer">
                    <!-- Mobile Logo -->
                    <div class="flex gap-2.5 lg:hidden items-center -ms-1">
                        <a class="shrink-0" href="html/demo1.html">
                            <img class="max-h-[25px] w-full" src="assets/media/app/mini-logo.svg" />
                        </a>
                        <div class="flex items-center">
                            <button class="kt-btn kt-btn-icon kt-btn-ghost" data-kt-drawer-toggle="#sidebar">
                                <i class="ki-filled ki-menu">
                                </i>
                            </button>
                            <button class="kt-btn kt-btn-icon kt-btn-ghost" data-kt-drawer-toggle="#mega_menu_wrapper">
                                <i class="ki-filled ki-burger-menu-2">
                                </i>
                            </button>
                        </div>
                    </div>
                    <!-- End of Mobile Logo -->
                    
                    <div class="flex items-stretch" id="megaMenuContainer"></div>
                    
                    <!-- Topbar -->
                    <div class="flex items-center gap-2.5">
                        <!-- Global Search -->
                        <div class="relative hidden lg:block" id="globalSearchWrapper">
                            <label class="kt-input w-[280px]">
                                <i class="ki-filled ki-magnifier"></i>
                                <input type="text" id="globalSearchInput" placeholder="Buscar..." autocomplete="off" />
                            </label>
                            <div id="globalSearchResults" class="hidden absolute top-full end-0 mt-2 w-[400px] bg-background border border-border rounded-lg shadow-lg z-50 max-h-[500px] overflow-y-auto">
                            </div>
                        </div>
                        <!-- End of Global Search -->
                        <!-- User -->
                        <div class="shrink-0" data-kt-dropdown="true" data-kt-dropdown-offset="10px, 10px" data-kt-dropdown-offset-rtl="-20px, 10px" data-kt-dropdown-placement="bottom-end" data-kt-dropdown-placement-rtl="bottom-start" data-kt-dropdown-trigger="click">
                            <div class="cursor-pointer shrink-0" data-kt-dropdown-toggle="true">
                                <img alt="" class="size-9 rounded-full border-2 border-green-500 shrink-0" src="{{ asset('assets/media/avatars/300-2.png') }}" />
                            </div>
                            <div class="kt-dropdown-menu w-[250px]" data-kt-dropdown-menu="true">
                                <div class="flex items-center justify-between px-2.5 py-1.5 gap-1.5">
                                    <div class="flex items-center gap-2">
                                        <img alt="" class="size-9 shrink-0 rounded-full border-2 border-green-500" src="{{ asset('assets/media/avatars/300-2.png') }}" />
                                        <div class="flex flex-col gap-1.5">
                                            <span class="text-sm text-foreground font-semibold leading-none">{{ Auth::user()->name }}</span>
                                            <a class="text-xs text-secondary-foreground hover:text-primary font-medium leading-none" href="html/demo1/account/home/get-started.html">
                                                {{ Auth::user()->email }}
                                            </a>
                                        </div>
                                    </div>                                    
                                </div>
                                <ul class="kt-dropdown-menu-sub">
                                    <li>
                                        <div class="kt-dropdown-menu-separator"></div>
                                    </li>
                                    <li>
                                        <a class="kt-dropdown-menu-link" href="#">
                                            <i class="ki-filled ki-profile-circle"></i> Meu perfil
                                        </a>
                                    </li>
                                    <li>
                                        <a class="kt-dropdown-menu-link" href="#">
                                            <i class="ki-filled ki-icon"></i> Billing                                                        
                                        </a>
                                    </li>
                                    <li>
                                        <div class="kt-dropdown-menu-separator"></div>
                                    </li>
                                </ul>
                                <div class="px-2.5 pt-1.5 mb-2.5 flex flex-col gap-3.5">
                                    <div class="flex items-center gap-2 justify-between">
                                        <span class="flex items-center gap-2">
                                            <i class="ki-filled ki-moon text-base text-muted-foreground"></i>
                                            <span class="font-medium text-2sm">Dark Mode</span>
                                        </span>
                                        <input class="kt-switch" data-kt-theme-switch-state="dark" data-kt-theme-switch-toggle="true" name="check" type="checkbox" value="1" />
                                    </div>
                                    <a class="kt-btn kt-btn-outline justify-center w-full" href="{{ route('login.logout') }}">Sair</a>
                                </div>
                            </div>
                        </div>
                        <!-- End of User -->
                    </div>
                    <!-- End of Topbar -->
                </div>
            </header>            
            <main class="grow pt-5" id="content" role="content">
                @yield('content')
            </main>  
            @include('layout.footer')          
        </div>
    </div>
        

    <script src="{{ asset('assets/js/core.bundle.js') }}"></script>
    <script src="{{ asset('assets/vendors/ktui/ktui.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/apexcharts/apexcharts.min.js') }}"></script>
    <script src="{{ asset('assets/js/widgets/general.js') }}"></script>
    <script src="{{ asset('assets/js/layouts/demo1.js') }}"></script>

    @auth
    <script>
    (function() {
        const gsInput = document.getElementById('globalSearchInput');
        const gsResults = document.getElementById('globalSearchResults');
        if (!gsInput || !gsResults) return;

        let gsTimeout = null;
        const gsToken = '{{ csrf_token() }}';

        gsInput.addEventListener('input', function() {
            clearTimeout(gsTimeout);
            const q = this.value.trim();
            if (q.length < 2) {
                gsResults.classList.add('hidden');
                gsResults.innerHTML = '';
                return;
            }
            gsTimeout = setTimeout(() => gsSearch(q), 300);
        });

        function gsSearch(q) {
            fetch(`{{ route('search') }}?q=${encodeURIComponent(q)}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                let html = '';
                const sections = [
                    { key: 'emissores', label: 'Emissores', icon: 'ki-filled ki-shop' },
                    { key: 'notas_fiscais', label: 'Notas Fiscais', icon: 'ki-filled ki-document' },
                    { key: 'produtos', label: 'Produtos', icon: 'ki-filled ki-basket' },
                ];

                let hasResults = false;
                sections.forEach(sec => {
                    const items = data[sec.key] || [];
                    if (items.length === 0) return;
                    hasResults = true;
                    html += `<div class="px-3 py-2 bg-accent/40 text-xs font-semibold text-secondary-foreground uppercase tracking-wide flex items-center gap-1.5">
                        <i class="${sec.icon} text-primary"></i> ${sec.label}
                    </div>`;
                    items.forEach(item => {
                        html += `<a href="${item.url}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-accent/30 transition-colors cursor-pointer">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-foreground truncate">${item.title}</p>
                                <p class="text-xs text-secondary-foreground truncate">${item.subtitle}</p>
                            </div>
                        </a>`;
                    });
                });

                if (!hasResults) {
                    html = '<div class="px-4 py-3 text-sm text-secondary-foreground">Nenhum resultado encontrado.</div>';
                }

                gsResults.innerHTML = html;
                gsResults.classList.remove('hidden');
            });
        }

        document.addEventListener('click', function(e) {
            if (!document.getElementById('globalSearchWrapper').contains(e.target)) {
                gsResults.classList.add('hidden');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                gsResults.classList.add('hidden');
                gsInput.blur();
            }
        });
    })();
    </script>
    @endauth
</body>
</html>