(function () {
	'use strict';

	function formatLocal(ts) {
		var sec = Number(ts);
		if (!isFinite(sec) || sec <= 0) {
			return '';
		}
		try {
			return new Date(sec * 1000).toLocaleString(undefined, {
				year: 'numeric',
				month: '2-digit',
				day: '2-digit',
				hour: '2-digit',
				minute: '2-digit',
				second: '2-digit',
				timeZoneName: 'short'
			});
		} catch (e) {
			return new Date(sec * 1000).toLocaleString();
		}
	}

	function applyBeatTitles(root) {
		var beats = (root || document).querySelectorAll('.sp-beat[data-time]');
		for (var i = 0; i < beats.length; i++) {
			var el = beats[i];
			var label = formatLocal(el.getAttribute('data-time'));
			if (label) {
				el.setAttribute('title', label);
			}
		}
	}

	function applyUpdated(root) {
		var nodes = (root || document).querySelectorAll('.sp-updated[data-time]');
		for (var i = 0; i < nodes.length; i++) {
			var el = nodes[i];
			var label = formatLocal(el.getAttribute('data-time'));
			if (!label) {
				continue;
			}
			var tpl = el.getAttribute('data-template');
			if (tpl && tpl.indexOf('%s') !== -1) {
				el.textContent = tpl.replace('%s', label);
			} else {
				el.textContent = label;
			}
		}
	}

	function run() {
		applyBeatTitles(document);
		applyUpdated(document);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', run);
	} else {
		run();
	}
})();
