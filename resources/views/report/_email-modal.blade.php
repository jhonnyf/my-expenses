{{-- Envio por e-mail (Pro): "agora" com os filtros da tela e agendamento recorrente. --}}
<div class="kt-modal" data-kt-modal="true" id="reportEmailModal">
    <div class="kt-modal-content max-w-[480px] top-[10%]">
        <div class="kt-modal-header">
            <h3 class="kt-modal-title">Receber relatório por e-mail</h3>
            <button type="button" class="kt-modal-close" data-kt-modal-dismiss="#reportEmailModal" aria-label="Fechar">
                <i class="ki-filled ki-cross text-base"></i>
            </button>
        </div>
        <div class="kt-modal-body flex flex-col gap-5">

            <section class="flex flex-col gap-3">
                <h4 class="text-sm font-semibold text-foreground">Enviar agora</h4>
                <p class="text-xs text-secondary-foreground">Envia o relatório desta tela, com os filtros aplicados, para o seu e-mail.</p>
                <div class="flex items-center gap-2">
                    <select id="reportEmailFormat" class="kt-select w-full" aria-label="Formato">
                        <option value="pdf">PDF</option>
                        <option value="csv">CSV (planilha)</option>
                    </select>
                    <button type="button" class="kt-btn kt-btn-mono shrink-0" data-action="send-report-email">
                        <i class="ki-filled ki-sms"></i> Enviar
                    </button>
                </div>
                <p id="reportEmailError" class="text-xs text-destructive hidden"></p>
            </section>

            <div class="border-t border-border"></div>

            <section class="flex flex-col gap-3">
                <h4 class="text-sm font-semibold text-foreground">Envio recorrente</h4>
                <p class="text-xs text-secondary-foreground">
                    Receba o relatório de todos os seus gastos automaticamente, sem filtros: a semana ou o mês anterior, às 6h.
                </p>
                <div class="grid grid-cols-2 gap-2">
                    <select id="reportScheduleFrequency" class="kt-select w-full" aria-label="Frequência">
                        @foreach(\App\Enums\ReportFrequency::cases() as $frequency)
                            <option value="{{ $frequency->value }}" @selected($schedule?->frequency === $frequency)>{{ $frequency->label() }}</option>
                        @endforeach
                    </select>
                    <select id="reportScheduleFormat" class="kt-select w-full" aria-label="Formato do envio recorrente">
                        <option value="pdf" @selected(($schedule?->format ?? 'pdf') === 'pdf')>PDF</option>
                        <option value="csv" @selected($schedule?->format === 'csv')>CSV (planilha)</option>
                    </select>
                </div>
                <p id="reportScheduleStatus" class="text-xs text-secondary-foreground {{ $schedule ? '' : 'hidden' }}">
                    @if($schedule)
                        Agendado: {{ $schedule->frequency->label() }}, em {{ $schedule->format === 'pdf' ? 'PDF' : 'CSV' }}.
                        Próximo envio em {{ $schedule->frequency->nextRunAfter(now())->format('d/m/Y') }}.
                    @endif
                </p>
                <div class="flex items-center gap-2">
                    <button type="button" class="kt-btn kt-btn-primary" data-action="save-report-schedule">Salvar agendamento</button>
                    <button type="button" class="kt-btn kt-btn-outline {{ $schedule ? '' : 'hidden' }}" id="reportScheduleDelete" data-action="delete-report-schedule">Cancelar agendamento</button>
                </div>
                <p id="reportScheduleError" class="text-xs text-destructive hidden"></p>
            </section>
        </div>
        <div class="kt-modal-footer">
            <button type="button" class="kt-btn kt-btn-secondary" data-kt-modal-dismiss="#reportEmailModal">Fechar</button>
        </div>
    </div>
</div>
