
function printMonitors(data, level=0) {
	let HTML = '';
	for (var key in data) {
		const monitor = data[key];
		if (!monitor || !monitor.id) continue;
		if (monitor.children && monitor.children.length > 0) {
			// Folder
			HTML += '<div class="row monitor-folder mb-1" data-monitor-id="'+monitor.id+'">';
			let show = false;
			if (localStorage.getItem("collapseOpened_collapseMonitors_" + monitor.id) === "true") {
				show = true;
			}
			HTML += '<div class="col-9 col-md-8'+(show ? '':' collapsed')+' monitor-title" data-monitor-id="'+monitor.id+'" aria-controls="collapseMonitors_'+monitor.id+'">';
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
			HTML += printMonitors(monitor.children, level + 1 )
			HTML += '</div>';
		} else {
			// Monitor
			HTML += '<div class="row monitor-item mb-1" data-monitor-id="'+monitor.id+'"><div class="col-9 col-md-8 monitor-title" data-monitor-id="'+monitor.id+'">';
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


function MonitorsUpdate(wss, count=25) {
	wss.send('monitors_update',{'count':count},(response)=>{
		if (response.message == false) {
			console.log(response);
		} else {
			if (response.message && response.message.length > 0) {
				const html = printMonitors(response.message, 0);
				$('div.monitor-list-items>div.col').html(html);
			}
		}
	})
	setTimeout(function() {
		MonitorsUpdate(wss, 5);
	}, 20000);
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