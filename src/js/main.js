// Глобальные переменные для таймаутов и идентификатора выбранного монитора
let monitorShowTimeout;
let monitorUpdateTimeout;
let monitorSearchTimeout;
let monitorDirtyTimeout;
let selectedMonitorId;
let selectedMonitorPeriod = 1;
let monitorDirtyVersion = null;
let monitorChart;

/** Matches Monitors::TYPE_EXTERNAL — no response-time metrics/chart. */
const MONITOR_TYPE_EXTERNAL = 9;

function isExternalMonitor(monitor) {
	return monitor && Number(monitor.type) === MONITOR_TYPE_EXTERNAL;
}

/**
 * Prefer backend hide_response_time; fallback covers external/server/exporter types
 * (disk %, CPU/RAM %, net Mbps, offline min, exporters).
 */
function hidesResponseTimeUi(dataOrMonitor) {
	if (!dataOrMonitor) return false;
	if (typeof dataOrMonitor.hide_response_time !== 'undefined') {
		return !!dataOrMonitor.hide_response_time;
	}
	const monitor = dataOrMonitor.monitor || dataOrMonitor;
	if (!monitor) return false;
	if (isExternalMonitor(monitor)) return true;
	const t = Number(monitor.type);
	// Exporters 5–7; server 10–17 and 19–25 (skip 18 UDP which is worker-based)
	return (t >= 5 && t <= 7) || (t >= 10 && t <= 17) || (t >= 19 && t <= 25);
}

function isValueChartMonitor(data) {
	return data && data.chart_mode === 'value';
}

function chartUnitLabel(unit) {
	if (unit === '%') return '%';
	if (unit === 'Mbps') return 'Mbps';
	if (unit === 'min') return (typeof Dashboard_Charts_UnitMin !== 'undefined' ? Dashboard_Charts_UnitMin : 'min');
	return unit || '';
}

function formatChartValue(value, unit) {
	if (value == null || Number.isNaN(Number(value))) return '';
	const n = Number(value);
	if (unit === 'ms') {
		return n >= 1000 ? (n / 1000).toFixed(2) + ' s' : n + ' ms';
	}
	const rounded = Number.isInteger(n) ? String(n) : n.toFixed(2);
	const label = chartUnitLabel(unit);
	return label ? (rounded + ' ' + label) : rounded;
}

/**
 * Escape a string for use inside an HTML attribute (e.g. data-bs-title).
 * With data-bs-html="true", entities are decoded once by the browser before
 * Bootstrap renders the tooltip, so intentional markup like &lt;br/&gt; still works.
 */
function escapeHtmlAttr(value) {
	return String(value ?? '')
		.replace(/&/g, '&amp;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;');
}

/** Soft colors for open minute while worker data is still missing. */
function toSubtleStatusClass(className) {
	switch (className) {
		case 'bg-success':
		case 'bg-success-subtle':
			return 'bg-success-subtle';
		case 'bg-danger':
		case 'bg-danger-subtle':
			return 'bg-danger-subtle';
		case 'bg-warning':
		case 'bg-warning-subtle':
			return 'bg-warning-subtle';
		case 'bg-secondary':
		case 'bg-body-secondary':
			return 'bg-body-secondary';
		default:
			return className;
	}
}

/**
 * Рекурсивно генерирует HTML-представление списка мониторов.
 * @param {Object} data - Данные мониторов для отображения.
 * @param {number} [level=0] - Уровень вложенности для отступов.
 * @param {string} [searchText=''] - Текст для поиска, используется для атрибута data-search-text.
 * @returns {string} - Сгенерированный HTML.
 */
function printMonitors(data, level=0, searchText='') {
	// Инициализация HTML-строки
	let HTML = '';
	// Перебор всех мониторов в предоставленных данных
	for (var key in data) {
		const monitor = data[key];
		if (!monitor || !monitor.id) continue;
		if (monitor.type == 1) {
			if (monitor.children) {
				// Folder
				HTML += '<div class="row monitor-folder mb-1" data-monitor-id="'+monitor.id+'">';
				// Проверка, была ли папка открыта ранее (состояние из localStorage)
				let show = false;
				if (localStorage.getItem("collapseOpened_collapseMonitors_" + monitor.id) === "true") {
					show = true;
				}
				// Заголовок папки с отступом, значком доступности и иконкой сворачивания
				HTML += '<div class="col-9 col-md-8'+(show ? '':' collapsed')+' monitor-title" data-search-status="'+(monitor.badge_class)+'" data-search-text="'+searchText + monitor.title +'" data-monitor-id="'+monitor.id+'" aria-controls="collapseMonitors_'+monitor.id+'">';
				HTML += '<span class="ml-padding-'+level+'"></span><span class="badge rounded-pill monitor-badge '+(monitor.badge_class)+'" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="24-hours" data-monitor-id="'+monitor.id+'">'+monitor.availability_percent+'%</span>';
				HTML += '<i class="bi bi-caret-down"></i>';
				HTML += monitor.title;
				HTML += '</div>';
				// Блок с полосками состояний (uptime bars)
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
							title: obj.attr('data-bs-title'),
						};
					}
				}
				for (var time in monitor.states) {
					const state = monitor.states[time];
					old_states[time] = state;
				}

				const keys = Object.keys(old_states).sort((a, b) => a - b);
				const lastKeys = keys.slice(-25);
				for (var i in lastKeys) {
					const time = lastKeys[i];
					const state = old_states[time];
					HTML += '<div data-min-time="'+ state.min_time + '" class="' + state.class + '" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="' + escapeHtmlAttr(state.title) + '"></div>';
				}
				HTML += '</div></div></div>';

				HTML += '</div>';
				// Рекурсивный вызов для дочерних элементов
				HTML += '<div class="collapse'+(show ? ' show' : '')+'" id="collapseMonitors_'+monitor.id+'">';
				HTML += printMonitors(monitor.children, level + 1, searchText + monitor.title + ' | ' );
				HTML += '</div>';
			}
		} else {
			// Monitor
			HTML += '<div class="row monitor-item mb-1" data-monitor-id="'+monitor.id+'"><div class="col-9 col-md-8 monitor-title pointer" data-search-status="'+(monitor.badge_class)+'" data-search-text="'+searchText + monitor.title +'" data-monitor-id="'+monitor.id+'">';
			// Заголовок монитора с отступом и значком доступности
			HTML += '<span class="ml-padding-'+level+'"></span><span class="badge rounded-pill monitor-badge '+(monitor.badge_class)+'" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="24-hours" data-monitor-id="'+monitor.id+'">'+monitor.availability_percent+'%</span>'+"\n";
			HTML += monitor.title;
			HTML += '</div>';
			// Блок с полосками состояний (uptime bars)
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
						title: obj.attr('data-bs-title'),
					};
				}
			}
			for (var time in monitor.states) {
				const state = monitor.states[time];
				old_states[time] = state;
			}
			const keys = Object.keys(old_states).sort((a, b) => a - b);
			const lastKeys = keys.slice(-25);
			for (var i in lastKeys) {
				const time = lastKeys[i];
				const state = old_states[time];
				HTML += '<div data-min-time="'+ state.min_time + '" class="' + state.class + '" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="' + escapeHtmlAttr(state.title) + '"></div>';
			}
			HTML += '</div></div></div></div>';
		}
	}
	return HTML;
}

function hideAllTooltips() {
	const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
	tooltipTriggerList.forEach(tooltipTriggerEl => {
		const tooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl)
		if (tooltip) {
			tooltip.dispose(); // dispose() также скроет подсказку, если она видима
		}
	});
}

function applyAllTooltips() {
	const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
	tooltipTriggerList.forEach(tooltipTriggerEl => {
		const tooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl)
		if (!tooltip) {
			new bootstrap.Tooltip(tooltipTriggerEl)
		}
	});
}

/**
 * Запрашивает и обновляет список мониторов.
 * @param {number} [count=25] - Количество последних состояний для загрузки.
 */
function MonitorsUpdate(count=25) {
	clearTimeout(monitorUpdateTimeout);
	// Отправка запроса на обновление через WebSocket
	wss.send('monitors_update',{'count':count},(response)=>{
		if (response.message === undefined) {
			console.log(response);
		} else {
			// Сначала уничтожаем старые всплывающие подсказки, затем обновляем DOM
			hideAllTooltips();
			$('div.monitor-list-items>div.col').html(printMonitors(response.message, 0));
			applyAllTooltips();
			filterAndHideMonitors();
		}
	});
	// Safety-net full refresh; dirty-check triggers earlier updates on change.
	monitorUpdateTimeout = setTimeout(function() {
		MonitorsUpdate(4);
	}, 30000);
}

/**
 * Cheap version poll: full list/detail refresh when logs/events change or minute rolls.
 */
function MonitorsDirtyCheck() {
	clearTimeout(monitorDirtyTimeout);
	if (typeof wss === 'undefined' || !wss) {
		monitorDirtyTimeout = setTimeout(MonitorsDirtyCheck, 5000);
		return;
	}
	wss.send('monitors_dirty', {}, (response) => {
		const payload = response && response.message;
		const version = payload && payload.version != null ? String(payload.version) : null;
		if (version !== null) {
			if (monitorDirtyVersion === null) {
				monitorDirtyVersion = version;
			} else if (version !== monitorDirtyVersion) {
				monitorDirtyVersion = version;
				MonitorsUpdate(4);
				if (selectedMonitorId) {
					const periodEl = document.getElementById('chart-period');
					const period = periodEl ? periodEl.value : selectedMonitorPeriod;
					MonitorShowData(selectedMonitorId, period);
				}
			}
		}
	});
	monitorDirtyTimeout = setTimeout(MonitorsDirtyCheck, 5000);
}

/**
 * Фильтрует и скрывает мониторы на основе текста поиска.
 */
function filterAndHideMonitors() {
	searchText = document.getElementById('monitorSearch').value;
	searchStatus = $('#monitorStatus').val(); // Array

	// console.log('filterAndHideMonitors', searchText, searchStatus);
	const allMonitors = document.querySelectorAll('.monitor-item, .monitor-folder');
	let hasResults = false;

	allMonitors.forEach(monitor => {
		const title = monitor.querySelector('.monitor-title');
		// Получаем текст для поиска из специального атрибута или из содержимого
		const text = title ? (title.dataset.searchText || title.textContent || title.innerText) : '';
		let matches = searchText === '' ||
					text.toLowerCase().includes(searchText.toLowerCase());
		if (searchStatus && searchStatus.length > 0) {
			// console.log(title ? title.dataset.searchStatus : '', searchStatus);
			matches = matches && searchStatus.includes(title ? title.dataset.searchStatus : '');
		}
		
		if (matches) {
			$(monitor).show();
			hasResults = true;

			// Показываем родительские контейнеры
			showParentContainers(monitor);
		} else {
			$(monitor).hide();
		}
	});

	// Если поиск пуст, восстанавливаем состояние свернутых/развернутых папок
	if (searchText === '') {
		restoreMonitorsCollapseFromLocalStorage();
	}

	// Показать/скрыть сообщение "нет результатов"
	const noResults = document.getElementById('noResults') || createNoResultsMessage();
	noResults.style.display = hasResults ? 'none' : 'block';
}

/**
 * Рекурсивно показывает родительские контейнеры для найденного элемента.
 * @param {HTMLElement} element - Элемент, родительские контейнеры которого нужно показать.
 */
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

/**
 * Создает и добавляет сообщение "Мониторы не найдены", если оно еще не существует.
 * @returns {HTMLElement} - Элемент сообщения.
 */
function createNoResultsMessage() {
	const message = document.createElement('div');
	message.id = 'noResults';
	message.textContent = monitorsNotFound;
	message.style.cssText = 'text-align: center; padding: 20px; color: #666;';

	const container = document.querySelector('.monitor-list-items');
	container.appendChild(message);
	return message;
}

/**
 * Очищает поле поиска и обновляет отображение мониторов.
 */
function clearSearch() {
	document.getElementById('monitorSearch').value = '';
	filterAndHideMonitors('');
}

/**
 * Восстанавливает состояние свернутых/развернутых папок из localStorage.
 */
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

/**
 * Инициализирует обработчики для сворачивания/разворачивания папок.
 */
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

/**
 * Обновляет лог воркеров монитора (полоски состояний).
 * @param {Object} data - Данные монитора.
 */
function updateMonitorWorkersLogs(data) {
	const monitorWorkersLogs = $('#monitor-workers-logs');
	const keys = Object.keys(data.logs).sort((a, b) => a - b);
	const last40Keys = keys.slice(-40);
	const repeat = parseInt(data.monitor && data.monitor.repeat_seconds, 10) || 60;
	const now = Math.floor(Date.now() / 1000);
	// Генерация HTML для каждого воркера
	HTML = '';
	for (let worker_id in data.workers) {
		const worker = data.workers[worker_id];
		HTML += '<div class="row">';
			HTML += '<div class="col-3 col-sm-2 align-middle lh-3">'+worker.title+'</div><div class="col-9 col-sm-10">';
				HTML += '<div class="wrap"><div class="monitor-states-big" data-monitor-id="7">';
					let prevClassName = 'bg-secondary';
					let prevTitle = '';
					for (let i in last40Keys) {
						const time = last40Keys[i];
						const slotStart = parseInt(time, 10);
						const log_workers = data.logs[time];
						const log = log_workers[worker_id];
						const isOpenSlot = slotStart <= now && (slotStart + repeat) > now;
						let className = 'bg-secondary';
						let title = log_workers.title;
						if (log) {
							if (log.status == 0) {
								className = "bg-danger";
							} else if (log['status'] == 1) {
								className = "bg-success";
							}
							title = log.title;
							prevClassName = className;
							prevTitle = title;
						} else if (isOpenSlot) {
							// Still collecting this minute — soft provisional color from previous status.
							className = toSubtleStatusClass(prevClassName);
							if (prevTitle) {
								title = prevTitle;
							}
						} else {
							prevClassName = 'bg-secondary';
							prevTitle = '';
						}
						if (data.monitor.maintenances) {
							// Собираем все заголовки обслуживания в одну строку
							const maintenance_title = Object.values(data.monitor.maintenances).join('<br/>');
							className = "bg-primary";
							title = title + '<br/>' + Maintenance_Title + '<br/>' + maintenance_title;
						}

						HTML += '<div data-min-time="'+ time + '" class="' + className + '" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="' + escapeHtmlAttr(title) + '"></div>';
					}
				HTML += '</div></div>';
			HTML += '</div>';
		HTML += '</div>';
	}
	monitorWorkersLogs.html(HTML);
}

/**
 * Обновляет информационную карточку с последними данными монитора.
 * @param {Object} data - Данные монитора.
 */
function updateMonitorLastInfo(data) {
	const container = $('#monitor-last-info');
	const hideLatency = hidesResponseTimeUi(data);
	HTML = '';
	HTML += '<div class="card monitor-info mb-3">';
	HTML += '<div class="card-body row">';
		if (!hideLatency) {
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
		}

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

		if (!hideLatency && data['cert_expire']) {
			HTML += '<div class="center col-4 col-lg mb-3">'+Dashboard_CertExpire+'<br/>';
			HTML += '<span class="text-secondary fs-7">('+(data['cert_expire'] ? data['cert_expire'] : '')+')</span><br/>';
			HTML += (data['cert_expire_days'] ? data['cert_expire_days'] : '')+'</div>';
		}
	HTML += '</div>';
	HTML += '</div>';
	container.empty().html(HTML);
}

/**
 * Builds one events table row (HTML string) with data-event-id for incremental updates.
 * @param {Object} event
 * @param {Object} data
 * @param {boolean} showMonitor
 * @returns {string}
 */
function buildMonitorEventRowHtml(event, data, showMonitor) {
	const icon = event.status == 1
		? '<i class="bi bi-check-square text-success"></i>'
		: '<i class="bi bi-exclamation-square text-danger"></i>';
	const worker = data.workers[event.worker_id] && data.workers[event.worker_id]['title']
		? data.workers[event.worker_id]['title']
		: 'Unknown worker';
	let monitor = '';
	if (event.monitor_id && data.monitors && data.monitors[event.monitor_id] && data.monitors[event.monitor_id]['title']) {
		monitor = data.monitors[event.monitor_id]['title'];
	}
	const eventId = event.id ? String(event.id) : '';
	let HTML = '<tr data-event-id="'+eventId+'">';
	HTML += '<td>'+icon+'</td>';
	if (showMonitor) {
		HTML += '<td>'+monitor+'</td>';
	}
	HTML += '<td>'+worker+'</td>';
	HTML += '<td>'+event.event_time+'</td>';
	HTML += '<td>'+event.status_code+'</td>';
	HTML += '<td>'+(event.response_error ? event.response_error : '')+'</td>';
	HTML += '</tr>';
	return HTML;
}

/**
 * History page URL for the events table link (optionally scoped to a monitor).
 * @param {Object} data
 * @returns {string}
 */
function monitorEventsHistoryUrl(data) {
	const monitorId = data && data.monitor && data.monitor.id ? parseInt(data.monitor.id, 10) : 0;
	if (monitorId > 0) {
		return '/events/?monitor_id=' + monitorId;
	}
	return '/events/';
}

function updateMonitorEvents(data) {
	const container = $('#monitor-events');
	if (!container.length) return;

	const events = Array.isArray(data.events) ? data.events : [];
	const showMonitor = !data.monitor || !data.monitor.id;
	const historyUrl = monitorEventsHistoryUrl(data);

	if ($.fn.DataTable.isDataTable('#Table')) {
		const table = $('#Table').DataTable();
		const incomingIds = new Set();
		events.forEach((event) => {
			if (event && event.id) incomingIds.add(String(event.id));
		});

		const existingIds = new Set();
		const rowsToRemove = [];
		table.rows().every(function() {
			const id = String($(this.node()).attr('data-event-id') || '');
			if (id) existingIds.add(id);
			if (!id || !incomingIds.has(id)) {
				rowsToRemove.push(this);
			}
		});
		rowsToRemove.forEach((row) => row.remove());

		const newEvents = events.filter((event) => event && event.id && !existingIds.has(String(event.id)));
		if (newEvents.length > 0) {
			const pageInfo = table.page.info();
			const remainingNodes = [];
			table.rows({ order: 'current' }).every(function() {
				remainingNodes.push(this.node());
			});
			table.clear();
			newEvents.forEach((event) => {
				table.row.add($(buildMonitorEventRowHtml(event, data, showMonitor))[0]);
			});
			remainingNodes.forEach((node) => table.row.add(node));
			table.draw(false);
			if (pageInfo && pageInfo.pages > 0) {
				const page = Math.min(pageInfo.page, Math.max(0, table.page.info().pages - 1));
				table.page(page).draw(false);
			}
		} else {
			table.draw(false);
		}

		const historyLink = container.find('.monitor-events-history-link');
		if (historyLink.length) {
			historyLink.attr('href', historyUrl);
		}
		return;
	}

	if (events.length < 1) {
		container.empty();
		return;
	}

	let HTML = '';
	HTML += '<div class="card monitor-events-card mb-3">';
	HTML += '<div class="card-body">';
	HTML += '<div class="d-flex justify-content-between align-items-center mb-2">';
	HTML += '<div></div>';
	HTML += '<a class="monitor-events-history-link small" href="'+historyUrl+'">'+Dashboard_Events_History+'</a>';
	HTML += '</div>';
	HTML += '<table class="table table-transparent table-responsive" id="Table">';

	HTML += '<thead class=""><tr>';
	HTML += '<th scope="col" class="align-middle" data-priority="1">'+Dashboard_Events_Status+'</th>';
	if (showMonitor) {
		HTML += '<th scope="col" class="align-middle" data-priority="2">'+Dashboard_Events_Monitor+'</th>';
	}
	HTML += '<th scope="col" class="align-middle" data-priority="3">'+Dashboard_Events_Worker+'</th>';
	HTML += '<th scope="col" class="align-middle" data-priority="4">'+Dashboard_Events_Time+'</th>';
	HTML += '<th scope="col" class="align-middle" data-priority="5">'+Dashboard_Events_StatusCode+'</th>';
	HTML += '<th scope="col" class="align-middle" data-priority="6">'+Dashboard_Events_ResponseError+'</th>';
	HTML += '</tr></thead><tbody>';

	for (let i = 0; i < events.length; i++) {
		HTML += buildMonitorEventRowHtml(events[i], data, showMonitor);
	}
	HTML += '</tbody></table>';
	HTML += '</div>';
	HTML += '</div>';
	container.empty().html(HTML);
	new DataTable('#Table', {
		paging: true,
		searching: false,
		ordering: false,
		info: false,
	});
}

/**
 * Отображает данные для выбранного монитора, включая графики и логи.
 * @param {Object} data - Данные монитора.
 * @param {string|number} period - Период для отображения на графике.
 */
function printMonitorData(data, period) {
	let HTML = '';
	data.monitor = data.monitor || {};
	$('title').html('Monitoring - Monitor: ' + data.monitor.title);
	data.logs = data.logs || {};
	data.workers = data.workers || {};
	cardObj = $('div.monitor-dashboard div.card#monitor-data-'+data.monitor.id);

	// Если карточка для этого монитора еще не создана, генерируем HTML
	if (!cardObj || cardObj.length < 1) {
		HTML += '<div class="card" id="monitor-data-'+data.monitor.id+'">';
		if (data.monitor.id) {
			HTML += '<div class="card-header bg-transparent d-flex justify-content-between align-items-center">';
			if (data.monitor.url) {
				HTML += '<a href="'+data.monitor.url+'" target="_blank"><h5>'+data.monitor.title+'</h5></a>';
			} else {
				HTML += '<h5>'+data.monitor.title+'</h5>';
			}
			if (data.monitor.can_write) {
				HTML +=
          '<h5 class="mb-0"><a href="/admin/monitors/' +
          data.monitor.parent_id +
          "/edit/" +
          data.monitor.id +
          '/"><i class="bi bi-pencil-square fs-4 text-info pointer"></i></a></h5>';
			} else if (data.monitor.can_read) {
				HTML +=
          '<h5 class="mb-0"><a href="/admin/monitors/' +
          data.monitor.parent_id +
          "/edit/" +
          data.monitor.id +
          '/"><i class="bi bi-eye fs-4 text-info pointer"></i></a></h5>';
			}
			HTML += '</div>';

		}
		
		HTML += '<div class="card-body">';
		if (data.monitor.id) {
			HTML += '<div id="monitor-workers-logs"></div>';
			HTML += '<div id="monitor-last-info"></div>';
			if (!isExternalMonitor(data.monitor)) {
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
			}
		}
		HTML += '<div id="monitor-events"></div>';
		HTML += '</div>';
		HTML += '</div>';
		$('div.monitor-dashboard').html(HTML);
		
		if (data.monitor.id && !isExternalMonitor(data.monitor)) {
			$('#chart-period').selectpicker('render');
			initECharts(data);
		}

	}
	if (data.monitor.id) {
		updateMonitorWorkersLogs(data);
		updateMonitorLastInfo(data);
		if (!isExternalMonitor(data.monitor)) {
			updateECharts(data);
		}
	}
	updateMonitorEvents(data);
}

/**
 * Инициализирует график ECharts с базовыми настройками.
 * @param {Object} [data] - Данные монитора (chart_mode / chart_unit).
 */
function initECharts(data) {
	// Определяем тему в зависимости от системных настроек
	const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
	const chartDom = document.getElementById('MonitorEChart');
	// Проверяем и уничтожаем предыдущий экземпляр графика, чтобы избежать утечек памяти
	if (monitorChart && !monitorChart.isDisposed()) {
		monitorChart.dispose();
	}
	monitorChart = null;
	if (!chartDom) {
		return;
	}
	monitorChart = echarts.init(chartDom, isDark ? 'dark' : 'light');
	const valueMode = isValueChartMonitor(data);
	const unit = (data && data.chart_unit) ? data.chart_unit : 'ms';
	const yName = valueMode
		? ((typeof Dashboard_Charts_Value !== 'undefined' ? Dashboard_Charts_Value : 'Value') + (unit ? ' (' + chartUnitLabel(unit) + ')' : ''))
		: Dashboard_Charts_ResponseTime;
	
	// Базовые опции для графика
	const option = {
		backgroundColor: 'rgba(0,0,0,0)',
		darkMode: isDark,
		tooltip: {
			trigger: 'axis',
			axisPointer: {
				type: 'cross',
				label: {
					backgroundColor: '#0dcaf0',
					color: 'rgba(255,255,255,1)'
				}
			},
			// Форматирование всплывающей подсказки
			formatter: function(params) {
				let result = params[0].axisValue + '<br/>';
				params.forEach(function(item) {
					if (item.value != null) {
						result += item.seriesName + ': ' + formatChartValue(item.value, unit) + '<br/>';
					}
				});
				return result;
			}
		},
		
		// Панель инструментов (сохранение как изображение)
		 toolbox: {
			show: true,
			feature: {
			saveAsImage: {}
			}
		},

		// Отступы для графика
		grid: {
			left: '3%',
			right: '4%',
			bottom: '60px',
			top: '60px',
			containLabel: true
		},
		
		// Настройки оси Y
		yAxis: {
			type: 'value',
			min: valueMode && unit === '%' ? 0 : undefined,
			max: valueMode && unit === '%' ? 100 : undefined,
			axisLabel: {
				formatter: function(value) {
					return formatChartValue(value, unit);
				}
			},
			name: yName,
			nameLocation: 'end',
			nameGap: 15
		},

		// Настройки масштабирования (зума)
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
		
		// Легенда графика
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
	// Обработчик изменения темы (светлая/темная)
	window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
		const isDark = e.matches;
		
		// Сохраняем текущие опции графика
		const currentOption = monitorChart.getOption();
		// console.log(currentOption);
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

/**
 * Обновляет данные на графике ECharts.
 * @param {Object} data - Новые данные для отображения.
 */
function updateECharts(data) {
	if (!monitorChart || monitorChart.isDisposed()) {
		return;
	}
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
	const timeKeys = Object.keys(data.logs_chart || {}).sort((a, b) => a - b);
	const valueMode = isValueChartMonitor(data);
	const unit = data.chart_unit || (valueMode ? '%' : 'ms');
	const yName = valueMode
		? ((typeof Dashboard_Charts_Value !== 'undefined' ? Dashboard_Charts_Value : 'Value') + (unit ? ' (' + chartUnitLabel(unit) + ')' : ''))
		: Dashboard_Charts_ResponseTime;

	// Подготовка данных для ECharts
	for (let worker_id in data.workers) {
		const worker = data.workers[worker_id];
		let workerData = [];
		let statusData = [];
		let noDataPeriods = [];
		let lastError = false; // Флаг для отслеживания, был ли предыдущий лог ошибкой
		// Собираем данные по времени
		for (let i = 0; i < timeKeys.length; i++) {
			const time = timeKeys[i];
			if (xAxisData.length < timeKeys.length) {
				// Форматируем дату для отображения
				const date = new Date(time * 1000);
				xAxisData.push(date.toLocaleTimeString());
			}

			const log_workers = data.logs_chart[time];
			let log = log_workers ? log_workers[worker_id] : null;

			if (!log) {
				log = {
					'status': 0,
					'time': null,
					'value': null
				};
			}

			const point = valueMode ? log['value'] : log['time'];

			// Если есть данные о времени выполнения / метрике, добавляем их
			if (log && point != null) {
				workerData.push(point);
				if (log['status'] == 1) {
					lastError = false;
				}
			} else {
				// Если данных нет, добавляем "No Data" линию
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
			// Если статус 0 (ошибка), добавляем "Error" линию
			if (Number(log['status']) == 0 && point != null) {
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
			// Настройки для одной серии (одного воркера)
			name: worker.title || ('#' + worker_id),
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

	// Обновляем опции графика с новыми данными
	const option = {
		legend: {
			data: Object.values(data.workers).map(w => w.title || ''),
			top: 10,
			type: 'scroll'
		},
		yAxis: {
			type: 'value',
			min: valueMode && unit === '%' ? 0 : undefined,
			max: valueMode && unit === '%' ? 100 : undefined,
			axisLabel: {
				formatter: function(value) {
					return formatChartValue(value, unit);
				}
			},
			name: yName,
			nameLocation: 'end',
			nameGap: 15
		},
		tooltip: {
			formatter: function(params) {
				if (!params || !params.length) return '';
				let result = params[0].axisValue + '<br/>';
				params.forEach(function(item) {
					if (item.value != null) {
						result += item.seriesName + ': ' + formatChartValue(item.value, unit) + '<br/>';
					}
				});
				return result;
			}
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

/**
 * Запрашивает и отображает данные для конкретного монитора.
 * @param {number} monitorId - ID монитора.
 * @param {number|string} [period=1] - Период для отображения данных (в часах).
 */
function MonitorShowData(monitorId, period=1) {
	clearTimeout(monitorShowTimeout); // Очищаем предыдущий таймаут
	selectedMonitorId = monitorId;
	selectedMonitorPeriod = period;
	wss.send('monitor_show_data',{'monitor_id':monitorId, 'period': period},(response)=>{
		if (response.message == false) {
			console.log(response);
		} else {
			if (response.message) {
				hideAllTooltips();
				printMonitorData(response.message, period);
				applyAllTooltips();
			}
		}
	});
	// Safety-net refresh; dirty-check refreshes sooner when data changes.
	monitorShowTimeout = setTimeout(function() {
		MonitorShowData(monitorId, period);
	}, 30000);
}

/**
 * Переключает вид на панель дашборда монитора.
 */
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

/**
 * Управляет отображением панелей списка и дашборда для мобильных устройств.
 * @param {boolean} [dashboard_active=false] - Флаг, указывающий, активен ли дашборд.
 */
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

function showPushNotification(title = '', message = '') {
	if (!("Notification" in window)) {
		return;
	}

	if (Notification.permission === "granted") {
		const notification = new Notification(title, {
			body: message,
			icon: '/images/favicon.ico'
		});
		notification.onclick = function() {
			window.focus();
			notification.close();
		};
	} else if (Notification.permission !== "denied") {
		Notification.requestPermission().then(permission => {
			if (permission === "granted") {
				showNotification(title, message);
			}
		});
	}
}

$(document).ready(function() {
	// if ("Notification" in window && Notification.permission === "default") {
	// 	Notification.requestPermission();
	// }
	// showPushNotification('Test Title!','TEST message <br/><em>html?</em>');
	// Делегированный обработчик клика на заголовок монитора
	$('body').delegate('.monitor-item>.monitor-title', 'click', function(){
		Loading = '<div class="d-flex justify-content-center"><div class="spinner-border text-warning" role="status"><span class="visually-hidden">Loading...</span></div></div>';
		$('div.monitor-dashboard').html(Loading);
		history.pushState({'action': 'monitor', 'monitorId': $(this).data('monitor-id')}, null, "/monitor/" + $(this).data('monitor-id') + "/");
		selectedMonitorId = $(this).data('monitor-id');
		MonitorShowData($(this).data('monitor-id'));
		showMonitorDashboard();
		document.querySelectorAll('body')[0].scrollIntoView();
	});
	// Обработчик изменения периода на графике
	$('body').delegate('#chart-period', 'change', function() {
		MonitorShowData(selectedMonitorId, $(this).val());
	});

	// Обработчик ввода в поле поиска с задержкой
	$('#monitorSearch').on('input', function() {
		clearTimeout(monitorSearchTimeout);
		monitorSearchTimeout = setTimeout(function() {
			filterAndHideMonitors();
		}, 500);
	});

	$('#monitorStatus').on('change', function() {
		filterAndHideMonitors();
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

	// Инициализация всплывающих подсказок с использованием делегирования событий
	// Это более надежный способ для динамически создаваемого контента
	document.body.addEventListener('mouseover', function(event) {
		const target = event.target.closest('[data-bs-toggle="tooltip"]');
		if (target && !bootstrap.Tooltip.getInstance(target)) {
			new bootstrap.Tooltip(target).show();
		}
	});

	// --- Логика перезагрузки страницы при неактивности ---
	let inactivityTimer;

	/**
	 * Сбрасывает и заново запускает таймер неактивности.
	 * Если таймер сработает, страница будет перезагружена.
	 */
	function resetInactivityTimer() {
		// Очищаем предыдущий таймер
		clearTimeout(inactivityTimer);

		// Устанавливаем новый таймер на 1 час (60 * 60 * 1000 миллисекунд)
		inactivityTimer = setTimeout(() => {
			// Эта функция выполнится, если не было активности в течение часа
			window.location.reload();
		}, 60 * 60 * 1000);
	}

	// Список событий, которые считаются активностью пользователя
	const userActivityEvents = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];

	// На каждое событие активности сбрасываем таймер
	userActivityEvents.forEach(event => document.addEventListener(event, resetInactivityTimer, false));

	// Запускаем таймер при первой загрузке страницы
	resetInactivityTimer();
});