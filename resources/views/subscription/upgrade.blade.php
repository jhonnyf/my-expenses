@extends('layout.main')
@section('page-module', '')

@section('content')

    <div class="kt-container-fixed">
        <div class="flex flex-col items-center text-center gap-2 py-6 lg:py-10 max-w-xl mx-auto">
            <span class="kt-badge kt-badge-success kt-badge-sm mb-2">Pro</span>
            <h1 class="text-2xl font-semibold text-mono">Desbloqueie o plano Pro</h1>

            @if(session('paywall_message'))
                <p class="text-sm text-secondary-foreground">{{ session('paywall_message') }}</p>
            @else
                <p class="text-sm text-secondary-foreground">Tenha acesso completo a todas as funcionalidades do sistema.</p>
            @endif
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="max-w-xl mx-auto">
            <div class="kt-card p-6 lg:p-7.5">
                <h3 class="kt-card-title mb-4">O que você ganha no Pro</h3>

                <ul class="flex flex-col gap-3 mb-6">
                    @foreach ([
                        'Sugestões automáticas via Inteligência Artificial (categorias e nomes de produto)',
                        'Exportação de relatórios em PDF',
                        'Comparação de preços e histórico entre lojas',
                        'Detecção de compras recorrentes e melhor loja',
                        'Múltiplos orçamentos por categoria',
                    ] as $benefit)
                        <li class="flex items-start gap-2.5">
                            <i class="ki-filled ki-check-circle text-green-600 text-base shrink-0 mt-0.5"></i>
                            <span class="text-sm text-foreground">{{ $benefit }}</span>
                        </li>
                    @endforeach
                </ul>

                <button type="button" class="kt-btn kt-btn-primary w-full" disabled>
                    Assinar Pro (em breve)
                </button>
                <p class="text-xs text-secondary-foreground text-center mt-3">
                    O pagamento online ainda não está disponível. Em breve você poderá assinar diretamente por aqui.
                </p>
            </div>
        </div>
    </div>

@endsection
