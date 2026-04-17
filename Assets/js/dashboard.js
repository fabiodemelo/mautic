var SyncData = (function () {
    'use strict';

    var breakdownChart = null;
    var trendChart = null;

    function init() {
        renderBreakdownChart();
        renderTrendChart();
        bindEvents();
    }

    function renderBreakdownChart() {
        var ctx = document.getElementById('sd-breakdown-chart');
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
        var ctx = document.getElementById('sd-trend-chart');
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
        document.querySelectorAll('.sd-period').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelectorAll('.sd-period').forEach(function (p) { p.classList.remove('active'); });
                this.classList.add('active');
                var period = this.dataset.period;
                var value = period === '24h' ? sgsStats.new_24h :
                            period === '7d' ? sgsStats.new_7d : sgsStats.new_30d;
                document.getElementById('sd-new').textContent = value.toLocaleString();
            });
        });

        // Trend chart filters
        var periodSelect = document.getElementById('sd-trend-period');
        var typeSelect = document.getElementById('sd-trend-type');

        if (periodSelect) {
            periodSelect.addEventListener('change', function () { updateTrendChart(); });
        }
        if (typeSelect) {
            typeSelect.addEventListener('change', function () { updateTrendChart(); });
        }

        // Suppressions search
        var searchInput = document.getElementById('sd-search-email');
        var filterType = document.getElementById('sd-filter-type');
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
        var period = document.getElementById('sd-trend-period').value;
        var type = document.getElementById('sd-trend-type').value;

        var url = mauticBaseUrl + 's/plugins/syncdata/dashboard/chart/trend?period=' + period;
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
        var email = (document.getElementById('sd-search-email') || {}).value || '';
        var type = (document.getElementById('sd-filter-type') || {}).value || '';

        var url = mauticBaseUrl + 's/plugins/syncdata/dashboard/suppressions?page=' + page;
        if (email) url += '&email=' + encodeURIComponent(email);
        if (type) url += '&type=' + encodeURIComponent(type);

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                renderSuppressions(data);
            });
    }

    function renderSuppressions(data) {
        var tbody = document.getElementById('sd-suppressions-body');
        if (!tbody) return;

        var html = '';
        data.items.forEach(function (item) {
            var emailCell = item.contact_id
                ? '<a href="' + mauticBaseUrl + 's/contacts/view/' + item.contact_id + '">' + escapeHtml(item.email) + '</a>'
                : escapeHtml(item.email);

            var actionClass = item.action === 'dnc' ? 'danger' : (item.action === 'segment' ? 'info' : 'default');

            html += '<tr>' +
                '<td>' + emailCell + '</td>' +
                '<td><span class="label sd-type-' + item.type + '">' + escapeHtml(item.type_label) + '</span></td>' +
                '<td class="sd-reason-cell" title="' + escapeHtml(item.reason || '') + '">' + escapeHtml((item.reason || '-').substring(0, 60)) + '</td>' +
                '<td>' + escapeHtml(item.source_date) + '</td>' +
                '<td>' + escapeHtml(item.synced_date) + '</td>' +
                '<td><span class="label label-' + actionClass + '">' + item.action.toUpperCase() + '</span></td>' +
                '</tr>';
        });

        tbody.innerHTML = html || '<tr><td colspan="6" class="text-center">No results found</td></tr>';

        // Update pagination
        var pagDiv = document.getElementById('sd-suppressions-pagination');
        if (pagDiv && data.pages > 1) {
            var pagHtml = '<nav><ul class="pagination pagination-sm">';
            for (var p = 1; p <= data.pages; p++) {
                pagHtml += '<li class="' + (p === data.page ? 'active' : '') + '">' +
                    '<a href="#" onclick="SyncData.loadSuppressions(' + p + '); return false;">' + p + '</a></li>';
            }
            pagHtml += '</ul></nav>';
            pagDiv.innerHTML = pagHtml;
        } else if (pagDiv) {
            pagDiv.innerHTML = '';
        }
    }

    function showSyncOverlay() {
        if (document.getElementById('sd-sync-overlay')) return;

        var elapsedStart = Date.now();
        var overlay = document.createElement('div');
        overlay.id = 'sd-sync-overlay';
        overlay.innerHTML =
            '<div class="sd-sync-modal">' +
                '<div class="sd-spinner"><div></div><div></div><div></div><div></div></div>' +
                '<h3 class="sd-sync-title">Sync in progress</h3>' +
                '<p class="sd-sync-msg">Fetching suppressions from SendGrid and applying them to Mautic.<br>This can take several minutes for large lists — please don\'t close this page.</p>' +
                '<div class="sd-sync-elapsed"><i class="ri-time-line"></i> <span id="sd-sync-time">0s</span> elapsed</div>' +
            '</div>';
        document.body.appendChild(overlay);

        // Live elapsed timer
        overlay.dataset.timer = setInterval(function () {
            var sec = Math.floor((Date.now() - elapsedStart) / 1000);
            var label = sec < 60
                ? sec + 's'
                : Math.floor(sec / 60) + 'm ' + (sec % 60) + 's';
            var el = document.getElementById('sd-sync-time');
            if (el) el.textContent = label;
        }, 1000);
    }

    function hideSyncOverlay() {
        var overlay = document.getElementById('sd-sync-overlay');
        if (!overlay) return;
        if (overlay.dataset.timer) clearInterval(overlay.dataset.timer);
        overlay.remove();
    }

    function runSync() {
        var btn = document.getElementById('sd-sync-btn');
        if (!btn) return;

        btn.disabled = true;
        btn.innerHTML = '<i class="ri-loader-4-line sd-icon-spin"></i> Syncing...';
        showSyncOverlay();

        fetch(mauticBaseUrl + 's/plugins/syncdata/sync/run', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': mauticAjaxCsrf
            }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            hideSyncOverlay();
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-refresh-line"></i> Run Sync Now';

            if (data.success) {
                if (typeof Mautic !== 'undefined' && Mautic.addSuccessFlash) {
                    Mautic.addSuccessFlash(data.message || 'Sync completed successfully.');
                }
                setTimeout(function () { window.location.reload(); }, 1200);
            } else {
                if (typeof Mautic !== 'undefined' && Mautic.addFailureFlash) {
                    Mautic.addFailureFlash(data.error || 'Sync failed.');
                } else {
                    alert('Sync failed: ' + (data.error || 'Unknown error'));
                }
            }
        })
        .catch(function (err) {
            hideSyncOverlay();
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-refresh-line"></i> Run Sync Now';
            alert('Sync request failed: ' + (err && err.message ? err.message : 'network error'));
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
