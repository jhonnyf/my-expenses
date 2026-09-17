@extends('layout.main-login')

@section('content')

<div class="grid lg:grid-cols-2 grow">
    <div class="flex justify-center items-center p-8 lg:p-10 order-2 lg:order-1">
        <div class="kt-card max-w-[420px] w-full">
            <div class="kt-card-content flex flex-col gap-5 p-10">
                <div class="text-center mb-2.5">
                    <i class="ki-filled ki-shield-tick text-4xl text-primary mb-2.5"></i>
                    <h3 class="text-lg font-medium text-mono leading-none mb-2.5">Atualizamos nossos Termos</h3>
                    <p class="text-sm text-secondary-foreground">
                        Revisamos os
                        <a href="{{ route('legal.terms') }}" target="_blank" class="text-primary hover:underline">Termos de Uso</a>
                        e a
                        <a href="{{ route('legal.privacy') }}" target="_blank" class="text-primary hover:underline">Política de Privacidade</a>.
                        Para continuar usando o {{ env('APP_NAME') }}, confirme que você leu e concorda com a versão atual.
                    </p>
                </div>

                <form action="{{ route('terms.accept.store') }}" method="post">
                    @csrf
                    <button class="kt-btn kt-btn-primary flex justify-center grow w-full">Li e aceito os termos atualizados</button>
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
                <img class="h-[28px] max-w-none" src="assets/media/app/mini-logo.png" />
            </a>
            <div class="flex flex-col gap-3">
                <h3 class="text-2xl font-semibold text-mono">Sua privacidade importa</h3>
                <div class="text-base font-medium text-secondary-foreground">
                    Deixamos mais claro como <br />
                    tratamos seus
                    <span class="text-mono font-semibold">dados pessoais</span>
                    e quais são <br />
                    os seus direitos.
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
