@extends('layout.main')
@section('page-module', 'budget')

@section('content')

    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Orçamento Mensal</h1>
                <p class="text-sm font-normal text-secondary-foreground">{{ now()->translatedFormat('F \d\e Y') }}</p>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            @if($budgets->isNotEmpty())
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 lg:gap-7.5">

                    <div class="kt-card flex-row items-center gap-4 p-5">
                        <div class="flex items-center justify-center size-10 rounded-xl bg-primary/10 shrink-0">
                            <i class="ki-filled ki-wallet text-primary text-xl"></i>
                        </div>
                        <div class="flex flex-col gap-0.5 min-w-0">
                            <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">
                                R$ {{ number_format($summary['total_budgeted'], 2, ',', '.') }}
                            </span>
                            <span class="text-xs font-normal text-secondary-foreground">Total Orçado</span>
                        </div>
                    </div>

                    <div class="kt-card flex-row items-center gap-4 p-5">
                        <div class="flex items-center justify-center size-10 rounded-xl bg-violet-500/10 shrink-0">
                            <i class="ki-filled ki-dollar text-violet-600 text-xl"></i>
                        </div>
                        <div class="flex flex-col gap-0.5 min-w-0">
                            <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">
                                R$ {{ number_format($summary['total_spent'], 2, ',', '.') }}
                            </span>
                            <span class="text-xs font-normal text-secondary-foreground">Total Gasto</span>
                        </div>
                    </div>

                    <div class="kt-card flex-row items-center gap-4 p-5">
                        <div class="flex items-center justify-center size-10 rounded-xl bg-green-500/10 shrink-0">
                            <i class="ki-filled ki-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="flex flex-col gap-0.5 min-w-0">
                            <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">
                                R$ {{ number_format($summary['total_remaining'], 2, ',', '.') }}
                            </span>
                            <span class="text-xs font-normal text-secondary-foreground">Total Restante</span>
                        </div>
                    </div>

                    <div class="kt-card flex-row items-center gap-4 p-5">
                        <div class="flex items-center justify-center size-10 rounded-xl bg-red-500/10 shrink-0">
                            <i class="ki-filled ki-information-2 text-destructive text-xl"></i>
                        </div>
                        <div class="flex flex-col gap-0.5 min-w-0">
                            <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums">{{ $summary['over_budget_count'] }}</span>
                            <span class="text-xs font-normal text-secondary-foreground">Orçamentos Estourados</span>
                        </div>
                    </div>

                </div>
            @endif

            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">Definir Orçamento</h3>
                </div>
                <div class="kt-card-content pb-5">
                    <div class="grid md:grid-cols-3 gap-4 items-end">
                        <div>
                            <label class="text-xs text-secondary-foreground mb-1 block">Categoria</label>
                            <select id="budgetCategory" class="kt-select w-full">
                                <option value="">Geral (todas)</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-secondary-foreground mb-1 block">Limite mensal (R$)</label>
                            <input type="number" id="budgetAmount" class="kt-input w-full" step="0.01" min="0.01" placeholder="0,00" />
                        </div>
                        <div class="flex gap-2 min-w-0">
                            <button data-action="save-budget" class="kt-btn kt-btn-primary flex-1 min-w-0">
                                <i class="ki-filled ki-check"></i> <span id="btnSaveBudgetLabel">Salvar Orçamento</span>
                            </button>
                            <button data-action="cancel-edit-budget" id="btnCancelEditBudget" class="kt-btn kt-btn-outline kt-btn-icon shrink-0 hidden" title="Cancelar edição">
                                <i class="ki-filled ki-cross"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @if($budgets->isNotEmpty())
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5" id="budgetsGrid">
                    @foreach($budgets as $budget)
                        @php
                            $pct = min($budget->percentage, 100);
                            if ($budget->percentage < 75) {
                                $textStatus  = 'text-green-600';
                                $accentColor = '#22c55e';
                            } elseif ($budget->percentage < 100) {
                                $textStatus  = 'text-yellow-600';
                                $accentColor = '#eab308';
                            } else {
                                $textStatus  = 'text-destructive';
                                $accentColor = '#ef4444';
                            }
                        @endphp
                        <div class="kt-card transition-shadow hover:shadow-md" style="box-shadow: inset 0 3px 0 0 {{ $accentColor }}" id="budget-{{ $budget->id }}">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title gap-2">
                                    @if($budget->category)
                                        <span class="size-3 rounded-full shrink-0" style="background-color: {{ $budget->category->color ?? '#94A3B8' }}"></span>
                                        {{ $budget->category->name }}
                                    @else
                                        <i class="ki-filled ki-wallet text-primary"></i>
                                        Geral
                                    @endif
                                </h3>
                                <div class="kt-card-toolbar gap-1">
                                    <button data-action="edit-budget"
                                            data-budget-id="{{ $budget->id }}"
                                            data-budget-category-id="{{ $budget->category_id }}"
                                            data-budget-amount="{{ $budget->amount }}"
                                            class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm" title="Editar">
                                        <i class="ki-filled ki-pencil text-muted-foreground"></i>
                                    </button>
                                    <button data-action="delete-budget" data-budget-id="{{ $budget->id }}" class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm" title="Excluir">
                                        <i class="ki-filled ki-trash text-muted-foreground"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="kt-card-content pb-5">
                                <div class="space-y-3">
                                    <div class="flex justify-between items-baseline">
                                        <span class="text-sm text-secondary-foreground">Limite</span>
                                        <span class="text-sm font-semibold font-mono text-foreground tabular-nums">R$ {{ number_format($budget->amount, 2, ',', '.') }}</span>
                                    </div>
                                    <div class="flex justify-between items-baseline">
                                        <span class="text-sm text-secondary-foreground">Gasto</span>
                                        <span class="text-sm font-semibold font-mono {{ $textStatus }} tabular-nums">R$ {{ number_format($budget->spent, 2, ',', '.') }}</span>
                                    </div>
                                    <div class="kt-progress h-2">
                                        <div class="kt-progress-indicator" style="width: {{ $pct }}%; background-color: {{ $accentColor }}"></div>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs {{ $textStatus }} font-medium tabular-nums">{{ number_format($budget->percentage, 0) }}%</span>
                                        <span class="text-xs text-secondary-foreground tabular-nums">
                                            R$ {{ number_format($budget->spent, 2, ',', '.') }} / R$ {{ number_format($budget->amount, 2, ',', '.') }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-baseline">
                                        <span class="text-sm text-secondary-foreground">Restante</span>
                                        <span class="text-sm font-semibold font-mono {{ $budget->remaining > 0 ? 'text-green-600' : 'text-destructive' }} tabular-nums">
                                            R$ {{ number_format($budget->remaining, 2, ',', '.') }}
                                        </span>
                                    </div>
                                    @if($budget->percentage >= 100)
                                        <div class="bg-red-500/10 rounded-xl px-3 py-2 text-xs text-destructive flex items-center gap-1.5">
                                            <i class="ki-filled ki-information-2 shrink-0"></i>
                                            Orçamento excedido em R$ {{ number_format($budget->spent - $budget->amount, 2, ',', '.') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="kt-card" id="budgetsGrid">
                    <div class="kt-card-content">
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <i class="ki-filled ki-wallet text-5xl text-secondary-foreground/30 mb-4"></i>
                            <p class="text-sm font-medium text-foreground mb-1">Nenhum orçamento definido.</p>
                            <p class="text-sm text-secondary-foreground">Use o formulário acima para definir um limite de gastos mensal.</p>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

@endsection

@push('scripts')
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        storeUrl: '{{ route("budgets.store") }}',
        baseUrl: '{{ url("budgets") }}',
    });
</script>
@endpush
