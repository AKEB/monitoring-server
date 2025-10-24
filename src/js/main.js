
function MonitorsUpdate(wss, count=20) {
	wss.send('monitors_update',{'count':count},(response)=>{
		if (response.message == false) {
			console.log(response);
		} else {
			for (var monitor_id in response.message) {
				const monitor = response.message[monitor_id];
				const statesDiv = $('div.monitor-states[data-monitor-id="'+monitor_id+'"]');
				if (statesDiv && statesDiv.length > 0) {
					for (var time in monitor.states) {
						const state = monitor.states[time];
						const existObject = $(statesDiv[0]).children('[data-min-time="'+state['min_time']+'"]');
						if (existObject && existObject.length > 0) {
							const obj = existObject[0];
							if (!$(obj).hasClass(state['class'])) {
								$(obj).removeClass("bg-secondary bg-warning bg-danger bg-success").addClass(state['class']);
							}
						} else {
							$(statesDiv[0]).append('<div data-min-time="'+ state['min_time'] + '" class="' + state['class'] + '" title="' + state['title'] + '"></div>');
						}
					}
				}
				const monitorItem = $('.monitor-item[data-monitor-id="'+monitor_id+'"]');
				const monitorBadge = $('.monitor-badge[data-monitor-id="'+monitor_id+'"]');
				if (monitorBadge && monitorBadge.length > 0) {
					$(monitorBadge[0]).removeClass("bg-secondary bg-warning bg-danger bg-success").addClass(monitor.badge_class);
					$(monitorBadge[0]).text(monitor.availability_percent + '%');
				}
				if (monitorItem && monitorItem.length > 0) {
					$(monitorItem[0]).removeClass("disabled").addClass(monitor.class);
				}
			}
		}
	})
	setTimeout(function() {
		MonitorsUpdate(wss, 5);
	}, 10000);
}

function MonitorsCollapse() {
	$('.monitor-folder>.monitor-title').on('click', function(){
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

	$(".collapse").each(function () {
		if (localStorage.getItem("collapseOpened_" + this.id) === "true") {
			$(this).collapse("show");
			$('.monitor-folder>.monitor-title[aria-controls="'+$(this).attr('id')+'"]').removeClass('collapsed');
		}
		else {
			$(this).collapse("hide");
			$('.monitor-folder>.monitor-title[aria-controls="'+$(this).attr('id')+'"]').addClass('collapsed');
		}
	});
}