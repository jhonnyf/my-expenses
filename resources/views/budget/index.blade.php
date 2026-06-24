@extends('layout.main')

@section('content')
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Orçamento Mensal</h1>
                <p class="text-sm text-secondary-foreground">{{ now()->translatedFormat('F \\d\\e Y') }}</p>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed space-y-5 pb-10">

        {{-- Formulário --}}
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title">
                    <i class="ki-filled ki-plus-squared text-primary me-1"></i> Definir Orçamento
                </h3>
            </div>
            <div class="kt-card-content pb-5">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-xs text-secondary-foreground">Categoria</label>
                        <select id="budgetCategory" class="kt-input mt-1 w-full">
                            <option value="">Geral (todas as categorias)</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-[200px]">
                        <label class="text-xs text-secondary-foreground">Limite mensal (R$)</label>
                        <input type="number" id="budgetAmount" class="kt-input mt-1 w-full" step="0.01" min="0.01" placeholder="0,00" />
                    </div>
                    <button onclick="saveBudget()" class="kt-btn kt-btn-primary">
                        <i class="ki-filled ki-check"></i> Salvar
                    </button>
                </div>
            </div>
        </div>

        {{-- Grid de orçamentos --}}
        @if($budgets->isNotEmpty())
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5" id="budgetsGrid">
                @foreach($budgets as $budget)
                    @php
                        $pct = min($budget->percentage, 100);
                        $barColor = $budget->percentage < 75 ? 'bg-green-500' : ($budget->percentage < 100 ? 'bg-yellow-500' : 'bg-red-500');
                        $textColor = $budget->percentage < 75 ? 'text-green-600' : ($budget->percentage < 100 ? 'text-yellow-600' : 'text-red-600');
                    @endphp
                    <div class="kt-card" id="budget-{{ $budget->id }}">
                        <div class="kt-card-header">
                            <h3 class="kt-card-title flex items-center gap-2">
                                @if($budget->category)
                                    <span class="size-3 rounded-full shrink-0" style="background-color: {{ $budget->category->color ?? '#94A3B8' }}"></span>
                                    {{ $budget->category->name }}
                                @else
                                    <i class="ki-filled ki-wallet text-primary"></i>
                                    Geral
                                @endif
                            </h3>
                            <button onclick="deleteBudget({{ $budget->id }})"
                                    class="text-muted-foreground hover:text-destructive transition-colors">
                                <i class="ki-filled ki-trash text-sm"></i>
                            </button>
                        </div>
                        <div class="kt-card-content pb-5">
                            <div class="space-y-3">
                                <div class="flex justify-between items-baseline">
                                    <span class="text-sm text-secondary-foreground">Limite</span>
                                    <span class="font-semibold font-mono">R$ {{ number_format($budget->amount, 2, ',', '.') }}</span>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-xs {{ $textColor }} font-medium">{{ number_format($budget->percentage, 0) }}%</span>
                                        <span class="text-xs text-secondary-foreground">
                                            R$ {{ number_format($budget->spent, 2, ',', '.') }} / R$ {{ number_format($budget->amount, 2, ',', '.') }}
                                        </span>
                                    </div>
                                    <div class="w-full bg-accent rounded-full h-3">
                                        <div class="{{ $barColor }} rounded-full h-3 transition-all" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-secondary-foreground">Restante</span>
                                    <span class="font-medium {{ $budget->remaining > 0 ? 'text-green-600' : 'text-red-600' }}">
                                        R$ {{ number_format($budget->remaining, 2, ',', '.') }}
                                    </span>
                                </div>
                                @if($budget->percentage >= 100)
                                    <div class="bg-red-50 dark:bg-red-500/10 rounded-lg px-3 py-2 text-xs text-red-600">
                                        <i class="ki-filled ki-information-2 me-1"></i> Orçamento excedido em R$ {{ number_format($budget->spent - $budget->amount, 2, ',', '.') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="kt-card">
                <div class="kt-card-content py-8 text-center text-secondary-foreground">
                    Nenhum orçamento definido. Use o formulário acima para começar.
                </div>
            </div>
        @endif

    </div>

    <script>window.pageConfig = { storeUrl: '{{ route("budgets.store") }}', baseUrl: '{{ url("budgets") }}', csrfToken: '{{ csrf_token() }}' };</script>
    @push('scripts') @vite('resources/js/pages/budget.js') @endpush
@endsection
