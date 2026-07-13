const formatBRL = (value) => parseFloat(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const toISODate = (date) => date.toISOString().slice(0, 10);

const QUICK_RANGES = {
    'this-month': () => {
        const now = new Date();
        return [new Date(now.getFullYear(), now.getMonth(), 1), now];
    },
    'last-month': () => {
        const now = new Date();
        return [new Date(now.getFullYear(), now.getMonth() - 1, 1), new Date(now.getFullYear(), now.getMonth(), 0)];
    },
    'last-3-months': () => {
        const now = new Date();
        return [new Date(now.getFullYear(), now.getMonth() - 2, 1), now];
    },
    'this-year': () => {
        const now = new Date();
        return [new Date(now.getFullYear(), 0, 1), now];
    },
};

const Report = (() => {
    let initialized = false;
    let generateUrl;

    const submitTo = (url) => {
        const form = document.getElementById('reportForm');
        form.action = url;
        form.submit();
        form.action = generateUrl;
    };

    const applyQuickRange = (range) => {
        const resolver = QUICK_RANGES[range];
        if (!resolver) return;

        const [start, end] = resolver();
        document.getElementById('reportStartDate').value = toISODate(start);
        document.getElementById('reportEndDate').value = toISODate(end);
        document.getElementById('reportForm').submit();
    };

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

    const handleClick = (e) => {
        const submitBtn = e.target.closest('[data-action="submit-report"]');
        if (submitBtn) {
            submitTo(submitBtn.dataset.url);
            return;
        }

        const rangeBtn = e.target.closest('[data-action="quick-range"]');
        if (rangeBtn) {
            applyQuickRange(rangeBtn.dataset.range);
        }
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            ({ generateUrl } = window.pageConfig);

            document.addEventListener('click', handleClick);

            const { categoryBreakdown } = window.pageConfig;
            if (categoryBreakdown?.length) {
                renderCategoryChart(categoryBreakdown);
            }
        }
    };
})();

export default Report;
