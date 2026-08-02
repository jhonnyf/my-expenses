{{--
    Filtro de período reutilizável (Este Mês / Mês Passado / Este Ano / Tudo / Personalizado).
    Espera receber:
    - $periodFilterAction: URL de destino do form (GET)
    - $filters: array com 'start_date'/'end_date' (chaves podem estar ausentes)
    - $extraFields (opcional): ['nome_do_campo' => 'valor'] — outros filtros da página
      (ex: busca) que precisam sobreviver ao submit deste form, senão se perdem.
--}}
@php
    $periodStart = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
    $periodEnd = $filters['end_date'] ?? now()->format('Y-m-d');

    $thisMonthStart = now()->startOfMonth()->format('Y-m-d');
    $thisMonthEnd = now()->format('Y-m-d');
    $lastMonthStart = now()->startOfMonth()->subMonth()->format('Y-m-d');
    $lastMonthEnd = now()->startOfMonth()->subDay()->format('Y-m-d');
    $thisYearStart = now()->startOfYear()->format('Y-m-d');
    // Data-sentinela para "Tudo": anterior a qualquer NFC-e real (eletrônica só existe desde ~2008).
    $allTimeStart = '2000-01-01';

    $activePeriod = match(true) {
        $periodStart === $thisMonthStart && $periodEnd === $thisMonthEnd => 'this_month',
        $periodStart === $lastMonthStart && $periodEnd === $lastMonthEnd => 'last_month',
        $periodStart === $thisYearStart && $periodEnd === $thisMonthEnd => 'this_year',
        $periodStart === $allTimeStart => 'all',
        default => 'custom',
    };
@endphp

<div class="kt-card">
    <div class="kt-card-content p-4">
        <form id="periodFilterForm" method="GET" action="{{ $periodFilterAction }}" class="flex flex-wrap items-center gap-3">
            @foreach($extraFields ?? [] as $fieldName => $fieldValue)
                <input type="hidden" name="{{ $fieldName }}" value="{{ $fieldValue }}" />
            @endforeach
            <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Período">
                <button type="button" data-action="filter-period" data-period="this_month"
                        class="kt-btn kt-btn-sm {{ $activePeriod === 'this_month' ? 'kt-btn-primary' : 'kt-btn-outline' }}">
                    Este Mês
                </button>
                <button type="button" data-action="filter-period" data-period="last_month"
                        class="kt-btn kt-btn-sm {{ $activePeriod === 'last_month' ? 'kt-btn-primary' : 'kt-btn-outline' }}">
                    Mês Passado
                </button>
                <button type="button" data-action="filter-period" data-period="this_year"
                        class="kt-btn kt-btn-sm {{ $activePeriod === 'this_year' ? 'kt-btn-primary' : 'kt-btn-outline' }}">
                    Este Ano
                </button>
                <button type="button" data-action="filter-period" data-period="all"
                        class="kt-btn kt-btn-sm {{ $activePeriod === 'all' ? 'kt-btn-primary' : 'kt-btn-outline' }}">
                    Tudo
                </button>
                <button type="button" data-action="filter-period" data-period="custom"
                        class="kt-btn kt-btn-sm {{ $activePeriod === 'custom' ? 'kt-btn-primary' : 'kt-btn-outline' }}">
                    Personalizado
                </button>
            </div>
            <div id="periodCustomRange" class="flex flex-wrap items-center gap-2 {{ $activePeriod === 'custom' ? '' : 'hidden' }}">
                <input type="date" name="start_date" id="periodStartDate" class="kt-input kt-input-sm"
                       value="{{ $periodStart }}" />
                <span class="text-xs text-secondary-foreground">até</span>
                <input type="date" name="end_date" id="periodEndDate" class="kt-input kt-input-sm"
                       value="{{ $periodEnd }}" />
                <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">Aplicar</button>
            </div>
        </form>
    </div>
</div>
