(function (window, document) {
	'use strict';

	function init() {
		var root = document.querySelector('.pat-admin-shell');

		if (!root) {
			return;
		}

		root.setAttribute('data-pat-ready', 'true');

		var note = root.querySelector('[data-pat-note]');
		if (note) {
			note.textContent = 'Phase 1 assets loaded.';
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(window, document);
