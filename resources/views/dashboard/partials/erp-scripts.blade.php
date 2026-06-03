<script src="{{ asset('assets/js/customizer.js') }}"></script>
<script>
(function () {
    const currency = @json($currency ?? '');
    const dataUrl = @json(route('dashboard.data'));
    const initial = JSON.parse(document.getElementById('dashboardInitialData').textContent || '{}');
    let chartInstances = {};

    function fmtMoney(n) {
        return currency + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function animateCounters(root) {
        (root || document).querySelectorAll('[data-count-to]').forEach(function (el) {
            const target = parseFloat(el.getAttribute('data-count-to')) || 0;
            const isInt = el.getAttribute('data-fmt') === 'int';
            const start = performance.now();
            function tick(now) {
                const p = Math.min(1, (now - start) / 800);
                const val = target * p;
                el.textContent = isInt ? Math.round(val).toLocaleString() : fmtMoney(val);
                if (p < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        });
    }

    function destroyCharts() {
        Object.keys(chartInstances).forEach(function (k) {
            if (chartInstances[k] && chartInstances[k].destroy) chartInstances[k].destroy();
        });
        chartInstances = {};
    }

    function renderCharts(data) {
        if (typeof ApexCharts === 'undefined') return;
        destroyCharts();
        const c = data.charts || {};
        const rvc = data.revenue_vs_cost || {};

        chartInstances.salesTrend = new ApexCharts(document.querySelector('#chartSalesTrend'), {
            series: [{ name: 'Sales', data: c.sales_trend?.sales || [] }],
            chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
            colors: ['#0d6efd'],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
            dataLabels: { enabled: false },
            xaxis: { categories: c.sales_trend?.labels || [] },
            yaxis: { labels: { formatter: function (v) { return fmtMoney(v); } } },
            tooltip: { y: { formatter: function (v) { return fmtMoney(v); } } },
            noData: { text: 'No sales data' }
        });
        chartInstances.salesTrend.render();

        chartInstances.revenueExpense = new ApexCharts(document.querySelector('#chartRevenueExpense'), {
            series: [
                { name: 'Sales', data: c.revenue_vs_expense?.sales || [] },
                { name: 'Expenses', data: c.revenue_vs_expense?.expenses || [] },
                { name: 'Profit', data: c.revenue_vs_expense?.profit || [] }
            ],
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            colors: ['#198754', '#dc3545', '#0d6efd'],
            plotOptions: { bar: { borderRadius: 3, columnWidth: '55%' } },
            xaxis: { categories: c.revenue_vs_expense?.labels || [] },
            yaxis: { labels: { formatter: function (v) { return fmtMoney(v); } } },
            legend: { position: 'top' },
            noData: { text: 'No data' }
        });
        chartInstances.revenueExpense.render();

        chartInstances.payment = new ApexCharts(document.querySelector('#chartPaymentMethods'), {
            series: c.payment_methods?.amounts || [],
            chart: { type: 'donut', height: 280 },
            labels: c.payment_methods?.labels || [],
            colors: ['#198754', '#6f42c1', '#0d6efd', '#20c997', '#6c757d'],
            legend: { position: 'bottom' },
            noData: { text: 'No payments' }
        });
        chartInstances.payment.render();

        chartInstances.revenueCost = new ApexCharts(document.querySelector('#chartRevenueCost'), {
            series: [
                { name: 'Revenue', data: rvc.revenue || [] },
                { name: 'Cost', data: rvc.cost || [] },
                { name: 'Profit', data: rvc.profit || [] }
            ],
            chart: { type: 'line', height: 300, toolbar: { show: false } },
            stroke: { curve: 'smooth', width: 2 },
            colors: ['#28a745', '#dc3545', '#0d6efd'],
            xaxis: { categories: rvc.labels || [] },
            yaxis: { labels: { formatter: function (v) { return fmtMoney(v); } } },
            legend: { position: 'top' },
            noData: { text: 'No data for range' }
        });
        chartInstances.revenueCost.render();

        chartInstances.topProducts = new ApexCharts(document.querySelector('#chartTopProducts'), {
            series: [{ name: 'Qty', data: c.top_products?.quantities || [] }],
            chart: { type: 'bar', height: 320, toolbar: { show: false } },
            plotOptions: { bar: { horizontal: true, borderRadius: 3 } },
            colors: ['#fd7e14'],
            xaxis: { categories: c.top_products?.labels || [] },
            dataLabels: { enabled: true },
            noData: { text: 'No product sales in range' }
        });
        chartInstances.topProducts.render();
    }

    function applyData(data) {
        renderCharts(data);
        const today = data.today_overview || {};
        document.querySelectorAll('[data-today-key]').forEach(function (el) {
            const k = el.getAttribute('data-today-key');
            const money = k === 'expenses' || k === 'payments_received';
            el.textContent = money ? fmtMoney(today[k]) : Number(today[k] || 0).toLocaleString();
        });
    }

    document.getElementById('btnCustomRange')?.addEventListener('click', function () {
        const el = document.getElementById('customDateFields');
        if (el) el.style.cssText = el.style.display === 'none' ? 'display:flex!important' : 'display:none!important';
    });

    document.addEventListener('DOMContentLoaded', function () {
        applyData(initial);
        animateCounters();
    });
})();
</script>
