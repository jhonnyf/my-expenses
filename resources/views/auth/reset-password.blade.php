@extends('layout.main-login')

@section('content')

<div class="grid lg:grid-cols-2 grow">
    <div class="flex justify-center items-center p-8 lg:p-10 order-2 lg:order-1">
        <div class="kt-card max-w-[370px] w-full">
            <form action="{{ route('password.update') }}" class="kt-card-content flex flex-col gap-5 p-10" method="post">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}" />
                <input type="hidden" name="email" value="{{ $email }}" />

                <div class="text-center mb-2.5">
                    <h3 class="text-lg font-medium text-mono leading-none mb-2.5">Redefinir Senha</h3>
                    <div class="flex items-center justify-center font-medium">
                        <span class="text-sm text-secondary-foreground me-1.5">Voltar para</span>
                        <a class="text-sm link" href="{{ route('login.index') }}">Entrar</a>
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="kt-form-label font-normal text-mono">Email</label>
                    <input type="email" class="kt-input bg-muted" value="{{ $email }}" readonly />
                </div>

                <div class="flex flex-col gap-1">
                    <label class="kt-form-label font-normal text-mono">Nova Senha</label>
                    <div class="kt-input @error('password') border-destructive @enderror" data-kt-toggle-password="true">
                        <input type="password" name="password" placeholder="Digite a nova senha" />
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
                        <input type="password" name="password_confirmation" placeholder="Confirme a nova senha" />
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

                @error('email')
                    <span class="text-xs text-destructive">{{ $message }}</span>
                @enderror

                <button class="kt-btn kt-btn-primary flex justify-center grow">Redefinir Senha</button>
            </form>
        </div>
    </div>
    <div class="lg:rounded-xl lg:border lg:border-border lg:m-5 order-1 lg:order-2 bg-top xxl:bg-center xl:bg-cover bg-no-repeat branded-bg">
        <div class="flex flex-col p-8 lg:p-16 gap-4">
            <a href="{{ route('login.index') }}">
                <img class="h-[28px] max-w-none" src="assets/media/app/mini-logo.svg" />
            </a>
            <div class="flex flex-col gap-3">
                <h3 class="text-2xl font-semibold text-mono">Crie uma nova senha</h3>
                <div class="text-base font-medium text-secondary-foreground">
                    Escolha uma senha forte para manter <br />
                    sua
                    <span class="text-mono font-semibold">conta segura</span>
                    e voltar a <br />
                    controlar seus gastos.
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
