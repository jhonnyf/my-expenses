import Utils from '../utils';

const formatBRL = (value) => parseFloat(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const Report = (() => {
    let initialized = false;
    let emailUrl, scheduleUrl, reportFilters;

    const renderCategoryChart = (data) => {
        const el = document.getElementById('reportCategoryChart');
        if (!el || !data.length) return;

        const style = getComputedStyle(document.documentElement);
        const background = style.getPropertyValue('--color-background').trim();

        const chart = new ApexCharts(el, {
            series: data.map(d => parseFloat(d.total)),
            labels: data.map(d => d.category_name),
            colors: data.map(d => d.category_color),
            chart: {
                type: 'donut',
                height: '100%',
                fontFamily: 'Inter, sans-serif',
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                formatter: w => formatBRL(w.globals.seriesTotals.reduce((a, b) => a + b, 0)),
                            },
                            value: { formatter: v => formatBRL(v) },
                        },
                    },
                },
            },
            dataLabels: { enabled: false },
            legend: { show: false },
            stroke: { width: 2, colors: [background] },
            tooltip: {
                y: { formatter: v => formatBRL(v) },
                style: { fontSize: '12px' },
            },
        });
        chart.render();
    };

    const handleAliasUpdated = (e) => {
        const { description, display_name: displayName } = e.detail;

        document.querySelectorAll(`.item-alias-name[data-item-description="${CSS.escape(description)}"]`).forEach(el => {
            el.textContent = displayName;
        });
    };

    // ---------- E-mail e agendamento (Pro) ----------

    const setError = (id, message) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = message || '';
        el.classList.toggle('hidden', !message);
    };

    const closeEmailModal = () => window.KTModal?.getInstance(document.getElementById('reportEmailModal'))?.hide();

    const sendReportByEmail = () => {
        setError('reportEmailError', '');

        Utils.http(emailUrl, {
            method: 'POST',
            body: { ...reportFilters, format: document.getElementById('reportEmailFormat').value },
        })
            .then(() => {
                closeEmailModal();
                Utils.showFlash('Relatório na fila: você o receberá por e-mail em instantes.');
            })
            .catch(error => setError('reportEmailError', Utils.errorMessage(error, 'Não foi possível enviar o relatório.')));
    };

    const scheduleSummary = (schedule) => {
        const format = schedule.format === 'pdf' ? 'PDF' : 'CSV';
        const next = new Date(`${schedule.next_run}T00:00:00`).toLocaleDateString('pt-BR');

        return `Agendado: ${schedule.frequency_label}, em ${format}. Próximo envio em ${next}.`;
    };

    const saveSchedule = () => {
        setError('reportScheduleError', '');

        Utils.http(scheduleUrl, {
            method: 'PUT',
            body: {
                frequency: document.getElementById('reportScheduleFrequency').value,
                format: document.getElementById('reportScheduleFormat').value,
            },
        })
            .then(schedule => {
                const status = document.getElementById('reportScheduleStatus');
                status.textContent = scheduleSummary(schedule);
                status.classList.remove('hidden');
                document.getElementById('reportScheduleDelete').classList.remove('hidden');
            })
            .catch(error => setError('reportScheduleError', Utils.errorMessage(error, 'Não foi possível salvar o agendamento.')));
    };

    const deleteSchedule = () => {
        setError('reportScheduleError', '');

        Utils.http(scheduleUrl, { method: 'DELETE' })
            .then(() => {
                document.getElementById('reportScheduleStatus').classList.add('hidden');
                document.getElementById('reportScheduleDelete').classList.add('hidden');
            })
            .catch(error => setError('reportScheduleError', Utils.errorMessage(error, 'Não foi possível cancelar o agendamento.')));
    };

    const ACTIONS = {
        'send-report-email': () => sendReportByEmail(),
        'save-report-schedule': () => saveSchedule(),
        'delete-report-schedule': () => deleteSchedule(),
    };

    const handleClick = (e) => {
        const btn = e.target.closest('[data-action]');
        if (btn) ACTIONS[btn.dataset.action]?.(btn);
    };

    // Categoria trocada na tabela: totais e gráfico (do servidor) ficam defasados até atualizar.
    const handleCategoryAssigned = () => {
        const notice = document.getElementById('reportStaleNotice');
        notice?.classList.remove('hidden');
        notice?.classList.add('flex');
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            ({ emailUrl, scheduleUrl, reportFilters = {} } = window.pageConfig);

            Utils.initPeriodFilter();
            Utils.restoreFlash();

            // A busca por texto só roda no botão "Buscar" (ou Enter); emissor, categoria e ordenação aplicam ao mudar.
            const form = document.getElementById('reportFilterForm');
            form?.querySelectorAll('select').forEach(el => el.addEventListener('change', () => form.requestSubmit()));

            document.addEventListener('click', handleClick);
            document.addEventListener('product-alias:updated', handleAliasUpdated);
            document.addEventListener('item-category:assigned', handleCategoryAssigned);

            const { categoryBreakdown, reportMonthly, assignCategoryUrl, suggestItemCategoryUrl } = window.pageConfig;
            Utils.initCategoryAssignment(assignCategoryUrl);
            if (suggestItemCategoryUrl) Utils.initCategoryAiSuggestion(suggestItemCategoryUrl, assignCategoryUrl);

            if (categoryBreakdown?.length) {
                renderCategoryChart(categoryBreakdown);
            }
            Utils.renderMonthlyBars('reportMonthlyChart', reportMonthly);
        }
    };
})();

export default Report;
