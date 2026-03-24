(function (window, document) {
	'use strict';

	function setHidden(rows, hidden) {
		rows.forEach(function (row) {
			row.classList.toggle('is-hidden', hidden);
			row.hidden = hidden;
			row.setAttribute('aria-hidden', hidden ? 'true' : 'false');
		});
	}

	function toggleChildren(button) {
		var targetId = button.getAttribute('data-pat-target');

		if (!targetId) {
			return;
		}

		var parentRow = document.getElementById(targetId);

		if (!parentRow) {
			return;
		}

		var expanded = button.getAttribute('aria-expanded') === 'true';
		var showChildren = !expanded;
		var childRows = document.querySelectorAll('[data-pat-parent="' + targetId + '"]');

		if (!childRows.length) {
			return;
		}

		button.setAttribute('aria-expanded', showChildren ? 'true' : 'false');
		parentRow.classList.toggle('is-open', showChildren);
		setHidden(Array.prototype.slice.call(childRows), !showChildren);

		if (button.querySelector('[data-pat-toggle-label]')) {
			button.querySelector('[data-pat-toggle-label]').textContent = showChildren ? 'Collapse' : 'Expand';
		}
	}

	function bindToggles(root) {
		var buttons = root.querySelectorAll('[data-pat-toggle-children]');

		Array.prototype.forEach.call(buttons, function (button) {
			button.addEventListener('click', function (event) {
				event.preventDefault();
				toggleChildren(button);
			});
		});
	}

	function init() {
		var root = document.querySelector('.pat-admin-shell, .pat-product-editor');

		if (!root) {
			return;
		}

		root.setAttribute('data-pat-ready', 'true');
		bindToggles(root);

		if (window.PATAdmin && window.PATAdmin.screenSlug) {
			root.setAttribute('data-pat-screen', window.PATAdmin.screenSlug);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(window, document);
