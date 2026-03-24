(function (window, document) {
	'use strict';

	var ROW_SELECTOR = '[data-pat-row-id]';
	var FIELD_SELECTOR = '[data-pat-field]';

	function toStringValue(value) {
		if (null === value || 'undefined' === typeof value) {
			return '';
		}

		return String(value);
	}

	function normalizeValue(field, value) {
		var normalized = toStringValue(value).replace(/\r\n?/g, '\n');

		if (field && field.type === 'checkbox') {
			return field.checked ? '1' : '0';
		}

		return normalized.trim();
	}

	function readFieldValue(field) {
		if (!field) {
			return '';
		}

		if (field.isContentEditable) {
			return field.textContent || '';
		}

		if ('value' in field) {
			return field.value;
		}

		return field.getAttribute('data-pat-value') || field.textContent || '';
	}

	function getRowFromField(field) {
		if (!field) {
			return null;
		}

		return field.closest(ROW_SELECTOR);
	}

	function getRowType(row) {
		if (!row) {
			return 'product';
		}

		return row.getAttribute('data-pat-row-type') || (row.classList.contains('pat-child-row') ? 'variation' : 'product');
	}

	function getRowState(row) {
		if (!row) {
			return 'clean';
		}

		return row.getAttribute('data-pat-row-state') || 'clean';
	}

	function getOrCreateRowStatus(row) {
		var status = row.querySelector('[data-pat-row-state-message]');

		if (status) {
			return status;
		}

		var stateCell = row.querySelector('[data-pat-row-state]');

		if (stateCell) {
			status = document.createElement('span');
			status.className = 'pat-row-state-message';
			status.setAttribute('data-pat-row-state-message', 'true');
			status.setAttribute('aria-live', 'polite');
			stateCell.appendChild(status);

			return status;
		}

		var cells = row.querySelectorAll('td');

		if (!cells.length) {
			return null;
		}

		status = document.createElement('span');
		status.className = 'pat-row-state-message';
		status.setAttribute('data-pat-row-state-message', 'true');
		status.setAttribute('aria-live', 'polite');
		cells[cells.length - 1].appendChild(status);

		return status;
	}

	function formatRowStatus(state, message) {
		if (message) {
			return message;
		}

		switch (state) {
			case 'saving':
				return 'Saving changes...';
			case 'saved':
				return 'Saved';
			case 'error':
				return 'Save error';
			case 'dirty':
				return 'Pending changes';
			default:
				return 'Ready';
		}
	}

	function setRowState(row, state, message) {
		var previousState = getRowState(row);
		var states = [ 'is-clean', 'is-dirty', 'is-saving', 'is-saved', 'is-error' ];

		states.forEach(function (className) {
			row.classList.remove(className);
		});

		row.classList.add('is-' + state);
		row.setAttribute('data-pat-row-state', state);

		if (message) {
			row.setAttribute('data-pat-row-message', message);
		} else {
			row.removeAttribute('data-pat-row-message');
		}

		var status = getOrCreateRowStatus(row);

		if (status) {
			status.textContent = formatRowStatus(state, message);
		}

		if (previousState !== state) {
			row.setAttribute('data-pat-row-state-previous', previousState);
		}
	}

	function ensureOriginalValue(field) {
		if (!field) {
			return;
		}

		if (!field.hasAttribute('data-pat-original-value')) {
			field.setAttribute('data-pat-original-value', readFieldValue(field));
		}
	}

	function isFieldDirty(field) {
		var original = normalizeValue(field, field.getAttribute('data-pat-original-value') || '');
		var current = normalizeValue(field, readFieldValue(field));

		return current !== original;
	}

	function updateFieldState(field) {
		var row = getRowFromField(field);
		var dirty = isFieldDirty(field);

		field.classList.toggle('is-dirty', dirty);
		field.setAttribute('data-pat-field-state', dirty ? 'dirty' : 'clean');

		if (row) {
			syncRowState(row);
		}
	}

	function syncRowState(row) {
		var state = 'clean';
		var dirtyFields = row.querySelectorAll(FIELD_SELECTOR + '.is-dirty');

		if ('saving' === row.getAttribute('data-pat-row-state')) {
			state = 'saving';
		} else if ('error' === row.getAttribute('data-pat-row-state')) {
			state = 'error';
		} else if ('saved' === row.getAttribute('data-pat-row-state') && dirtyFields.length === 0) {
			state = 'saved';
		} else if (dirtyFields.length) {
			state = 'dirty';
		}

		setRowState(row, state, row.getAttribute('data-pat-row-message') || '');
	}

	function snapshotEditableFields(root) {
		var fields = root.querySelectorAll(FIELD_SELECTOR);

		Array.prototype.forEach.call(fields, function (field) {
			ensureOriginalValue(field);
			updateFieldState(field);
		});

		var rows = root.querySelectorAll(ROW_SELECTOR);
		Array.prototype.forEach.call(rows, function (row) {
			if (!row.getAttribute('data-pat-row-type')) {
				row.setAttribute('data-pat-row-type', row.classList.contains('pat-child-row') ? 'variation' : 'product');
			}

			syncRowState(row);
		});
	}

	function getDirtyRowElements(root) {
		return root.querySelectorAll(ROW_SELECTOR + '[data-pat-row-state="dirty"], ' + ROW_SELECTOR + '[data-pat-row-state="error"]');
	}

	function updateToolbar(root, state, message) {
		var saveButton = root.querySelector('[data-pat-save-trigger]');
		var saveStatus = root.querySelector('[data-pat-save-status]');
		var dirtyCount = root.querySelector('[data-pat-dirty-count]');
		var dirtyRows = getDirtyRowElements(root);
		var count = dirtyRows.length;

		if (dirtyCount) {
			dirtyCount.textContent = count + (1 === count ? ' changed row' : ' changed rows');
		}

		if (saveButton) {
			saveButton.disabled = 0 === count || 'saving' === state;
		}

		if (saveStatus) {
			saveStatus.setAttribute('data-pat-save-status', state);
			saveStatus.textContent = message || (0 === count ? 'No pending changes.' : count + (1 === count ? ' row ready to save.' : ' rows ready to save.'));
		}

		root.setAttribute('data-pat-editor-state', state);
	}

	function collectDirtyRows(root) {
		var rows = [];
		var dirtyRowNodes = getDirtyRowElements(root);

		Array.prototype.forEach.call(dirtyRowNodes, function (row) {
			var changes = {};
			var fields = row.querySelectorAll(FIELD_SELECTOR);

			Array.prototype.forEach.call(fields, function (field) {
				if (!isFieldDirty(field)) {
					return;
				}

				changes[field.getAttribute('data-pat-field')] = readFieldValue(field);
			});

			rows.push({
				id: parseInt(row.getAttribute('data-pat-row-id'), 10) || 0,
				row_type: getRowType(row),
				changes: changes
			});
		});

		return rows;
	}

	function setRowSavingState(row) {
		setRowState(row, 'saving', 'Saving changes...');
	}

	function setRowErrorState(row, message) {
		setRowState(row, 'error', message || 'Save endpoint is not wired yet.');
	}

	function setRowSavedState(row, result) {
		var fields = row.querySelectorAll(FIELD_SELECTOR);

		Array.prototype.forEach.call(fields, function (field) {
			var fieldName = field.getAttribute('data-pat-field');
			var newValue = result && result.data && Object.prototype.hasOwnProperty.call(result.data, fieldName) ? result.data[fieldName] : readFieldValue(field);

			if (field.isContentEditable) {
				field.textContent = toStringValue(newValue);
			} else if ('value' in field) {
				field.value = toStringValue(newValue);
			} else {
				field.setAttribute('data-pat-value', toStringValue(newValue));
			}

			field.setAttribute('data-pat-original-value', toStringValue(newValue));
			field.classList.remove('is-dirty');
			field.setAttribute('data-pat-field-state', 'clean');
		});

		setRowState(row, 'saved', result && result.message ? result.message : 'Saved');
	}

	function applySaveResults(root, response, requestRows) {
		var payload = response && response.data && !Array.isArray(response.results) ? response.data : response;
		var results = payload && Array.isArray(payload.results) ? payload.results : [];
		var resultMap = Object.create(null);

		Array.prototype.forEach.call(results, function (result) {
			if (!result || 'number' !== typeof result.id && 'string' !== typeof result.id) {
				return;
			}

			resultMap[String(result.id)] = result;
		});

		requestRows.forEach(function (requestedRow) {
			var row = root.querySelector(ROW_SELECTOR + '[data-pat-row-id="' + requestedRow.id + '"]');
			var result = resultMap[String(requestedRow.id)];

			if (!row) {
				return;
			}

			if (result && 'saved' === result.status) {
				setRowSavedState(row, result);
				return;
			}

			setRowErrorState(row, result && result.message ? result.message : payload && payload.message ? payload.message : 'Save failed.');
		});

		if (payload && payload.success) {
			snapshotEditableFields(root);
		}
	}

	function requestSave(payload) {
		if (!window.PATAdmin || !window.PATAdmin.ajaxUrl || !window.PATAdmin.saveAction) {
			return Promise.resolve({
				success: false,
				message: 'Save endpoint is not configured.',
				results: []
			});
		}

		var formData = new window.FormData();
		formData.append('action', window.PATAdmin.saveAction);
		formData.append(window.PATAdmin.nonceField || 'nonce', window.PATAdmin.nonce || '');
		formData.append('rows', JSON.stringify(payload.rows));

		return window.fetch(window.PATAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		}).then(function (response) {
			return response.json().catch(function () {
				return {
					success: false,
					message: 'Save response could not be parsed.',
					results: []
				};
			});
		});
	}

	function handleSaveClick(root) {
		var payload = {
			rows: collectDirtyRows(root)
		};

		if (!payload.rows.length) {
			updateToolbar(root, 'idle', 'No pending changes.');
			return;
		}

		payload.rows.forEach(function (row) {
			var rowNode = root.querySelector(ROW_SELECTOR + '[data-pat-row-id="' + row.id + '"]');

			if (rowNode) {
				setRowSavingState(rowNode);
			}
		});

		updateToolbar(root, 'saving', 'Saving ' + payload.rows.length + (1 === payload.rows.length ? ' row...' : ' rows...'));

		requestSave(payload)
			.then(function (response) {
				applySaveResults(root, response, payload.rows);
				var payloadResponse = response && response.data && 'undefined' === typeof response.results ? response.data : response;
				updateToolbar(root, payloadResponse && payloadResponse.success ? 'saved' : 'error', payloadResponse && payloadResponse.message ? payloadResponse.message : 'Save request completed.');
			})
			.catch(function (error) {
				applySaveResults(root, { success: false, message: error && error.message ? error.message : 'Save failed.', results: [] }, payload.rows);
				updateToolbar(root, 'error', error && error.message ? error.message : 'Save failed.');
			});
	}

	function bindEditableEvents(root) {
		root.addEventListener('input', function (event) {
			var field = event.target.closest(FIELD_SELECTOR);

			if (!field || !root.contains(field)) {
				return;
			}

			ensureOriginalValue(field);
			updateFieldState(field);
			updateToolbar(root, 'dirty');
		});

		root.addEventListener('change', function (event) {
			var field = event.target.closest(FIELD_SELECTOR);

			if (!field || !root.contains(field)) {
				return;
			}

			ensureOriginalValue(field);
			updateFieldState(field);
			updateToolbar(root, 'dirty');
		});
	}

	function bindSaveToolbar(root) {
		var saveButton = root.querySelector('[data-pat-save-trigger]');

		if (!saveButton) {
			return;
		}

		saveButton.addEventListener('click', function (event) {
			event.preventDefault();
			handleSaveClick(root);
		});
	}

	function setUpEditableShell(root) {
		snapshotEditableFields(root);
		bindEditableEvents(root);
		bindSaveToolbar(root);
		updateToolbar(root, 'idle', 'No pending changes.');
	}

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
		var root = document.querySelector('[data-pat-editor-root="true"], .pat-admin-shell, .pat-product-editor');

		if (!root) {
			return;
		}

		root.setAttribute('data-pat-ready', 'true');
		bindToggles(root);
		setUpEditableShell(root);

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
