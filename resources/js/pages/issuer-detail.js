const IssuerDetail = (() => {
    let initialized = false;

    const formatBRL = (value) => parseFloat(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

    const getThemeColors = () => {
        const style = getComputedStyle(document.documentElement);
        return {
            primary: style.getPropertyValue('--color-primary').trim(),
            secondaryForeground: style.getPropertyValue('--color-secondary-foreground').trim(),
            border: style.getPropertyValue('--color-border').trim(),
        };
    };

    const renderMonthlyChart = (data) => {
        const el = document.getElementById('issuerMonthlyChart');
        if (!el || !data?.length || typeof ApexCharts === 'undefined') return;

        const colors = getThemeColors();
        const months = data.map(({ month }) => {
            const [y, m] = month.split('-');
            return new Date(y, m - 1).toLocaleDateString('pt-BR', { month: 'short', year: '2-digit' });
        });

        new ApexCharts(el, {
            series: [{ name: 'Gasto', data: data.map(d => d.total) }],
            chart: { type: 'bar', height: '100%', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, zoom: { enabled: false } },
            colors: [colors.primary],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
            dataLabels: { enabled: false },
            xaxis: {
                categories: months,
                labels: { style: { colors: colors.secondaryForeground, fontSize: '11px' } },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: { labels: { style: { colors: colors.secondaryForeground, fontSize: '11px' }, formatter: formatBRL } },
            grid: { borderColor: colors.border, strokeDashArray: 4 },
            tooltip: { y: { formatter: formatBRL } },
        }).render();
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            renderMonthlyChart(window.pageConfig?.issuerMonthly);
        }
    };
})();

export default IssuerDetail;
