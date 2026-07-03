const Dashboard = (() => {
    let initialized = false;

    const getThemeColors = () => {
        const style = getComputedStyle(document.documentElement);
        return {
            primary: style.getPropertyValue('--color-primary').trim(),
            foreground: style.getPropertyValue('--color-foreground').trim(),
            secondaryForeground: style.getPropertyValue('--color-secondary-foreground').trim(),
            border: style.getPropertyValue('--color-border').trim(),
            background: style.getPropertyValue('--color-background').trim(),
        };
    };

    const formatBRL = (value) => parseFloat(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

    const renderMonthlyChart = (data, colors) => {
        const el = document.getElementById('monthlyExpensesChart');
        if (!el) return;

        const months = data.map(d => {
            const [y, m] = d.month.split('-');
            return new Date(y, m - 1).toLocaleDateString('pt-BR', { month: 'short', year: '2-digit' });
        });

        const chart = new ApexCharts(el, {
            series: [{ name: 'Gastos', data: data.map(d => parseFloat(d.total)) }],
            chart: {
                type: 'area',
                height: '100%',
                fontFamily: 'Inter, sans-serif',
                toolbar: { show: false },
                zoom: { enabled: false },
            },
            colors: [colors.primary],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2.5 },
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05, stops: [0, 90, 100] },
            },
            xaxis: {
                categories: months,
                labels: { style: { colors: colors.secondaryForeground, fontSize: '11px' } },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    style: { colors: colors.secondaryForeground, fontSize: '11px' },
                    formatter: v => formatBRL(v),
                },
            },
            grid: { borderColor: colors.border, strokeDashArray: 4, padding: { left: 8, right: 8 } },
            tooltip: {
                y: { formatter: v => formatBRL(v) },
                theme: false,
                style: { fontSize: '12px' },
            },
        });
        chart.render();
    };

    const renderCategoryChart = (data, colors) => {
        const el = document.getElementById('categoryChart');
        if (!el) return;

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
            stroke: { width: 2, colors: [colors.background] },
            tooltip: {
                y: { formatter: v => formatBRL(v) },
                style: { fontSize: '12px' },
            },
        });
        chart.render();
    };

    const renderPaymentChart = (data, labels, colors) => {
        const el = document.getElementById('paymentChart');
        if (!el) return;

        const chartColors = ['#3b82f6', '#8b5cf6', '#06b6d4', '#f59e0b', '#10b981', '#ef4444', '#ec4899', '#6366f1'];

        const chart = new ApexCharts(el, {
            series: data.map(d => parseFloat(d.total)),
            labels: data.map(d => labels[d.method] || d.method),
            colors: chartColors.slice(0, data.length),
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
            stroke: { width: 2, colors: [colors.background] },
            tooltip: {
                y: { formatter: v => formatBRL(v) },
                style: { fontSize: '12px' },
            },
        });
        chart.render();
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            const { monthlyExpenses, spendingByCategory, paymentDistribution, paymentLabels } = window.pageConfig;
            const colors = getThemeColors();

            if (monthlyExpenses?.length) {
                renderMonthlyChart(monthlyExpenses, colors);
            }

            if (spendingByCategory?.length) {
                renderCategoryChart(spendingByCategory, colors);
            }

            if (paymentDistribution?.length) {
                renderPaymentChart(paymentDistribution, paymentLabels, colors);
            }
        }
    };
})();

export default Dashboard;
