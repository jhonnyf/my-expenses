@extends('layout.main')
@section('page-module', '')

@section('content')

    @php $isPro = $user->isPro(); @endphp

    <div class="kt-container-fixed">
        <div class="flex flex-col items-center text-center gap-2 py-6 lg:py-10 max-w-xl mx-auto">
            <span class="kt-badge kt-badge-sm mb-2 {{ $isPro ? 'kt-badge-success' : 'kt-badge-secondary' }}">{{ $isPro ? 'Pro' : 'Grátis' }}</span>

            @if($isPro)
                <h1 class="text-2xl font-semibold text-mono">Você já é Pro</h1>
                <p class="text-sm text-secondary-foreground">
                    Todos os recursos abaixo estão liberados
                    @if($user->subscription?->started_at) desde {{ $user->subscription->started_at->format('d/m/Y') }}@endif.
                </p>
            @else
                <h1 class="text-2xl font-semibold text-mono">Desbloqueie o plano Pro</h1>
                <p class="text-sm text-secondary-foreground">
                    {{ session('paywall_message') ?? 'Tenha acesso completo a todas as funcionalidades do sistema.' }}
                </p>
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
                                @foreach ($features as $key => $feature)
                                    <tr @class(['bg-primary/5' => $highlight === $key]) @if($highlight === $key) aria-current="true" @endif>
                                        <td class="py-2.5 text-sm text-foreground">{{ $feature['label'] }}</td>
                                        <td class="py-2.5 text-center">
                                            @if($feature['free'])
                                                <i class="ki-filled ki-check-circle text-green-600 text-base" aria-hidden="true"></i>
                                                <span class="sr-only">Incluído no plano Grátis</span>
                                            @else
                                                <i class="ki-filled ki-cross text-secondary-foreground text-base" aria-hidden="true"></i>
                                                <span class="sr-only">Não incluído no plano Grátis</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 text-center">
                                            <i class="ki-filled ki-check-circle text-green-600 text-base" aria-hidden="true"></i>
                                            <span class="sr-only">Incluído no plano Pro</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="kt-card-footer flex-col gap-3">
                    @if($isPro)
                        <a href="{{ route('account.index') }}" class="kt-btn kt-btn-outline w-full">Voltar para minha conta</a>
                    @else
                        <button type="button" class="kt-btn kt-btn-primary w-full" disabled>
                            Assinar Pro (em breve)
                        </button>
                        <p class="text-xs text-secondary-foreground text-center">
                            O pagamento online ainda não está disponível. Em breve você poderá assinar diretamente por aqui.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection
