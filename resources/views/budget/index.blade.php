@extends('layout.main')
@section('page-module', 'budget')

@section('content')

    @php
        $monthRoute = fn (?string $value) => route('budgets.index', $value ? ['month' => $value] : []);
        $canEdit = $month['is_current'];
    @endphp

    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-tight text-mono">Orçamento mensal</h1>
                <p class="text-sm font-normal text-secondary-foreground">Limites de gasto por mês, gerais ou por categoria</p>
            </div>

            {{-- Navegação entre meses --}}
            <div class="flex items-center gap-2" role="group" aria-label="Mês">
                <a href="{{ $monthRoute($month['previous']) }}" class="kt-btn kt-btn-outline kt-btn-icon" title="Mês anterior" aria-label="Mês anterior">
                    <i class="ki-filled ki-left"></i>
                </a>
                <span class="kt-btn kt-btn-outline pointer-events-none min-w-44 justify-center capitalize" aria-live="polite">{{ $month['label'] }}</span>
                @if($month['next'])
                    <a href="{{ $monthRoute($month['next']) }}" class="kt-btn kt-btn-outline kt-btn-icon" title="Próximo mês" aria-label="Próximo mês">
                        <i class="ki-filled ki-right"></i>
                    </a>
                @else
                    <span class="kt-btn kt-btn-outline kt-btn-icon opacity-50 pointer-events-none" aria-disabled="true"><i class="ki-filled ki-right"></i></span>
                @endif
                @unless($month['is_current'])
                    <a href="{{ $monthRoute(null) }}" class="kt-btn kt-btn-primary">Mês atual</a>
                @endunless
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            <div id="pageFlash" role="status" class="hidden items-center gap-3 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3">
                <i class="ki-filled ki-check-circle text-green-600 text-lg shrink-0"></i>
                <span class="text-sm text-green-600 font-medium" data-flash-text></span>
            </div>

            @if($budgets->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 lg:gap-7.5">
                    <div class="kt-card flex-row items-center gap-4 p-5">
                        <div class="flex items-center justify-center size-10 rounded-xl bg-primary/10 shrink-0">
                            <i class="ki-filled ki-wallet text-primary text-xl"></i>
                        </div>
                        <div class="flex flex-col gap-0.5 min-w-0">
                            <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">
                                R$ {{ number_format($summary['total_budgeted'], 2, ',', '.') }}
                            </span>
                            <span class="text-xs font-normal text-secondary-foreground"
                                  title="{{ $summary['scope'] === 'general' ? 'O orçamento Geral já inclui o gasto das categorias; os totais usam só ele.' : 'Soma dos orçamentos por categoria.' }}">
                                Total orçado · {{ $summary['scope'] === 'general' ? 'Geral' : 'por categoria' }}
                            </span>
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
                            <span class="text-xs font-normal text-secondary-foreground">Total gasto</span>
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
                            <span class="text-xs font-normal text-secondary-foreground">Restante</span>
                        </div>
                    </div>
                    <div class="kt-card flex-row items-center gap-4 p-5">
                        <div class="flex items-center justify-center size-10 rounded-xl bg-red-500/10 shrink-0">
                            <i class="ki-filled ki-information-2 text-destructive text-xl"></i>
                        </div>
                        <div class="flex flex-col gap-0.5 min-w-0">
                            <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums">{{ $summary['over_budget_count'] }}</span>
                            <span class="text-xs font-normal text-secondary-foreground">{{ $summary['over_budget_count'] == 1 ? 'Orçamento estourado' : 'Orçamentos estourados' }}</span>
                        </div>
                    </div>
                </div>
            @endif

            @if($canEdit)
                <div class="kt-card">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">Definir orçamento</h3>
                    </div>
                    <div class="kt-card-content pb-5">
                        <div class="grid md:grid-cols-3 gap-4 items-end">
                            <div>
                                <label class="text-xs text-secondary-foreground mb-1 block" for="budgetCategory">Categoria</label>
                                <select id="budgetCategory" class="kt-select w-full" data-kt-select="true" data-kt-select-dropdown-strategy="fixed" data-kt-select-placeholder="Geral (todas)">
                                    <option value="">Geral (todas)</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" @disabled(! $isPro)>{{ $cat->name }}{{ $isPro ? '' : ' (Pro)' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-secondary-foreground mb-1 block" for="budgetAmount">Limite mensal (R$)</label>
                                <input type="number" id="budgetAmount" class="kt-input w-full" step="0.01" min="0.01" max="99999999.99" placeholder="0,00" />
                            </div>
                            <div class="flex gap-2 min-w-0">
                                <button data-action="save-budget" class="kt-btn kt-btn-primary flex-1 min-w-0">
                                    <i class="ki-filled ki-check"></i> <span id="btnSaveBudgetLabel">Salvar orçamento</span>
                                </button>
                                <button data-action="cancel-edit-budget" id="btnCancelEditBudget" class="kt-btn kt-btn-outline kt-btn-icon shrink-0 hidden" title="Cancelar edição">
                                    <i class="ki-filled ki-cross"></i>
                                </button>
                            </div>
                        </div>
                        <p id="budgetFormError" class="text-xs text-destructive mt-3 hidden"></p>
                        @unless($isPro)
                            <p class="text-xs text-secondary-foreground mt-3">
                                No plano gratuito você define o orçamento <strong>Geral</strong>.
                                Orçamentos por categoria são do <a href="{{ route('subscription.upgrade') }}" class="text-primary font-medium hover:underline">plano Pro</a>.
                            </p>
                        @endunless
                    </div>
                </div>
            @else
                <div class="kt-card">
                    <div class="kt-card-content p-5 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-secondary-foreground">
                            Os limites valem para todos os meses. Para alterá-los, volte ao mês atual.
                        </p>
                        <a href="{{ $monthRoute(null) }}" class="kt-btn kt-btn-outline kt-btn-sm">Ir para o mês atual</a>
                    </div>
                </div>
            @endif

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
                            $badgeClass = $budget->percentage < 75 ? 'kt-badge-success' : ($budget->percentage < 100 ? 'kt-badge-warning' : 'kt-badge-destructive');
                            $budgetName = $budget->category->name ?? 'Geral';

                            // Caixa de projeção: todo card tem uma, para o layout ser o mesmo em todos.
                            $projectionAlert = match (true) {
                                $budget->percentage >= 100 => [
                                    'box' => 'bg-red-500/10 text-destructive', 'icon' => 'ki-information-2',
                                    'text' => 'Orçamento excedido em R$ '.number_format($budget->spent - $budget->amount, 2, ',', '.'),
                                ],
                                (bool) $budget->exceeds_on => [
                                    'box' => 'bg-yellow-500/10 text-yellow-700', 'icon' => 'ki-information-2',
                                    'text' => 'No ritmo atual, o limite estoura por volta de '.\Carbon\Carbon::parse($budget->exceeds_on)->format('d/m').'.',
                                ],
                                $budget->projected !== null => [
                                    'box' => 'bg-green-500/10 text-green-700', 'icon' => 'ki-check-circle',
                                    'text' => 'No ritmo atual, o mês fecha em R$ '.number_format($budget->projected, 2, ',', '.').' ('.number_format($budget->projected_percentage, 0).'% do limite).',
                                ],
                                $budget->spent <= 0 => [
                                    'box' => 'bg-accent text-secondary-foreground', 'icon' => 'ki-information-2',
                                    'text' => $month['is_current'] ? 'Ainda sem gastos neste mês.' : 'Sem gastos neste mês.',
                                ],
                                ! $month['is_current'] => [
                                    'box' => 'bg-green-500/10 text-green-700', 'icon' => 'ki-check-circle',
                                    'text' => 'Mês fechado em R$ '.number_format($budget->spent, 2, ',', '.').' ('.number_format($budget->percentage, 0).'% do limite).',
                                ],
                                default => [
                                    'box' => 'bg-accent text-secondary-foreground', 'icon' => 'ki-information-2',
                                    'text' => 'Poucos dias de dados para projetar o mês.',
                                ],
                            };
                        @endphp
                        <div class="kt-card flex flex-col transition-shadow hover:shadow-md" style="box-shadow: inset 0 3px 0 0 {{ $accentColor }}" id="budget-{{ $budget->id }}">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title gap-2 min-w-0">
                                    @if($budget->category)
                                        <span class="size-3 rounded-full shrink-0" style="background-color: {{ $budget->category->color ?? '#94A3B8' }}"></span>
                                        <a href="{{ route('categories.show', ['category' => $budget->category, 'start_date' => $month['value'].'-01', 'end_date' => \Carbon\Carbon::parse($month['value'].'-01')->endOfMonth()->toDateString()]) }}"
                                           class="hover:underline break-words min-w-0" title="Ver a categoria neste mês">{{ $budgetName }}</a>
                                    @else
                                        <i class="ki-filled ki-wallet text-primary"></i>
                                        Geral
                                    @endif

                                </h3>
                                @if($canEdit)
                                    <div class="kt-card-toolbar gap-1">
                                        <button data-action="edit-budget"
                                                data-budget-id="{{ $budget->id }}"
                                                data-budget-category-id="{{ $budget->category_id }}"
                                                data-budget-amount="{{ $budget->amount }}"
                                                class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm" title="Editar">
                                            <i class="ki-filled ki-pencil text-muted-foreground"></i>
                                        </button>
                                        <button data-action="prepare-delete-budget" data-kt-modal-toggle="#deleteBudgetModal"
                                                data-budget-id="{{ $budget->id }}" data-budget-name="{{ $budgetName }}"
                                                class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm" title="Excluir">
                                            <i class="ki-filled ki-trash text-muted-foreground"></i>
                                        </button>
                                    </div>
                                @endif
                            </div>
                            <div class="kt-card-content pb-5 flex flex-col gap-4 flex-1">

                                {{-- Destaque: gasto do mês, limite e % --}}
                                <div>
                                    <div class="flex items-end justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-2xl font-semibold font-mono tabular-nums leading-tight {{ $textStatus }} truncate">
                                                R$ {{ number_format($budget->spent, 2, ',', '.') }}
                                            </p>
                                            <p class="text-xs text-secondary-foreground tabular-nums">de R$ {{ number_format($budget->amount, 2, ',', '.') }}</p>
                                        </div>
                                        <span class="kt-badge kt-badge-outline kt-badge-sm tabular-nums shrink-0 {{ $badgeClass }}">{{ number_format($budget->percentage, 0) }}%</span>
                                    </div>

                                    {{-- Barra; o traço marca onde a projeção do mês termina --}}
                                    <div class="relative mt-3">
                                        <div class="kt-progress h-2">
                                            <div class="kt-progress-indicator" style="width: {{ $pct }}%; background-color: {{ $accentColor }}"></div>
                                        </div>
                                        @if($budget->projected !== null && $budget->percentage < 100)
                                            <span class="absolute top-1/2 h-3.5 w-0.5 -translate-y-1/2 rounded"
                                                  style="left: {{ min($budget->projected_percentage, 100) }}%; background-color: var(--color-foreground); opacity: .55"
                                                  title="Projeção do mês: R$ {{ number_format($budget->projected, 2, ',', '.') }}"></span>
                                        @endif
                                    </div>

                                    <div class="mt-1.5 flex items-center justify-between gap-2 text-xs">
                                        <span class="text-secondary-foreground">Restante</span>
                                        <span class="font-semibold font-mono tabular-nums {{ $budget->remaining > 0 ? 'text-green-600' : 'text-destructive' }}">
                                            R$ {{ number_format($budget->remaining, 2, ',', '.') }}
                                        </span>
                                    </div>
                                </div>

                                <div class="{{ $projectionAlert['box'] }} rounded-xl px-3 py-2 text-xs flex items-center gap-1.5">
                                    <i class="ki-filled {{ $projectionAlert['icon'] }} shrink-0"></i>
                                    {{ $projectionAlert['text'] }}
                                </div>

                                {{-- Indicadores (rodapé fixo: os cards alinham mesmo com conteúdos diferentes) --}}
                                @if($budget->projected !== null || $budget->daily_available !== null || ($budget->delta_pct !== null && $budget->delta_pct != 0))
                                    <div class="mt-auto flex gap-3 border-t border-border pt-3">
                                        @if($budget->projected !== null)
                                            <div class="flex-1 min-w-0" title="Gasto médio por dia até hoje × dias do mês">
                                                <p class="text-[11px] text-secondary-foreground">Projeção</p>
                                                <p class="text-sm font-semibold font-mono tabular-nums truncate">R$ {{ number_format($budget->projected, 2, ',', '.') }}</p>
                                                <p class="text-[11px] tabular-nums {{ $budget->projected_percentage >= 100 ? 'text-destructive' : 'text-secondary-foreground' }}">
                                                    {{ number_format($budget->projected_percentage, 0) }}% do limite
                                                </p>
                                            </div>
                                        @endif
                                        @if($budget->daily_available !== null)
                                            <div class="flex-1 min-w-0 {{ $budget->projected !== null ? 'border-s border-border ps-3' : '' }}">
                                                <p class="text-[11px] text-secondary-foreground">Disponível por dia</p>
                                                <p class="text-sm font-semibold font-mono tabular-nums truncate">R$ {{ number_format($budget->daily_available, 2, ',', '.') }}</p>
                                                <p class="text-[11px] text-secondary-foreground">até o fim do mês</p>
                                            </div>
                                        @endif
                                        @if($budget->delta_pct !== null && $budget->delta_pct != 0)
                                            <div class="flex-1 min-w-0 {{ $budget->projected !== null || $budget->daily_available !== null ? 'border-s border-border ps-3' : '' }}"
                                                 title="Comparado ao mês anterior">
                                                <p class="text-[11px] text-secondary-foreground">Vs. mês anterior</p>
                                                <p class="text-sm font-semibold tabular-nums {{ $budget->delta_pct > 0 ? 'text-destructive' : 'text-green-600' }}">
                                                    {{ $budget->delta_pct > 0 ? '▲' : '▼' }} {{ number_format(abs($budget->delta_pct), 1, ',', '.') }}%
                                                </p>
                                                <p class="text-[11px] text-secondary-foreground tabular-nums truncate">R$ {{ number_format($budget->previous_spent, 2, ',', '.') }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @endif
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
                            <p class="text-sm text-secondary-foreground">
                                {{ $canEdit ? 'Use o formulário acima para definir um limite de gastos mensal.' : 'Volte ao mês atual para definir um limite de gastos mensal.' }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="kt-modal" data-kt-modal="true" id="deleteBudgetModal">
        <div class="kt-modal-content max-w-[420px] top-[15%]">
            <div class="kt-modal-header">
                <h3 class="kt-modal-title">Excluir orçamento</h3>
                <button type="button" class="kt-modal-close" data-kt-modal-dismiss="#deleteBudgetModal" aria-label="Fechar">
                    <i class="ki-filled ki-cross text-base"></i>
                </button>
            </div>
            <div class="kt-modal-body flex flex-col gap-3">
                <p class="text-sm text-foreground">Excluir o orçamento <strong id="deleteBudgetName"></strong>?</p>
                <p class="text-xs text-secondary-foreground">O limite deixa de valer em todos os meses. Seus gastos não são afetados.</p>
                <p id="deleteBudgetError" class="text-xs text-destructive hidden"></p>
            </div>
            <div class="kt-modal-footer">
                <button type="button" class="kt-btn kt-btn-secondary" data-kt-modal-dismiss="#deleteBudgetModal">Cancelar</button>
                <button type="button" class="kt-btn kt-btn-destructive" data-action="confirm-delete-budget">Excluir orçamento</button>
            </div>
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
