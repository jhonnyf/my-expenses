@extends('layout.main-login')

@section('content')

<div class="grid lg:grid-cols-2 grow">
    <div class="flex justify-center items-center p-8 lg:p-10 order-2 lg:order-1">
        <div class="kt-card max-w-[370px] w-full">
            <div class="kt-card-content flex flex-col gap-5 p-10">
                <div class="text-center mb-2.5">
                    <i class="ki-filled ki-sms text-4xl text-primary mb-2.5"></i>
                    <h3 class="text-lg font-medium text-mono leading-none mb-2.5">Confirme seu e-mail</h3>
                    <p class="text-sm text-secondary-foreground">
                        Enviamos um link de confirmação para <span class="font-medium text-mono">{{ auth()->user()->email }}</span>.
                        Clique no link para ativar sua conta.
                    </p>
                </div>

                @if (session('status'))
                    <div class="kt-alert kt-alert-success flex items-center gap-2 p-3 rounded-md bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800">
                        <i class="ki-filled ki-shield-tick text-green-600 dark:text-green-400 text-base"></i>
                        <span class="text-sm text-green-700 dark:text-green-300">{{ session('status') }}</span>
                    </div>
                @endif

                <form action="{{ route('verification.send') }}" method="post">
                    @csrf
                    <button class="kt-btn kt-btn-primary flex justify-center grow w-full">Reenviar e-mail de confirmação</button>
                </form>

                <form action="{{ route('login.logout') }}" method="post">
                    @csrf
                    <button class="kt-btn kt-btn-outline flex justify-center grow w-full">Sair</button>
                </form>
            </div>
        </div>
    </div>
    <div class="lg:rounded-xl lg:border lg:border-border lg:m-5 order-1 lg:order-2 bg-top xxl:bg-center xl:bg-cover bg-no-repeat branded-bg">
        <div class="flex flex-col p-8 lg:p-16 gap-4">
            <a href="{{ route('login.index') }}">
                <img class="h-[28px] max-w-none" src="assets/media/app/mini-logo.svg" />
            </a>
            <div class="flex flex-col gap-3">
                <h3 class="text-2xl font-semibold text-mono">Quase lá!</h3>
                <div class="text-base font-medium text-secondary-foreground">
                    Confirme seu e-mail para <br />
                    garantir a
                    <span class="text-mono font-semibold">segurança da sua conta</span>
                    e começar <br />
                    a controlar seus gastos.
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
