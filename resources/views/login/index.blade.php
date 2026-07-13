@extends('layout.main-login')

@section('content')

<div class="grid lg:grid-cols-2 grow">
    <div class="flex justify-center items-center p-8 lg:p-10 order-2 lg:order-1">
        <div class="kt-card max-w-[370px] w-full">
            <form action="{{ route('login.execute') }}" class="kt-card-content flex flex-col gap-5 p-10" id="sign_in_form" method="post">
                @csrf
                <div class="text-center mb-2.5">
                    <h3 class="text-lg font-medium text-mono leading-none mb-2.5">Entrar</h3>
                    <div class="flex items-center justify-center font-medium">
                        <span class="text-sm text-secondary-foreground me-1.5">Ainda não tem uma conta?</span>
                        <a class="text-sm link" href="{{ route('register.index') }}">Cadastre-se</a>
                    </div>
                </div>
                <div class="flex flex-col gap-2.5">
                    <a class="kt-btn kt-btn-outline justify-center" href="{{ route('login.social.redirect', 'google') }}">
                        <img alt="" class="size-3.5 shrink-0" src="assets/media/brand-logos/google.svg" />
                        Entrar com Google
                    </a>
                    <a class="kt-btn kt-btn-outline justify-center" href="{{ route('login.social.redirect', 'facebook') }}">
                        <img alt="" class="size-3.5 shrink-0" src="assets/media/brand-logos/facebook.svg" />
                        Entrar com Facebook
                    </a>
                    <a class="kt-btn kt-btn-outline justify-center" href="{{ route('login.social.redirect', 'apple') }}">
                        <img alt="" class="size-3.5 shrink-0 dark:hidden" src="assets/media/brand-logos/apple-black.svg" />
                        <img alt="" class="size-3.5 shrink-0 light:hidden" src="assets/media/brand-logos/apple-white.svg" />
                        Entrar com Apple
                    </a>
                </div>
                <div class="flex items-center gap-2">
                    <span class="border-t border-border w-full"></span>
                    <span class="text-xs text-muted-foreground font-medium uppercase">Ou</span>
                    <span class="border-t border-border w-full"></span>
                </div>
                @error('email')
                    <div class="flex items-center gap-2 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2.5">
                        <i class="ki-filled ki-information-2 text-destructive text-sm shrink-0"></i>
                        <span class="text-xs text-destructive">{{ $message }}</span>
                    </div>
                @enderror
                <div class="flex flex-col gap-1">
                    <label class="kt-form-label font-normal text-mono">Email</label>
                    <input type="text" name="email" class="kt-input" placeholder="email@email.com" value="{{ old('email') }}" />
                </div>
                <div class="flex flex-col gap-1">
                    <div class="flex items-center justify-between gap-1">
                        <label class="kt-form-label font-normal text-mono">Senha</label>
                        <a class="text-sm kt-link shrink-0" href="{{ route('password.request') }}">Esqueceu a senha?</a>
                    </div>
                    <div class="kt-input" data-kt-toggle-password="true">
                        <input type="password" name="password" placeholder="Digite sua senha"/>
                        <button class="kt-btn kt-btn-sm kt-btn-ghost kt-btn-icon bg-transparent! -me-1.5" data-kt-toggle-password-trigger="true" type="button">
                            <span class="kt-toggle-password-active:hidden">
                                <i class="ki-filled ki-eye text-muted-foreground"></i>
                            </span>
                            <span class="hidden kt-toggle-password-active:block">
                                <i class="ki-filled ki-eye-slash text-muted-foreground"></i>
                            </span>
                        </button>
                    </div>
                </div>
                <label class="kt-label">
                    <input class="kt-checkbox kt-checkbox-sm" name="check" type="checkbox" value="1" />
                    <span class="kt-checkbox-label">Lembrar de mim</span>
                </label>
                <button class="kt-btn kt-btn-primary flex justify-center grow">Entrar</button>
            </form>
        </div>
    </div>
    <div class="lg:rounded-xl lg:border lg:border-border lg:m-5 order-1 lg:order-2 bg-top xxl:bg-center xl:bg-cover bg-no-repeat branded-bg">
        <div class="flex flex-col p-8 lg:p-16 gap-4">
            <a href="{{ route('dashboard.index') }}">
                <img class="h-[28px] max-w-none" src="assets/media/app/mini-logo.svg" />
            </a>
            <div class="flex flex-col gap-3">
                <h3 class="text-2xl font-semibold text-mono">Portal de Acesso Seguro</h3>
                <div class="text-base font-medium text-secondary-foreground">
                    Controle seus gastos importando suas <br />
                    notas fiscais e acompanhe
                    <span class="text-mono font-semibold">tudo em um só lugar</span>
                    de forma <br />
                    simples e segura.
                </div>
            </div>
        </div>
    </div>
</div>

@endsection