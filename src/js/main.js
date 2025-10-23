
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
			}
		}
	})
	setTimeout(function() {
		MonitorsUpdate(wss, 5);
	}, 30000);
}