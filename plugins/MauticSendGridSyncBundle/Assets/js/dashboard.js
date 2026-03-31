var SendGridSync = (function () {
    'use strict';

    var breakdownChart = null;
    var trendChart = null;

    function init() {
        renderBreakdownChart();
        renderTrendChart();
        bindEvents();
    }

    function renderBreakdownChart() {
        var ctx = document.getElementById('sgs-breakdown-chart');
        if (!ctx || !sgsBreakdownData || !sgsBreakdownData.data.length) return;

        breakdownChart = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: sgsBreakdownData.labels,
                datasets: [{
                    data: sgsBreakdownData.data,
                    backgroundColor: sgsBreakdownData.colors,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                legend: {
                    position: 'bottom',
                    labels: { fontSize: 12, padding: 15 }
                },
                tooltips: {
                    callbacks: {
                        label: function (item, data) {
                            var label = data.labels[item.index] || '';
                            var value = data.datasets[0].data[item.index];
                            var total = data.datasets[0].data.reduce(function (a, b) { return a + b; }, 0);
                            var pct = total > 0 ? Math.round((value / total) * 100) : 0;
                            return label + ': ' + value.toLocaleString() + ' (' + pct + '%)';
                        }
                    }
                }
            }
        });
    }

    function renderTrendChart() {
        var ctx = document.getElementById('sgs-trend-chart');
        if (!ctx || !sgsTrendData) return;

        var datasets = sgsTrendData.datasets.map(function (ds) {
            return {
                label: ds.label,
                data: ds.data,
                borderColor: ds.borderColor,
                backgroundColor: ds.backgroundColor,
                fill: ds.fill,
                lineTension: 0.3,
                pointRadius: 3,
                pointHoverRadius: 5
            };
        });

        trendChart = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: sgsTrendData.labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    yAxes: [{
                        ticks: { beginAtZero: true, precision: 0 }
                    }],
                    xAxes: [{
                        ticks: { maxTicksLimit: 15 }
                    }]
                },
                legend: { display: true, position: 'top' },
                tooltips: { mode: 'index', intersect: false }
            }
        });
    }

    function bindEvents() {
        // Period toggle for "New" card
        document.querySelectorAll('.sgs-period').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelectorAll('.sgs-period').forEach(function (p) { p.classList.remove('active'); });
                this.classList.add('active');
                var period = this.dataset.period;
                var value = period === '24h' ? sgsStats.new_24h :
                            period === '7d' ? sgsStats.new_7d : sgsStats.new_30d;
                document.getElementById('sgs-new').textContent = value.toLocaleString();
            });
        });

        // Trend chart filters
        var periodSelect = document.getElementById('sgs-trend-period');
        var typeSelect = document.getElementById('sgs-trend-type');

        if (periodSelect) {
            periodSelect.addEventListener('change', function () { updateTrendChart(); });
        }
        if (typeSelect) {
            typeSelect.addEventListener('change', function () { updateTrendChart(); });
        }

        // Suppressions search
        var searchInput = document.getElementById('sgs-search-email');
        var filterType = document.getElementById('sgs-filter-type');
        var searchTimeout = null;

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function () { loadSuppressions(1); }, 400);
            });
        }
        if (filterType) {
            filterType.addEventListener('change', function () { loadSuppressions(1); });
        }
    }

    function updateTrendChart() {
        var period = document.getElementById('sgs-trend-period').value;
        var type = document.getElementById('sgs-trend-type').value;

        var url = mauticBaseUrl + 's/plugins/sendgridsync/dashboard/chart/trend?period=' + period;
        if (type) url += '&suppression_type=' + type;

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (trendChart) {
                    trendChart.data.labels = data.labels;
                    trendChart.data.datasets[0].data = data.datasets[0].data;
                    trendChart.data.datasets[0].label = data.datasets[0].label;
                    trendChart.update();
                }
            });
    }

    function loadSuppressions(page) {
        var email = (document.getElementById('sgs-search-email') || {}).value || '';
        var type = (document.getElementById('sgs-filter-type') || {}).value || '';

        var url = mauticBaseUrl + 's/plugins/sendgridsync/dashboard/suppressions?page=' + page;
        if (email) url += '&email=' + encodeURIComponent(email);
        if (type) url += '&type=' + encodeURIComponent(type);

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                renderSuppressions(data);
            });
    }

    function renderSuppressions(data) {
        var tbody = document.getElementById('sgs-suppressions-body');
        if (!tbody) return;

        var html = '';
        data.items.forEach(function (item) {
            var emailCell = item.contact_id
                ? '<a href="' + mauticBaseUrl + 's/contacts/view/' + item.contact_id + '">' + escapeHtml(item.email) + '</a>'
                : escapeHtml(item.email);

            var actionClass = item.action === 'dnc' ? 'danger' : (item.action === 'segment' ? 'info' : 'default');

            html += '<tr>' +
                '<td>' + emailCell + '</td>' +
                '<td><span class="label sgs-type-' + item.type + '">' + escapeHtml(item.type_label) + '</span></td>' +
                '<td class="sgs-reason-cell" title="' + escapeHtml(item.reason || '') + '">' + escapeHtml((item.reason || '-').substring(0, 60)) + '</td>' +
                '<td>' + escapeHtml(item.sendgrid_date) + '</td>' +
                '<td>' + escapeHtml(item.synced_date) + '</td>' +
                '<td><span class="label label-' + actionClass + '">' + item.action.toUpperCase() + '</span></td>' +
                '</tr>';
        });

        tbody.innerHTML = html || '<tr><td colspan="6" class="text-center">No results found</td></tr>';

        // Update pagination
        var pagDiv = document.getElementById('sgs-suppressions-pagination');
        if (pagDiv && data.pages > 1) {
            var pagHtml = '<nav><ul class="pagination pagination-sm">';
            for (var p = 1; p <= data.pages; p++) {
                pagHtml += '<li class="' + (p === data.page ? 'active' : '') + '">' +
                    '<a href="#" onclick="SendGridSync.loadSuppressions(' + p + '); return false;">' + p + '</a></li>';
            }
            pagHtml += '</ul></nav>';
            pagDiv.innerHTML = pagHtml;
        } else if (pagDiv) {
            pagDiv.innerHTML = '';
        }
    }

    function runSync() {
        var btn = document.getElementById('sgs-sync-btn');
        if (!btn) return;

        btn.disabled = true;
        btn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Syncing...';

        fetch(mauticBaseUrl + 's/plugins/sendgridsync/sync/run', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': mauticAjaxCsrf
            }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-refresh-line"></i> Run Sync Now';

            if (data.success) {
                Mautic.addSuccessFlash(data.message || 'Sync completed successfully.');
                setTimeout(function () { window.location.reload(); }, 1500);
            } else {
                Mautic.addFailureFlash(data.error || 'Sync failed.');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-refresh-line"></i> Run Sync Now';
            Mautic.addFailureFlash('Sync request failed.');
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Init on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        loadSuppressions: loadSuppressions,
        runSync: runSync
    };
})();
