let monitorShowTimeout;
let monitorUpdateTimeout;
let monitorSearchTimeout;
let selectedMonitorId;
let monitorChart;


function printMonitors(data, level=0, searchText='') {
	let HTML = '';
	for (var key in data) {
		const monitor = data[key];
		if (!monitor || !monitor.id) continue;
		if (monitor.type == 1) {
			if (monitor.children) {
				// Folder
				HTML += '<div class="row monitor-folder mb-1" data-monitor-id="'+monitor.id+'">';
				let show = false;
				if (localStorage.getItem("collapseOpened_collapseMonitors_" + monitor.id) === "true") {
					show = true;
				}
				HTML += '<div class="col-9 col-md-8'+(show ? '':' collapsed')+' monitor-title" data-search-text="'+searchText + monitor.title +'" data-monitor-id="'+monitor.id+'" aria-controls="collapseMonitors_'+monitor.id+'">';
				HTML += '<span class="ml-padding-'+level+'"></span><span class="badge rounded-pill monitor-badge '+(monitor.badge_class)+'" title="24-hours" data-monitor-id="'+monitor.id+'">'+monitor.availability_percent+'%</span>';
				HTML += '<i class="bi bi-caret-down"></i>';
				HTML += monitor.title;
				HTML += '</div>';
				HTML += '<div class="col-3 col-md-4"><div class="wrap"><div class="monitor-states" data-monitor-id="'+monitor.id+'">';
				let statesDiv = $('.monitor-states[data-monitor-id="'+monitor.id+'"]')
				let old_states = {};
				if (statesDiv.length > 0) {
					const children = $(statesDiv[0]).children('div[data-min-time]');
					for (var i = 0; i < children.length; i++) {
						const obj = $(children.eq(i));
						old_states[obj.data('min-time')] = {
							min_time: obj.data('min-time'),
							class: obj.attr('class'),
							title: obj.attr('title'),
						};
					}
				}
				for (var time in monitor.states) {
					const state = monitor.states[time];
					old_states[time] = state;
				}
				for (var time in old_states) {
					const state = old_states[time];
					HTML += '<div data-min-time="'+ state.min_time + '" class="' + state.class + '" title="' + state.title + '"></div>';
				}
				HTML += '</div></div></div>';

				HTML += '</div>';
				HTML += '<div class="collapse'+(show ? ' show' : '')+'" id="collapseMonitors_'+monitor.id+'">';
				HTML += printMonitors(monitor.children, level + 1, searchText + monitor.title + ' | ' );
				HTML += '</div>';
			}
		} else {
			// Monitor
			HTML += '<div class="row monitor-item mb-1" data-monitor-id="'+monitor.id+'"><div class="col-9 col-md-8 monitor-title pointer" data-search-text="'+searchText + monitor.title +'" data-monitor-id="'+monitor.id+'">';
			HTML += '<span class="ml-padding-'+level+'"></span><span class="badge rounded-pill monitor-badge '+(monitor.badge_class)+'" title="24-hours" data-monitor-id="'+monitor.id+'">'+monitor.availability_percent+'%</span>'+"\n";
			HTML += monitor.title;
			HTML += '</div>';
			HTML += '<div class="col-3 col-md-4"><div class="wrap"><div class="monitor-states" data-monitor-id="'+monitor.id+'">';
			let statesDiv = $('.monitor-states[data-monitor-id="'+monitor.id+'"]')
			let old_states = {};
			if (statesDiv.length > 0) {
				const children = $(statesDiv[0]).children('div[data-min-time]');
				for (var i = 0; i < children.length; i++) {
					const obj = $(children.eq(i));
					old_states[obj.data('min-time')] = {
						min_time: obj.data('min-time'),
						class: obj.attr('class'),
						title: obj.attr('title'),
					};
				}
			}
			for (var time in monitor.states) {
				const state = monitor.states[time];
				old_states[time] = state;
			}
			for (var time in old_states) {
				const state = old_states[time];
				HTML += '<div data-min-time="'+ state.min_time + '" class="' + state.class + '" title="' + state.title + '"></div>';
			}
			HTML += '</div></div></div></div>';
		}
	}
	return HTML;
}

function MonitorsUpdate(count=25) {
	clearTimeout(monitorUpdateTimeout);
	wss.send('monitors_update',{'count':count},(response)=>{
		if (response.message == false) {
			console.log(response);
		} else {
			if (response.message) {
				const html = printMonitors(response.message, 0);
				$('div.monitor-list-items>div.col').html(html);
				filterAndHideMonitors();
			}
		}
	});
	monitorUpdateTimeout = setTimeout(function() {
		MonitorsUpdate(5);
	}, 30000);
}

function filterAndHideMonitors() {
	searchText = document.getElementById('monitorSearch').value;
	console.log('filterAndHideMonitors', searchText);
	const allMonitors = document.querySelectorAll('.monitor-item, .monitor-folder');
	let hasResults = false;

	allMonitors.forEach(monitor => {
		const title = monitor.querySelector('.monitor-title');
		const text = title ? (title.dataset.searchText || title.textContent || title.innerText) : '';
		const matches = searchText === '' ||
					text.toLowerCase().includes(searchText.toLowerCase());

		if (matches) {
			$(monitor).show();
			hasResults = true;

			// Показываем родительские контейнеры
			showParentContainers(monitor);
		} else {
			$(monitor).hide();
		}
	});

	if (searchText === '') {
		restoreMonitorsCollapseFromLocalStorage();
	}

	// Показать/скрыть сообщение "нет результатов"
	const noResults = document.getElementById('noResults') || createNoResultsMessage();
	noResults.style.display = hasResults || searchText === '' ? 'none' : 'block';
}

function showParentContainers(element) {
	let parent = element.parentElement;
	searchText = document.getElementById('monitorSearch').value;
	while (parent && !parent.classList.contains('monitor-list-items')) {
		if (parent.classList.contains('collapse')) {
			// $(parent).show();
			if (searchText !== '') {
				$(parent).collapse('show');
			}
			
			// Показать заголовок папки
			const folderId = parent.id.split('_')[1];
			const folder = document.querySelector(`.monitor-folder[data-monitor-id="${folderId}"]`);
			const folderTitle = document.querySelector(`.monitor-folder[data-monitor-id="${folderId}"]>.monitor-title`);
			if (folder && folderTitle) {
				$(folder).show();
				if (searchText !== '') {
					$(folderTitle).removeClass('collapsed');
				}
			}
		}
		parent = parent.parentElement;
	}
}

function createNoResultsMessage() {
	const message = document.createElement('div');
	message.id = 'noResults';
	message.textContent = monitorsNotFound;
	message.style.cssText = 'text-align: center; padding: 20px; color: #666;';

	const container = document.querySelector('.monitor-list-items');
	container.appendChild(message);
	return message;
}

function clearSearch() {
	document.getElementById('monitorSearch').value = '';
	filterAndHideMonitors('');
}

function restoreMonitorsCollapseFromLocalStorage() {
	$('.monitor-folder>.monitor-title').each(function() {
		const collapseId = $(this).attr('aria-controls');
		const collapseObj = $(`#${collapseId}`);
		if (localStorage.getItem("collapseOpened_" + collapseId) === "true") {
			collapseObj.collapse('show');
			$(this).removeClass('collapsed');
		} else {
			collapseObj.collapse('hide');
			$(this).addClass('collapsed');
		}
	});
}

function MonitorsCollapse() {
	$('body').delegate('.monitor-folder>.monitor-title', 'click', function(){
		const collapseId = $(this).attr('aria-controls');
		const collapseObj = $(`#${collapseId}`);
		if (collapseObj.hasClass('show')) {
			collapseObj.collapse('hide');
			$(this).addClass('collapsed');
			localStorage.removeItem("collapseOpened_" + collapseId);
		} else {
			collapseObj.collapse('show');
			$(this).removeClass('collapsed');
			localStorage.setItem("collapseOpened_" + collapseId, true);
		}
	});
}

function updateMonitorWorkersLogs(data) {
	const monitorWorkersLogs = $('#monitor-workers-logs');
	const keys = Object.keys(data.logs).sort((a, b) => a - b);
	const last40Keys = keys.slice(-40);
	HTML = '';
	for (let worker_id in data.workers) {
		const worker = data.workers[worker_id];
		const logs = data.logs[worker_id];
		HTML += '<div class="row">';
			HTML += '<div class="col-3 col-sm-2 align-middle lh-3">'+worker.title+'</div><div class="col-9 col-sm-10">';
				HTML += '<div class="wrap"><div class="monitor-states-big" data-monitor-id="7">';
					for (let i in last40Keys) {
						const time = last40Keys[i];
						const log_workers = data.logs[time];
						const log = log_workers[worker_id];
						let className = 'bg-secondary';
						let title = log_workers.title;
						if (log) {
							if (log.status == 0) {
								className = "bg-danger";
							} else if (log['status'] == 1) {
								className = "bg-success";
							}
							title = log.title;
						}
						HTML += '<div data-min-time="'+ time + '" class="' + className + '" title="' + title + '"></div>';
					}
				HTML += '</div></div>';
			HTML += '</div>';
		HTML += '</div>';
	}
	monitorWorkersLogs.html(HTML);
}

function updateMonitorLastInfo(data) {
	const monitorWorkersLogs = $('#monitor-last-info');
	HTML = '';
	HTML += '<div class="card monitor-info mb-3">';
	HTML += '<div class="card-body row">';
		HTML += '<div class="center col-4 col-lg mb-3">';
			HTML += Dashboard_Response+'<br/>';
			HTML += '<span class="text-secondary fs-7">('+Dashboard_Last+')</span><br/>';
			HTML += (data['avg_response_time'] ? data['avg_response_time'] : '');
		HTML += '</div>';

		HTML += '<div class="center col-4 col-lg mb-3">';
			HTML += Dashboard_AVGResponse+'<br/>';
			HTML += '<span class="text-secondary fs-7">('+Dashboard_24Hours+')</span><br/>';
			HTML += (data['avg_response_time_1d'] ? data['avg_response_time_1d'] : '');
		HTML += '</div>';

		HTML += '<div class="center col-4 col-lg mb-3">';
			HTML += Dashboard_Uptime+'<br/>';
			HTML += '<span class="text-secondary fs-7">('+Dashboard_24Hours+')</span><br/>';
			HTML += (data['uptime_1'] ? data['uptime_1']+'%' : '');
		HTML += '</div>';
		HTML += '<div class="center col-4 col-lg mb-3">';
			HTML += Dashboard_Uptime+'<br/>';
			HTML += '<span class="text-secondary fs-7">('+Dashboard_30Days+')</span><br/>';
			HTML += (data['uptime_30'] ? data['uptime_30']+'%' : '');
		HTML += '</div>';

		if (data['cert_expire']) {
			HTML += '<div class="center col-4 col-lg mb-3">'+Dashboard_CertExpire+'<br/>';
			HTML += '<span class="text-secondary fs-7">('+(data['cert_expire'] ? data['cert_expire'] : '')+')</span><br/>';
			HTML += (data['cert_expire_days'] ? data['cert_expire_days'] : '')+'</div>';
		}
	HTML += '</div>';
	HTML += '</div>';
	monitorWorkersLogs.html(HTML);
}

function printMonitorData(data, period) {
	let HTML = '';
	data.monitor = data.monitor || {};
	$('title').html('Monitoring - Monitor: ' + data.monitor.title);
	data.logs = data.logs || {};
	data.workers = data.workers || {};
	cardObj = $('div.monitor-dashboard div.card#monitor-data-'+data.monitor.id);

	if (!cardObj || cardObj.length < 1) {
		HTML += '<div class="card" id="monitor-data-'+data.monitor.id+'">';
		HTML += '<div class="card-header bg-transparent"><h5>'+data.monitor.title+'</h5></div>';
		HTML += '<div class="card-body">';
		HTML += '<div id="monitor-workers-logs"></div>';
		HTML += '<div id="monitor-last-info"></div>';
		HTML += '<div id="monitor-charts">';
			HTML += '<div class="card chart mb-3">';
				HTML += '<div class="card-body">';
					HTML += '<div class="float-end"><select data-width="fit" data-container="body" data-style-base="btn btn-sm" name="period" class="selectpicker" id="chart-period">';
					HTML += '<option value="0.5" '+(period == "0.5" ? 'selected':'')+'>'+Dashboard_Charts_30Minutes+'</option>';
					HTML += '<option value="1" '+(period == "1" ? 'selected':'')+'>'+Dashboard_Charts_1Hour+'</option>';
					HTML += '<option value="4" '+(period == "4" ? 'selected':'')+'>'+Dashboard_Charts_4Hours+'</option>';
					HTML += '<option value="6" '+(period == "6" ? 'selected':'')+'>'+Dashboard_Charts_6Hours+'</option>';
					HTML += '<option value="24" '+(period == "24" ? 'selected':'')+'>'+Dashboard_Charts_24Hours+'</option>';
					HTML += '</select></div>';
					HTML += '<div class="clearfix"></div>';
					HTML += '<div id="MonitorEChart" class="MonitorEChart"></div>';
				HTML += '</div>';
			HTML += '</div>';
		HTML += '</div>';
		HTML += '</div>';
		HTML += '</div>';
		$('div.monitor-dashboard').html(HTML);
		$('#chart-period').selectpicker('render');
		initECharts();
	}
	updateMonitorWorkersLogs(data);
	updateMonitorLastInfo(data);
	updateECharts(data);

}

function initECharts() {
	const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
	const chartDom = document.getElementById('MonitorEChart');
	monitorChart = echarts.init(chartDom, isDark ? 'dark' : 'light');
	const option = {
		backgroundColor: 'rgba(0,0,0,0)',
		darkMode: isDark,
		tooltip: {
			trigger: 'axis',
			axisPointer: {
				type: 'cross',
				label: {
					backgroundColor: '#0dcaf0'
				}
			},
			formatter: function(params) {
				let result = params[0].axisValue + '<br/>';
				params.forEach(function(item) {
					if (item.value != null) {
						let valueText = item.value >= 1000 ? 
							(item.value / 1000).toFixed(2) + ' s' : 
							item.value + ' ms';
						result += item.seriesName + ': ' + valueText + '<br/>';
					}
				});
				return result;
			}
		},
		
		 toolbox: {
			show: true,
			feature: {
			saveAsImage: {}
			}
		},

		grid: {
			left: '3%',
			right: '4%',
			bottom: '60px',
			top: '60px',
			containLabel: true
		},
		
		yAxis: {
			type: 'value',
			axisLabel: {
				formatter: function(value) {
					if (value >= 1000) {
						return (value / 1000) + ' s';
					} else {
						return value + ' ms';
					}
				}
			},
			name: Dashboard_Charts_ResponseTime,
			nameLocation: 'end',
			nameGap: 15
		},

		dataZoom: [
			{
				type: 'inside',
				start: 0,
				end: 100
			},
			{
				type: 'slider',
				start: 0,
				end: 100
			}
		],
		
		legend: {
			data: Object.values({}).map(w => w.title),
			top: 10,
			type: 'scroll'
		},
		
		xAxis: {
			type: 'category',
			boundaryGap: true,
			data: []
		},

		series: []
	};
	monitorChart.setOption(option);
	// Обработчик изменения размера окна
	window.addEventListener('resize', function() {
		monitorChart.resize();
	});
	window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
		const isDark = e.matches;
		
		// Сохраняем текущие опции графика
		const currentOption = monitorChart.getOption();
		console.log(currentOption);
		// Меняем тему
		monitorChart.setTheme(isDark ? 'dark' : 'light');
		
		// Обновляем опции с учетом новой темы
		monitorChart.setOption({
			backgroundColor: isDark ? 'rgba(0,0,0,1)' : 'rgba(255,255,255,0)',
			darkMode: isDark
		});
		
		// Применяем заново текущие данные
		monitorChart.setOption(currentOption);
		
		// Перерисовываем график
		monitorChart.resize();
	});
}


function updateECharts(data) {
	let series = [];
	let xAxisData = [];

	function findNoDataPeriods(dataArray, statusData, timeKeys) {
		const periods = [];
		let currentPeriod = null;
		
		for (let i = 0; i < dataArray.length; i++) {
			if (dataArray[i] === null || statusData[i] === 0) {
				if (currentPeriod === null) {
					currentPeriod = { 
						start: timeKeys[i], 
						startIndex: i,
						status: statusData[i],
						color: dataArray[i] === null ? 'rgba(255, 193, 7, 0.1)' : 'rgba(220, 53, 69, 0.1)',
					};
				}
				currentPeriod.end = timeKeys[i];
				currentPeriod.endIndex = i;
			} else {
				if (currentPeriod !== null) {
					// Добавляем период только если он длится более 1 точки
					if (currentPeriod.endIndex - currentPeriod.startIndex > 0) {
						periods.push([
							{
								xAxis: (new Date(currentPeriod.start * 1000)).toLocaleTimeString(),
								itemStyle: {
									color: currentPeriod.color
								},
							},
							{
								xAxis: (new Date(currentPeriod.end * 1000)).toLocaleTimeString(),
							}
						]);
					}
					currentPeriod = null;
				}
			}
		}
		
		// Добавляем последний период, если он есть и достаточно длинный
		if (currentPeriod !== null && currentPeriod.endIndex - currentPeriod.startIndex > 0) {
			periods.push([
				{ 
					xAxis: (new Date(currentPeriod.start * 1000)).toLocaleTimeString(),
					itemStyle: {
						color: currentPeriod.color
					},
				},
				{ 
					xAxis: (new Date(currentPeriod.end * 1000)).toLocaleTimeString(),
				}
			]);
		}
		
		return periods;
	}

	// Получаем отсортированные ключи времени
	const timeKeys = Object.keys(data.logs_chart).sort((a, b) => a - b);



	// Подготовка данных для ECharts
	for (let worker_id in data.workers) {
		const worker = data.workers[worker_id];
		let workerData = [];
		let statusData = [];
		let noDataPeriods = [];
		let lastError = false;
		// Собираем данные по времени
		for (let i = 0; i < timeKeys.length; i++) {
			const time = timeKeys[i];
			if (xAxisData.length < timeKeys.length) {
				// Форматируем дату для отображения
				const date = new Date(time * 1000);
				xAxisData.push(date.toLocaleTimeString());
			}
			
			const log_workers = data.logs_chart[time];
			let log = log_workers[worker_id];
			
			if (!log) {
				log = {
					'status': 0,
					'time': null
				};
			}
			
			if (log && log['time'] != null) {
				workerData.push(log['time']);
				if (log['status'] == 1) {
					lastError = false;
				}
			} else {
				// Не выполнять для последнего элемента
				if (!lastError && i < timeKeys.length - 1) {
					noDataPeriods.push([
						{
							name: "No Data",
							xAxis: (new Date(time * 1000)).toLocaleTimeString(),
							y: '80%',
							symbol: 'none',
							lineStyle: {
								color: "rgba(255, 193, 7, 0.5)",
								type: "solid",
								symbol: 'none',
								width: 3,
							}
						},
						{
							xAxis: (new Date(time * 1000)).toLocaleTimeString(),
							y: '15%',
							symbol: 'none',
						}
					]);
					lastError = true;
				}
				workerData.push(null); // ECharts использует null для пропущенных данных
			}
			statusData.push(Number(log['status'] ? log['status'] : 0));
			if (Number(log['status']) == 0 && log['time'] != null) {
				if (!lastError) {
					noDataPeriods.push([
						{
							name: "Error",
							xAxis: (new Date(time * 1000)).toLocaleTimeString(),
							y: '80%',
							symbol: 'none',
							lineStyle: {
								color: "rgba(220, 53, 69, 1)",
								type: "solid",
								symbol: 'none',
								width: 3,
							}
						},
						{
							xAxis: (new Date(time * 1000)).toLocaleTimeString(),
							y: '15%',
							symbol: 'none',
						}
					]);
					lastError = true;
				}
			}
		}
		const noDataPeriodsArea = findNoDataPeriods(workerData, statusData, timeKeys);

		series.push({
			name: worker.title,
			type: 'line',
			data: workerData,
			symbol: 'circle',
			symbolSize: 4,
			connectNulls: false,
			markLine: noDataPeriods.length > 0 ? {
				data: noDataPeriods,
			} : undefined,
			markArea: noDataPeriodsArea.length > 0 ? {
				itemStyle: {
					color: 'rgba(220, 53, 69, 0.1)'
				},
				data: noDataPeriodsArea
			} : undefined

		});
	}

	const option = {
		legend: {
			data: Object.values(data.workers).map(w => w.title),
			top: 10,
			type: 'scroll'
		},
		xAxis: {
			type: 'category',
			boundaryGap: true,
			data: xAxisData
		},
		series: series
	}

	monitorChart.setOption(option);
}

function MonitorShowData(monitorId, period=1) {
	clearTimeout(monitorShowTimeout);
	selectedMonitorId = monitorId;
	wss.send('monitor_show_data',{'monitor_id':monitorId, 'period': period},(response)=>{
		if (response.message == false) {
			console.log(response);
		} else {
			if (response.message) {
				printMonitorData(response.message, period);
			}
		}
	});
	monitorShowTimeout = setTimeout(function() {
		MonitorShowData(monitorId, period);
	}, 60000);
}

function showMonitorDashboard() {
	const navButtons = document.querySelectorAll('button[data-target]');
	const monitorList = document.getElementById('monitor-list');
	const monitorDashboard = document.getElementById('monitor-dashboard');

	navButtons.forEach(btn => btn.classList.remove('active'));

	navButtons.forEach(button => {
		const target = button.getAttribute('data-target');
		if (target === 'monitor-dashboard') {
			button.classList.add('active');
		}
	});
	if (window.innerWidth < 992) {
		monitorList.classList.remove('active');
		monitorDashboard.classList.add('active');
	} else {
		monitorList.classList.add('active');
		monitorDashboard.classList.add('active');
	}

}

function list_dashboard_mobile(dashboard_active=false) {
	const navButtons = document.querySelectorAll('button[data-target]');
	const monitorList = document.getElementById('monitor-list');
	const monitorDashboard = document.getElementById('monitor-dashboard');

	if (window.innerWidth < 992) {
		monitorList.classList.remove('active');
		monitorDashboard.classList.remove('active');
		if (dashboard_active) {
			monitorDashboard.classList.add('active');
		} else {
			monitorList.classList.add('active');
		}
	} else {
		monitorList.classList.add('active');
		monitorDashboard.classList.add('active');
	}

	navButtons.forEach(button => {
		button.addEventListener('click', function() {
			const target = this.getAttribute('data-target');
			
			// Убираем активный класс у всех кнопок
			navButtons.forEach(btn => btn.classList.remove('active'));
			// Добавляем активный класс текущей кнопке
			this.classList.add('active');
			
			// Скрываем все блоки
			monitorList.classList.remove('active');
			monitorDashboard.classList.remove('active');
			
			// Показываем выбранный блок
			if (target === 'monitor-list') {
				monitorList.classList.add('active');
			} else if (target === 'monitor-dashboard') {
				monitorDashboard.classList.add('active');
			}
		});
	});

	 window.addEventListener('resize', function() {
		if (window.innerWidth >= 992) {
			// На больших экранах показываем оба блока
			monitorList.classList.add('active');
			monitorDashboard.classList.add('active');
		} else {
			// На маленьких экранах показываем только активный блок
			const activeButton = document.querySelector('[data-target].active');
			if (activeButton) {
				const target = activeButton.getAttribute('data-target');
				monitorList.classList.remove('active');
				monitorDashboard.classList.remove('active');
				
				if (target === 'monitor-list') {
					monitorList.classList.add('active');
				} else if (target === 'monitor-dashboard') {
					monitorDashboard.classList.add('active');
				}
			}
		}
	});
}

$(document).ready(function() {
	$('body').delegate('.monitor-item>.monitor-title', 'click', function(){
		Loading = '<div class="d-flex justify-content-center"><div class="spinner-border text-warning" role="status"><span class="visually-hidden">Loading...</span></div></div>';
		$('div.monitor-dashboard').html(Loading);
		history.pushState({'action': 'monitor', 'monitorId': $(this).data('monitor-id')}, null, "/monitor/" + $(this).data('monitor-id') + "/");
		selectedMonitorId = $(this).data('monitor-id');
		MonitorShowData($(this).data('monitor-id'));
		showMonitorDashboard();
		document.querySelectorAll('body')[0].scrollIntoView();
	});
	$('body').delegate('#chart-period', 'change', function() {
		MonitorShowData(selectedMonitorId, $(this).val());
	});

	$('#monitorSearch').on('input', function() {
		clearTimeout(monitorSearchTimeout);
		monitorSearchTimeout = setTimeout(function() {
			filterAndHideMonitors();
		}, 500);
	});

	// Обработчик события popstate (когда пользователь нажимает назад/вперед)
	window.addEventListener('popstate', function(event) {
		if (event.state && event.state.action && event.state.action == 'monitor' && event.state.monitorId) {
			Loading = '<div class="d-flex justify-content-center"><div class="spinner-border text-warning" role="status"><span class="visually-hidden">Loading...</span></div></div>';
			$('div.monitor-dashboard').html(Loading);
			selectedMonitorId = event.state.monitorId;
			MonitorShowData(event.state.monitorId);
		}
	});

});