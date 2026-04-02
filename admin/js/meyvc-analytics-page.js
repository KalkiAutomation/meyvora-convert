/**
 * Analytics dashboard: Chart.js defaults, charts, table sort, A/B row expand, copy URL.
 */
(function ($) {
	'use strict';

	var meyvc_chart_defaults = {
		responsive: true,
		maintainAspectRatio: false,
		plugins: { legend: { position: 'bottom' } },
		scales: {
			x: { grid: { display: false } },
			y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
		},
	};

	function readBootstrap() {
		if (typeof meyvcAnalyticsBootstrap !== 'undefined' && meyvcAnalyticsBootstrap && typeof meyvcAnalyticsBootstrap === 'object') {
			return meyvcAnalyticsBootstrap;
		}
		var el = document.getElementById('meyvc-analytics-bootstrap');
		if (!el || !el.textContent) {
			return {};
		}
		try {
			return JSON.parse(el.textContent);
		} catch (e) {
			return {};
		}
	}

	function truncate(str, max) {
		str = String(str || '');
		return str.length > max ? str.slice(0, max - 1) + '…' : str;
	}

	function bindTableSort(table) {
		if (!table) {
			return;
		}
		var thead = table.querySelector('thead');
		if (!thead) {
			return;
		}
		var ths = thead.querySelectorAll('th[data-sort-key]');
		var sortState = { key: null, dir: 1 };

		ths.forEach(function (th) {
			th.style.cursor = 'pointer';
			th.addEventListener('click', function () {
				var key = th.getAttribute('data-sort-key');
				if (!key) {
					return;
				}
				sortState.dir = sortState.key === key ? -sortState.dir : 1;
				sortState.key = key;
				var tbody = table.querySelector('tbody');
				if (!tbody) {
					return;
				}
				var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
				rows.sort(function (a, b) {
					var da = a.querySelector('td[data-sort="' + key + '"]');
					var db = b.querySelector('td[data-sort="' + key + '"]');
					var va = da ? da.getAttribute('data-sort-value') : '';
					var vb = db ? db.getAttribute('data-sort-value') : '';
					if (va !== '' && vb !== '' && !isNaN(+va) && !isNaN(+vb)) {
						return (parseFloat(va) - parseFloat(vb)) * sortState.dir;
					}
					var ta = da ? da.textContent : '';
					var tb = db ? db.textContent : '';
					return String(ta).localeCompare(String(tb)) * sortState.dir;
				});
				rows.forEach(function (r) {
					tbody.appendChild(r);
				});
			});
		});
	}

	function initMainCharts(b) {
		var dailyData = b.dailyStats || [];
		var deviceData = b.devices || [];

		var mainEl = document.getElementById('meyvc-main-chart');
		if (mainEl && typeof Chart !== 'undefined' && dailyData && dailyData.length) {
			var mainCtx = mainEl.getContext('2d');
			var mainChart = new Chart(mainCtx, {
				type: 'line',
				data: {
					labels: dailyData.map(function (d) {
						return d.label;
					}),
					datasets: [
						{
							label: b.strings && b.strings.conversions ? b.strings.conversions : 'Conversions',
							data: dailyData.map(function (d) {
								return d.conversions;
							}),
							borderColor: '#333',
							backgroundColor: 'rgba(0, 0, 0, 0.08)',
							fill: true,
							tension: 0.3,
						},
					],
				},
				options: Object.assign({}, meyvc_chart_defaults, {
					plugins: { legend: { display: false } },
					scales: { y: { beginAtZero: true, grid: { color: '#f0f0f0' } } },
				}),
			});

			document.querySelectorAll('.meyvc-chart-toggle button').forEach(function (btn) {
				btn.addEventListener('click', function () {
					document.querySelectorAll('.meyvc-chart-toggle button').forEach(function (x) {
						x.classList.remove('active');
					});
					btn.classList.add('active');
					var metric = btn.getAttribute('data-metric');
					var colors = { conversions: '#333', revenue: '#555', impressions: '#888' };
					mainChart.data.datasets[0].data = dailyData.map(function (d) {
						return d[metric];
					});
					mainChart.data.datasets[0].borderColor = colors[metric] || '#333';
					mainChart.data.datasets[0].backgroundColor = (colors[metric] || '#333') + '20';
					mainChart.data.datasets[0].label = btn.textContent.trim();
					mainChart.update();
				});
			});
		}

		var devEl = document.getElementById('meyvc-device-chart');
		if (devEl && typeof Chart !== 'undefined' && deviceData && deviceData.length) {
			var deviceCtx = devEl.getContext('2d');
			new Chart(deviceCtx, {
				type: 'doughnut',
				data: {
					labels: deviceData.map(function (d) {
						var dev = d.device || 'unknown';
						return dev.charAt(0).toUpperCase() + dev.slice(1);
					}),
					datasets: [
						{
							data: deviceData.map(function (d) {
								return d.conversions;
							}),
							backgroundColor: ['#333', '#555', '#888', '#aaa'],
						},
					],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: { legend: { position: 'bottom' } },
				},
			});
		}
	}

	function readDateRangeForAjax() {
		var f = document.querySelector('.meyvc-date-form input[name="from"]');
		var t = document.querySelector('.meyvc-date-form input[name="to"]');
		var sel = document.querySelector('#meyvc-campaign-filter');
		return {
			from: f ? f.value : '',
			to: t ? t.value : '',
			campaign_id: sel && sel.value ? String(sel.value) : '0',
		};
	}

	function applyRevenueChartSlice(state, byCamp, byOff) {
		if (!state || !state.chart) {
			return;
		}
		var byC = (byCamp || []).slice(0, 10);
		var byO = (byOff || []).slice(0, 10);
		state.campLabels = byC.map(function (r) {
			return truncate(r.label || '', 30);
		});
		state.campData = byC.map(function (r) {
			return r.revenue;
		});
		state.offLabels = byO.map(function (r) {
			return truncate(r.label || '', 30);
		});
		state.offData = byO.map(function (r) {
			return r.revenue;
		});
		if (state.mode === 'offer') {
			state.chart.data.labels = state.offLabels;
			state.chart.data.datasets[0].data = state.offData;
		} else {
			state.chart.data.labels = state.campLabels;
			state.chart.data.datasets[0].data = state.campData;
		}
		state.chart.update();
	}

	function bindAttributionModel(revenueChartState) {
		var radios = document.querySelectorAll('input[name="meyvc_attribution"]');
		if (!radios.length || typeof meyvcAnalyticsPage === 'undefined' || !meyvcAnalyticsPage.ajaxUrl) {
			return;
		}
		var setNonce = meyvcAnalyticsPage.setAttributionNonce || '';
		var revNonce = meyvcAnalyticsPage.getRevenueNonce || '';
		radios.forEach(function (radio) {
			radio.addEventListener('change', function () {
				if (!this.checked) {
					return;
				}
				var model = this.value;
				var dr = readDateRangeForAjax();
				$.ajax({
					url: meyvcAnalyticsPage.ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'meyvc_set_attribution_model',
						_wpnonce: setNonce,
						model: model,
					},
				}).done(function (res) {
					if (!res || !res.success) {
						return;
					}
					$.ajax({
						url: meyvcAnalyticsPage.ajaxUrl,
						type: 'POST',
						dataType: 'json',
						data: {
							action: 'meyvc_get_attributed_revenue',
							_wpnonce: revNonce,
							from: dr.from,
							to: dr.to,
							campaign_id: dr.campaign_id,
							model: model,
						},
					}).done(function (revRes) {
						if (!revRes || !revRes.success || !revRes.data) {
							return;
						}
						var d = revRes.data;
						var revHtml = d.revenue_formatted || d.revenue_html;
						var valEl = document.querySelector('.js-meyvc-revenue-kpi-value');
						if (valEl && revHtml) {
							valEl.innerHTML = revHtml;
						}
						var chEl = document.querySelector('.js-meyvc-revenue-kpi-change');
						if (chEl) {
							chEl.innerHTML = d.change_html || '';
						}
						var rpvEl = document.querySelector('.js-meyvc-rpv-stat-value');
						if (rpvEl && d.rpv_html) {
							rpvEl.innerHTML = d.rpv_html;
						}
						var tip = document.querySelector('.js-meyvc-revenue-kpi-tip');
						if (tip && d.tooltip) {
							tip.setAttribute('title', d.tooltip);
						}
						var lbl = document.querySelector('.meyvc-attribution-model-label');
						if (lbl && d.model_label) {
							lbl.textContent = d.model_label;
						}
						$.ajax({
							url: meyvcAnalyticsPage.ajaxUrl,
							type: 'POST',
							dataType: 'json',
							data: {
								action: 'meyvc_get_campaign_revenue_chart',
								_wpnonce: revNonce,
								from: dr.from,
								to: dr.to,
								campaign_id: dr.campaign_id,
								model: model,
							},
						}).done(function (chartRes) {
							if (!chartRes || !chartRes.success || !chartRes.data) {
								return;
							}
							var cd = chartRes.data;
							applyRevenueChartSlice(revenueChartState, cd.revenueByCampaign, cd.revenueByOffer);
							var tip2 = document.querySelector('.js-meyvc-revenue-kpi-tip');
							if (tip2 && cd.tooltip) {
								tip2.setAttribute('title', cd.tooltip);
							}
						});
					});
				});
			});
		});
	}

	function initRevenueChart(b) {
		var canvas = document.getElementById('meyvc-revenue-attribution-chart');
		if (!canvas || typeof Chart === 'undefined') {
			return null;
		}
		var byCamp = (b.revenueByCampaign || []).slice(0, 10);
		var byOff = (b.revenueByOffer || []).slice(0, 10);
		var strings = b.strings || {};

		var campLabels = byCamp.map(function (r) {
			return truncate(r.label || '', 30);
		});
		var campData = byCamp.map(function (r) {
			return r.revenue;
		});
		var offLabels = byOff.map(function (r) {
			return truncate(r.label || '', 30);
		});
		var offData = byOff.map(function (r) {
			return r.revenue;
		});

		var chart = new Chart(canvas.getContext('2d'), {
			type: 'bar',
			data: {
				labels: campLabels,
				datasets: [
					{
						label: strings.revenue || 'Revenue',
						data: campData,
						backgroundColor: '#1a73e8',
					},
				],
			},
			options: Object.assign({}, meyvc_chart_defaults, {
				indexAxis: 'y',
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							label: function (ctx) {
								var v = ctx.parsed.x;
								return strings.currencyPrefix != null
									? strings.currencyPrefix + v.toFixed(2)
									: String(v);
							},
						},
					},
				},
				scales: {
					x: { beginAtZero: true, grid: { color: '#f0f0f0' } },
					y: { grid: { display: false } },
				},
			}),
		});

		var state = {
			chart: chart,
			mode: 'campaign',
			strings: strings,
			campLabels: campLabels,
			campData: campData,
			offLabels: offLabels,
			offData: offData,
		};

		document.querySelectorAll('.js-meyvc-revenue-toggle').forEach(function (btn) {
			btn.addEventListener('click', function () {
				document.querySelectorAll('.js-meyvc-revenue-toggle').forEach(function (x) {
					x.classList.remove('active');
				});
				btn.classList.add('active');
				state.mode = btn.getAttribute('data-mode') || 'campaign';
				if (state.mode === 'offer') {
					chart.data.labels = state.offLabels;
					chart.data.datasets[0].data = state.offData;
				} else {
					chart.data.labels = state.campLabels;
					chart.data.datasets[0].data = state.campData;
				}
				chart.update();
			});
		});

		return state;
	}

	function bindAbExpand() {
		document.querySelectorAll('.js-meyvc-ab-summary-row').forEach(function (row) {
			row.addEventListener('click', function (e) {
				if (e.target.closest('a')) {
					return;
				}
				var id = row.getAttribute('data-test-id');
				var sub = document.querySelector('.js-meyvc-ab-expand[data-test-id="' + id + '"]');
				if (sub) {
					sub.hidden = !sub.hidden;
					row.classList.toggle('is-expanded', !sub.hidden);
				}
			});
		});
	}

	function bindCopyUrl() {
		document.querySelectorAll('.js-meyvc-copy-url').forEach(function (btn) {
			btn.addEventListener('click', function (ev) {
				ev.preventDefault();
				ev.stopPropagation();
				var url = btn.getAttribute('data-url') || '';
				if (!url || !navigator.clipboard || !navigator.clipboard.writeText) {
					return;
				}
				navigator.clipboard.writeText(url).then(function () {
					var prev = btn.textContent;
					btn.textContent =
						(window.meyvcAdmin && meyvcAdmin.strings && meyvcAdmin.strings.copied) || 'Copied!';
					setTimeout(function () {
						btn.textContent = prev;
					}, 1500);
				});
			});
		});
	}

	function bindDatePresets() {
		document.querySelectorAll('.meyvc-date-presets button').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var from = btn.getAttribute('data-from');
				var to = btn.getAttribute('data-to');
				var f = document.querySelector('input[name="from"]');
				var t = document.querySelector('input[name="to"]');
				if (f) {
					f.value = from;
				}
				if (t) {
					t.value = to;
				}
				var form = document.querySelector('.meyvc-date-form');
				if (form) {
					form.submit();
				}
			});
		});
	}

	$(function () {
		var b = readBootstrap();
		initMainCharts(b);
		var revenueState = initRevenueChart(b);
		bindAttributionModel(revenueState);
		bindTableSort(document.querySelector('.js-meyvc-top-pages-table'));
		bindAbExpand();
		bindCopyUrl();
		bindDatePresets();
	});
})(jQuery);
