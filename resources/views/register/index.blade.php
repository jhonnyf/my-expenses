@extends('layout.main-login')

@section('content')

<div class="grid lg:grid-cols-2 grow">
    <div class="flex justify-center items-center p-8 lg:p-10 order-2 lg:order-1">
        <div class="kt-card max-w-[370px] w-full">
            <form action="{{ route('register.store') }}" class="kt-card-content flex flex-col gap-5 p-10" id="sign_up_form" method="post">
                @csrf
                <div class="text-center mb-2.5">
                    <h3 class="text-lg font-medium text-mono leading-none mb-2.5">Criar conta</h3>
                    <div class="flex items-center justify-center font-medium">
                        <span class="text-sm text-secondary-foreground me-1.5">Já tem uma conta?</span>
                        <a class="text-sm link" href="{{ route('login.index') }}">Entrar</a>
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
                <div class="flex flex-col gap-1">
                    <label class="kt-form-label font-normal text-mono">Nome</label>
                    <input type="text" name="name" class="kt-input @error('name') border-destructive @enderror" placeholder="Seu nome completo" value="{{ old('name') }}" />
                    @error('name')
                        <span class="text-xs text-destructive">{{ $message }}</span>
                    @enderror
                </div>
                <div class="flex flex-col gap-1">
                    <label class="kt-form-label font-normal text-mono">Email</label>
                    <input type="text" name="email" class="kt-input @error('email') border-destructive @enderror" placeholder="email@email.com" value="{{ old('email') }}" />
                    @error('email')
                        <span class="text-xs text-destructive">{{ $message }}</span>
                    @enderror
                </div>
                <div class="flex flex-col gap-1">
                    <label class="kt-form-label font-normal text-mono">Senha</label>
                    <div class="kt-input @error('password') border-destructive @enderror" data-kt-toggle-password="true">
                        <input type="password" name="password" placeholder="Digite sua senha" />
                        <button class="kt-btn kt-btn-sm kt-btn-ghost kt-btn-icon bg-transparent! -me-1.5" data-kt-toggle-password-trigger="true" type="button">
                            <span class="kt-toggle-password-active:hidden">
                                <i class="ki-filled ki-eye text-muted-foreground"></i>
                            </span>
                            <span class="hidden kt-toggle-password-active:block">
                                <i class="ki-filled ki-eye-slash text-muted-foreground"></i>
                            </span>
                        </button>
                    </div>
                    @error('password')
                        <span class="text-xs text-destructive">{{ $message }}</span>
                    @enderror
                </div>
                <div class="flex flex-col gap-1">
                    <label class="kt-form-label font-normal text-mono">Confirmar Senha</label>
                    <div class="kt-input" data-kt-toggle-password="true">
                        <input type="password" name="password_confirmation" placeholder="Repita a senha" />
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
                <button class="kt-btn kt-btn-primary flex justify-center grow">Criar Conta</button>
            </form>
        </div>
    </div>
    <div class="lg:rounded-xl lg:border lg:border-border lg:m-5 order-1 lg:order-2 bg-top xxl:bg-center xl:bg-cover bg-no-repeat branded-bg">
        <div class="flex flex-col p-8 lg:p-16 gap-4">
            <a href="{{ route('login.index') }}">
                <img class="h-[28px] max-w-none" src="assets/media/app/mini-logo.svg" />
            </a>
            <div class="flex flex-col gap-3">
                <h3 class="text-2xl font-semibold text-mono">Comece a controlar seus gastos</h3>
                <div class="text-base font-medium text-secondary-foreground">
                    Crie sua conta e comece a importar suas <br />
                    <span class="text-mono font-semibold">notas fiscais (NFC-e)</span>
                    para acompanhar cada <br />
                    compra automaticamente.
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
