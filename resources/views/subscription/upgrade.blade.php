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
        <div class="max-w-2xl mx-auto">
            <div class="kt-card kt-card-grid">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">Grátis x Pro</h3>
                </div>

                <div class="kt-card-table">
                    <div class="kt-scrollable-x-auto">
                        <table class="kt-table kt-table-border table-auto">
                            <thead>
                                <tr>
                                    <th class="min-w-[260px]">Funcionalidade</th>
                                    <th class="w-[100px] text-center">Grátis</th>
                                    <th class="w-[100px] text-center">Pro</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ([
                                    ['label' => 'Orçamento geral (sem categoria)', 'free' => true],
                                    ['label' => 'Múltiplos orçamentos por categoria', 'free' => false],
                                    ['label' => 'Exportação de relatórios em CSV', 'free' => false],
                                    ['label' => 'Exportação de relatórios em PDF', 'free' => false],
                                    ['label' => 'Sugestões via Inteligência Artificial (categorias e nomes de produto)', 'free' => false],
                                    ['label' => 'Histórico de preços por produto', 'free' => false],
                                    ['label' => 'Comparação de preços entre lojas e cidades', 'free' => false],
                                    ['label' => 'Detecção de compras recorrentes', 'free' => false],
                                ] as $feature)
                                    <tr>
                                        <td class="py-2.5 text-sm text-foreground">{{ $feature['label'] }}</td>
                                        <td class="py-2.5 text-center">
                                            @if($feature['free'])
                                                <i class="ki-filled ki-check-circle text-green-600 text-base"></i>
                                            @else
                                                <i class="ki-filled ki-cross text-secondary-foreground text-base"></i>
                                            @endif
                                        </td>
                                        <td class="py-2.5 text-center">
                                            <i class="ki-filled ki-check-circle text-green-600 text-base"></i>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="kt-card-footer flex-col gap-3">
                    <button type="button" class="kt-btn kt-btn-primary w-full" disabled>
                        Assinar Pro (em breve)
                    </button>
                    <p class="text-xs text-secondary-foreground text-center">
                        O pagamento online ainda não está disponível. Em breve você poderá assinar diretamente por aqui.
                    </p>
                </div>
            </div>
        </div>
    </div>

@endsection
