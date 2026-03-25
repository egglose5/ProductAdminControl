(function (window, document) {
	'use strict';

	var ROW_SELECTOR = '[data-pat-row-id]';
	var FIELD_SELECTOR = '[data-pat-field]';
	var variationLoadState = Object.create(null);
	var selectionState = {
		selectedKeys: Object.create(null),
		anchorKey: '',
		focusKey: ''
	};

	function createVariationState() {
		return {
			status: 'idle',
			message: '',
			loading: false,
			loaded: false,
			errored: false,
			rows: [],
			html: ''
		};
	}

	function getVariationStateKey(parentId) {
		var normalized = parseInt(parentId, 10);

		return String(isNaN(normalized) ? parentId || '' : normalized);
	}

	function getVariationState(parentId) {
		var key = getVariationStateKey(parentId);

		if (!variationLoadState[key]) {
			variationLoadState[key] = createVariationState();
		}

		return variationLoadState[key];
	}

	function getRowById(rowId) {
		if (!rowId && 0 !== rowId) {
			return null;
		}

		return document.querySelector(ROW_SELECTOR + '[data-pat-row-id="' + rowId + '"]');
	}

	function getParentRowById(parentId) {
		if (!parentId && 0 !== parentId) {
			return null;
		}

		return document.querySelector(ROW_SELECTOR + '[data-pat-row-type="product"][data-pat-row-id="' + parentId + '"]');
	}

	function getParentIdFromRow(row) {
		if (!row) {
			return 0;
		}

		return parseInt(row.getAttribute('data-pat-row-id'), 10) || 0;
	}

	function getParentDomId(row) {
		if (!row) {
			return '';
		}

		return row.id || '';
	}

	function getVariationStatusNode(row) {
		if (!row) {
			return null;
		}

		return row.querySelector('[data-pat-variation-status]');
	}

	function syncVariationRowState(parentId, state) {
		var row = getParentRowById(parentId);

		if (!row) {
			return;
		}

		var classes = [ 'is-loading-variations', 'is-variation-error', 'is-variation-loaded' ];
		var status = state && state.status ? state.status : 'idle';

		classes.forEach(function (className) {
			row.classList.remove(className);
		});

		row.removeAttribute('data-pat-variation-load-state');
		row.removeAttribute('data-pat-variation-load-message');
		row.removeAttribute('aria-busy');

		if ('loading' === status) {
			row.classList.add('is-loading-variations');
			row.setAttribute('data-pat-variation-load-state', 'loading');
			row.setAttribute('aria-busy', 'true');
		} else if ('error' === status) {
			row.classList.add('is-variation-error');
			row.setAttribute('data-pat-variation-load-state', 'error');
		} else if ('loaded' === status) {
			row.classList.add('is-variation-loaded');
			row.setAttribute('data-pat-variation-load-state', 'loaded');
		}

		if (state && state.message) {
			row.setAttribute('data-pat-variation-load-message', state.message);
		}

		var statusNode = getVariationStatusNode(row);

		if (!statusNode) {
			return;
		}

		statusNode.textContent = state && state.message ? state.message : '';
	}

	function setVariationState(parentId, nextState) {
		var key = getVariationStateKey(parentId);
		var current = variationLoadState[key] || createVariationState();
		var state = {
			status: nextState && nextState.status ? nextState.status : current.status,
			message: nextState && 'undefined' !== typeof nextState.message ? toStringValue(nextState.message) : current.message,
			loading: nextState && 'undefined' !== typeof nextState.loading ? !!nextState.loading : 'loading' === current.status,
			loaded: nextState && 'undefined' !== typeof nextState.loaded ? !!nextState.loaded : 'loaded' === current.status,
			errored: nextState && 'undefined' !== typeof nextState.errored ? !!nextState.errored : 'error' === current.status,
			rows: nextState && Array.isArray(nextState.rows) ? nextState.rows.slice() : current.rows.slice(),
			html: nextState && 'undefined' !== typeof nextState.html ? toStringValue(nextState.html) : current.html
		};

		variationLoadState[key] = state;
		syncVariationRowState(parentId, state);

		return state;
	}

	function cacheVariationRows(parentId, rows, html) {
		return setVariationState(parentId, {
			status: 'loaded',
			message: '',
			loading: false,
			loaded: true,
			errored: false,
			rows: Array.isArray(rows) ? rows.slice() : [],
			html: html || ''
		});
	}

	function markVariationLoading(parentId, message) {
		return setVariationState(parentId, {
			status: 'loading',
			message: message || 'Loading variations...',
			loading: true,
			loaded: false,
			errored: false
		});
	}

	function markVariationError(parentId, message) {
		return setVariationState(parentId, {
			status: 'error',
			message: message || 'Variation load failed.',
			loading: false,
			loaded: false,
			errored: true
		});
	}

	function clearVariationState(parentId) {
		return setVariationState(parentId, {
			status: 'idle',
			message: '',
			loading: false,
			loaded: false,
			errored: false,
			rows: [],
			html: ''
		});
	}

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

	function getRowSelectionKey(row) {
		if (!row) {
			return '';
		}

		var rowId = row.getAttribute('data-pat-row-id');

		if (!rowId && '0' !== rowId) {
			return '';
		}

		return getRowType(row) + ':' + rowId;
	}

	function rowFromSelectionKey(root, key) {
		if (!key || !root) {
			return null;
		}

		var parts = key.split(':');
		var rowType = parts[0] || '';
		var rowId = parts[1] || '';

		if (!rowType || !rowId) {
			return null;
		}

		return root.querySelector(ROW_SELECTOR + '[data-pat-row-type="' + rowType + '"][data-pat-row-id="' + rowId + '"]');
	}

	function isRowVisible(row) {
		if (!row) {
			return false;
		}

		return !row.hidden && !row.classList.contains('is-hidden');
	}

	function getSelectableRows(root) {
		var rows = root.querySelectorAll(ROW_SELECTOR);

		return Array.prototype.filter.call(rows, function (row) {
			return isRowVisible(row);
		});
	}

	function getRowSelectCheckbox(row) {
		if (!row) {
			return null;
		}

		return row.querySelector('[data-pat-select-row]');
	}

	function isRowSelected(row) {
		var key = getRowSelectionKey(row);

		if (!key) {
			return false;
		}

		return !!selectionState.selectedKeys[key];
	}

	function syncRowSelectionUI(row) {
		if (!row) {
			return;
		}

		var selected = isRowSelected(row);
		var checkbox = getRowSelectCheckbox(row);

		row.classList.toggle('is-selected', selected);
		row.setAttribute('aria-selected', selected ? 'true' : 'false');

		if (checkbox) {
			checkbox.checked = selected;
		}
	}

	function setRowSelected(row, selected) {
		var key = getRowSelectionKey(row);

		if (!key) {
			return;
		}

		if (selected) {
			selectionState.selectedKeys[key] = true;
		} else {
			delete selectionState.selectedKeys[key];
		}

		syncRowSelectionUI(row);
	}

	function clearSelection(root) {
		var selectedRows = root.querySelectorAll(ROW_SELECTOR + '.is-selected');

		Array.prototype.forEach.call(selectedRows, function (row) {
			setRowSelected(row, false);
		});

		selectionState.selectedKeys = Object.create(null);
		selectionState.anchorKey = '';
		selectionState.focusKey = '';
	}

	function updateSelectedCount(root) {
		var selectedCountNode = root.querySelector('[data-pat-selected-count]');
		var selectedRows = root.querySelectorAll(ROW_SELECTOR + '.is-selected');
		var count = selectedRows.length;

		if (selectedCountNode) {
			selectedCountNode.textContent = count + (1 === count ? ' selected row' : ' selected rows');
		}

		updateBulkEditBar(root);
	}

	function syncSelectionUI(root) {
		var rows = root.querySelectorAll(ROW_SELECTOR);

		Array.prototype.forEach.call(rows, function (row) {
			syncRowSelectionUI(row);
		});

		updateSelectedCount(root);
	}

	function getSelectionAnchorRow(root, fallbackRow) {
		var anchorRow = rowFromSelectionKey(root, selectionState.anchorKey);

		if (anchorRow && isRowVisible(anchorRow)) {
			return anchorRow;
		}

		if (fallbackRow && isRowVisible(fallbackRow)) {
			return fallbackRow;
		}

		var visibleRows = getSelectableRows(root);

		return visibleRows.length ? visibleRows[0] : null;
	}

	function selectSingleRow(root, row) {
		if (!row) {
			return;
		}

		clearSelection(root);
		setRowSelected(row, true);

		selectionState.anchorKey = getRowSelectionKey(row);
		selectionState.focusKey = selectionState.anchorKey;
		updateSelectedCount(root);
	}

	function toggleSingleRow(root, row) {
		if (!row) {
			return;
		}

		setRowSelected(row, !isRowSelected(row));

		var key = getRowSelectionKey(row);

		if (!selectionState.anchorKey) {
			selectionState.anchorKey = key;
		}

		selectionState.focusKey = key;
		updateSelectedCount(root);
	}

	function selectRange(root, anchorRow, targetRow, keepExisting) {
		if (!anchorRow || !targetRow) {
			return;
		}

		var visibleRows = getSelectableRows(root);
		var start = visibleRows.indexOf(anchorRow);
		var end = visibleRows.indexOf(targetRow);

		if (-1 === start || -1 === end) {
			selectSingleRow(root, targetRow);
			return;
		}

		if (!keepExisting) {
			clearSelection(root);
		}

		if (start > end) {
			var temp = start;
			start = end;
			end = temp;
		}

		visibleRows.slice(start, end + 1).forEach(function (row) {
			setRowSelected(row, true);
		});

		selectionState.anchorKey = getRowSelectionKey(anchorRow);
		selectionState.focusKey = getRowSelectionKey(targetRow);
		updateSelectedCount(root);
	}

	function clearSelectionForRows(root, rows) {
		rows.forEach(function (row) {
			setRowSelected(row, false);
		});

		if (!isRowSelected(rowFromSelectionKey(root, selectionState.anchorKey))) {
			selectionState.anchorKey = '';
		}

		if (!isRowSelected(rowFromSelectionKey(root, selectionState.focusKey))) {
			selectionState.focusKey = '';
		}

		updateSelectedCount(root);
	}

	function refreshSelectionAfterDomChange(root) {
		var knownRows = root.querySelectorAll(ROW_SELECTOR);
		var knownKeys = Object.create(null);

		Array.prototype.forEach.call(knownRows, function (row) {
			knownKeys[getRowSelectionKey(row)] = true;
		});

		Object.keys(selectionState.selectedKeys).forEach(function (key) {
			if (!knownKeys[key]) {
				delete selectionState.selectedKeys[key];
			}
		});

		if (!knownKeys[selectionState.anchorKey]) {
			selectionState.anchorKey = '';
		}

		if (!knownKeys[selectionState.focusKey]) {
			selectionState.focusKey = '';
		}

		syncSelectionUI(root);
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
		var currentState = getRowState(row);
		var dirtyFields = row.querySelectorAll(FIELD_SELECTOR + '.is-dirty');

		if ('saving' === currentState) {
			state = 'saving';
		} else if ('saved' === currentState && dirtyFields.length === 0) {
			state = 'saved';
		} else if ('error' === currentState && dirtyFields.length === 0) {
			state = 'clean';
		} else if ('error' === currentState) {
			state = 'error';
		} else if (dirtyFields.length) {
			state = 'dirty';
		}

		setRowState(row, state, 'clean' === state ? '' : row.getAttribute('data-pat-row-message') || '');
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
			syncRowSelectionUI(row);
		});

		updateSelectedCount(root);
	}

	function getDirtyRowElements(root) {
		return root.querySelectorAll(ROW_SELECTOR + '[data-pat-row-state="dirty"], ' + ROW_SELECTOR + '[data-pat-row-state="error"]');
	}

	function getEditorState(root) {
		if (root.querySelector(ROW_SELECTOR + '[data-pat-row-state="saving"]')) {
			return 'saving';
		}

		if (getDirtyRowElements(root).length) {
			return 'dirty';
		}

		return 'idle';
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

		updateSelectedCount(root);

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
			field.classList.remove('is-field-error');
			field.removeAttribute('title');
			field.setAttribute('data-pat-field-state', 'clean');
		});

		setRowState(row, 'saved', result && result.message ? result.message : 'Saved');
	}

	function applyFieldErrors(row, errors) {
		if (!row || !errors || 'object' !== typeof errors) {
			return;
		}

		Object.keys(errors).forEach(function (fieldName) {
			var field = row.querySelector(FIELD_SELECTOR + '[data-pat-field="' + fieldName + '"]');

			if (!field) {
				return;
			}

			field.classList.add('is-field-error');
			field.title = toStringValue(errors[fieldName]);
		});
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
			applyFieldErrors(row, result && result.errors ? result.errors : {});
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
			updateToolbar(root, getEditorState(root));
		});

		root.addEventListener('change', function (event) {
			var field = event.target.closest(FIELD_SELECTOR);

			if (!field || !root.contains(field)) {
				return;
			}

			ensureOriginalValue(field);
			updateFieldState(field);
			updateToolbar(root, getEditorState(root));
		});

		root.addEventListener('keydown', function (event) {
			if ('Escape' !== event.key) {
				return;
			}

			var field = event.target.closest(FIELD_SELECTOR);

			if (!field || !root.contains(field)) {
				return;
			}

			event.preventDefault();
			var originalValue = field.hasAttribute('data-pat-original-value') ? field.getAttribute('data-pat-original-value') : readFieldValue(field);

			if (field.isContentEditable) {
				field.textContent = originalValue;
			} else if ('value' in field) {
				field.value = originalValue;
			} else {
				field.setAttribute('data-pat-value', originalValue);
			}

			field.classList.remove('is-field-error');
			field.removeAttribute('title');
			updateFieldState(field);
			updateToolbar(root, getEditorState(root));
		});
	}

	function bindSelectionEvents(root) {
		root.addEventListener('click', function (event) {
			var row = event.target.closest(ROW_SELECTOR);

			if (!row || !root.contains(row)) {
				return;
			}

			if (event.target.closest('[data-pat-toggle-children], [data-pat-save-trigger], [data-pat-select-row]')) {
				return;
			}

			if (event.shiftKey) {
				selectRange(root, getSelectionAnchorRow(root, row), row, event.ctrlKey || event.metaKey);
				return;
			}

			if (event.ctrlKey || event.metaKey) {
				toggleSingleRow(root, row);
				return;
			}

			selectSingleRow(root, row);
		});

		root.addEventListener('click', function (event) {
			var checkbox = event.target.closest('[data-pat-select-row]');

			if (!checkbox || !root.contains(checkbox)) {
				return;
			}

			var row = checkbox.closest(ROW_SELECTOR);

			if (!row) {
				return;
			}

			event.preventDefault();

			if (event.shiftKey) {
				selectRange(root, getSelectionAnchorRow(root, row), row, event.ctrlKey || event.metaKey);
				return;
			}

			if (event.ctrlKey || event.metaKey) {
				toggleSingleRow(root, row);
				return;
			}

			selectSingleRow(root, row);
		});

		root.addEventListener('keydown', function (event) {
			if (!event.shiftKey || ('ArrowDown' !== event.key && 'ArrowUp' !== event.key)) {
				return;
			}

			if (event.target.closest(FIELD_SELECTOR)) {
				return;
			}

			var currentRow = event.target.closest(ROW_SELECTOR) || rowFromSelectionKey(root, selectionState.focusKey) || rowFromSelectionKey(root, selectionState.anchorKey);
			var visibleRows = getSelectableRows(root);
			var currentIndex = visibleRows.indexOf(currentRow);

			if (!visibleRows.length || -1 === currentIndex) {
				return;
			}

			var delta = 'ArrowDown' === event.key ? 1 : -1;
			var nextIndex = currentIndex + delta;

			if (nextIndex < 0 || nextIndex >= visibleRows.length) {
				return;
			}

			event.preventDefault();
			selectRange(root, getSelectionAnchorRow(root, currentRow), visibleRows[nextIndex], false);
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

	function updateBulkEditBar(root) {
		var trigger = root.querySelector('[data-pat-bulk-edit-trigger]');
		var selectedRows = root.querySelectorAll(ROW_SELECTOR + '.is-selected');
		var count = selectedRows.length;

		if (trigger) {
			trigger.disabled = (0 === count);
		}

		var bar = root.querySelector('[data-pat-bulk-edit-bar]');

		if (!bar) {
			return;
		}

		var countLabel = bar.querySelector('[data-pat-bulk-edit-row-count]');

		if (countLabel) {
			countLabel.textContent = count + (1 === count ? ' row' : ' rows');
		}
	}

	function openBulkEditBar(root) {
		var bar = root.querySelector('[data-pat-bulk-edit-bar]');

		if (!bar) {
			return;
		}

		bar.hidden = false;
		updateBulkEditBar(root);
	}

	function closeBulkEditBar(root) {
		var bar = root.querySelector('[data-pat-bulk-edit-bar]');

		if (!bar) {
			return;
		}

		bar.hidden = true;

		var fieldSelect = bar.querySelector('[data-pat-bulk-field-select]');
		var statusSelect = bar.querySelector('[data-pat-bulk-value-status]');
		var textInput = bar.querySelector('[data-pat-bulk-value-text]');

		if (fieldSelect) {
			fieldSelect.value = '';
		}

		if (statusSelect) {
			statusSelect.style.display = 'none';
		}

		if (textInput) {
			textInput.style.display = 'none';
			textInput.value = '';
		}
	}

	function applyBulkEdit(root) {
		var bar = root.querySelector('[data-pat-bulk-edit-bar]');

		if (!bar) {
			return;
		}

		var fieldSelect = bar.querySelector('[data-pat-bulk-field-select]');

		if (!fieldSelect || !fieldSelect.value) {
			return;
		}

		var fieldName = fieldSelect.value;
		var value = '';

		if ('status' === fieldName) {
			var statusSelect = bar.querySelector('[data-pat-bulk-value-status]');
			value = statusSelect ? statusSelect.value : '';
		} else {
			var textInput = bar.querySelector('[data-pat-bulk-value-text]');
			value = textInput ? textInput.value : '';
		}

		var selectedRows = root.querySelectorAll(ROW_SELECTOR + '.is-selected');

		Array.prototype.forEach.call(selectedRows, function (row) {
			if (row.hidden || row.classList.contains('is-hidden')) {
				return;
			}

			var field = row.querySelector(FIELD_SELECTOR + '[data-pat-field="' + fieldName + '"]');

			if (!field) {
				return;
			}

			if ('value' in field) {
				field.value = value;
			} else if (field.isContentEditable) {
				field.textContent = value;
			}

			ensureOriginalValue(field);
			updateFieldState(field);
		});

		updateToolbar(root, getEditorState(root));
		closeBulkEditBar(root);
	}

	function bindBulkEditEvents(root) {
		var trigger = root.querySelector('[data-pat-bulk-edit-trigger]');

		if (trigger) {
			trigger.addEventListener('click', function (event) {
				event.preventDefault();
				openBulkEditBar(root);
			});
		}

		root.addEventListener('click', function (event) {
			if (event.target.closest('[data-pat-bulk-cancel]') && root.contains(event.target)) {
				closeBulkEditBar(root);
			}

			if (event.target.closest('[data-pat-bulk-apply]') && root.contains(event.target)) {
				applyBulkEdit(root);
			}
		});

		root.addEventListener('change', function (event) {
			var fieldSelect = event.target.closest('[data-pat-bulk-field-select]');

			if (!fieldSelect || !root.contains(fieldSelect)) {
				return;
			}

			var bar = fieldSelect.closest('[data-pat-bulk-edit-bar]');

			if (!bar) {
				return;
			}

			var fieldName = fieldSelect.value;
			var statusSelect = bar.querySelector('[data-pat-bulk-value-status]');
			var textInput = bar.querySelector('[data-pat-bulk-value-text]');

			if ('status' === fieldName) {
				if (statusSelect) {
					statusSelect.style.display = '';
				}

				if (textInput) {
					textInput.style.display = 'none';
				}
			} else if (fieldName) {
				if (statusSelect) {
					statusSelect.style.display = 'none';
				}

				if (textInput) {
					var isNumeric = ('regular_price' === fieldName || 'sale_price' === fieldName || 'stock_quantity' === fieldName || 'menu_order' === fieldName || 'weight' === fieldName || 'length' === fieldName || 'width' === fieldName || 'height' === fieldName);
					textInput.type = isNumeric ? 'number' : 'text';
					textInput.step = ('regular_price' === fieldName || 'sale_price' === fieldName || 'weight' === fieldName || 'length' === fieldName || 'width' === fieldName || 'height' === fieldName) ? '0.01' : '1';
					textInput.value = '';
					textInput.style.display = '';
					textInput.focus();
				}
			} else {
				if (statusSelect) {
					statusSelect.style.display = 'none';
				}

				if (textInput) {
					textInput.style.display = 'none';
				}
			}
		});
	}

	function setUpEditableShell(root) {
		snapshotEditableFields(root);
		bindEditableEvents(root);
		bindSelectionEvents(root);
		bindBulkEditEvents(root);
		bindSaveToolbar(root);
		updateToolbar(root, 'idle', 'No pending changes.');
	}

	function getChildRowsByParentDomId(parentDomId) {
		if (!parentDomId) {
			return [];
		}

		return Array.prototype.slice.call(document.querySelectorAll('[data-pat-parent="' + parentDomId + '"]'));
	}

	function removeChildRowsByParentDomId(parentDomId) {
		getChildRowsByParentDomId(parentDomId).forEach(function (row) {
			row.parentNode.removeChild(row);
		});
	}

	function insertVariationMarkup(parentRow, html) {
		var parentDomId = getParentDomId(parentRow);

		if (!parentRow || !parentDomId || !html) {
			return [];
		}

		removeChildRowsByParentDomId(parentDomId);
		parentRow.insertAdjacentHTML('afterend', html);

		return getChildRowsByParentDomId(parentDomId);
	}

	function requestVariations(parentId) {
		if (!window.PATAdmin || !window.PATAdmin.ajaxUrl || !window.PATAdmin.variationAction) {
			return Promise.resolve({
				success: false,
				message: 'Variation endpoint is not configured.',
				rows: [],
				html: ''
			});
		}

		var formData = new window.FormData();
		formData.append('action', window.PATAdmin.variationAction);
		formData.append(window.PATAdmin.variationNonceField || 'nonce', window.PATAdmin.variationNonce || '');
		formData.append('parent_id', String(parentId));

		return window.fetch(window.PATAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		}).then(function (response) {
			return response.json().catch(function () {
				return {
					success: false,
					message: 'Variation response could not be parsed.',
					rows: [],
					html: ''
				};
			});
		});
	}

	function updateToggleButton(button, expanded) {
		button.setAttribute('aria-expanded', expanded ? 'true' : 'false');

		var label = button.querySelector('[data-pat-toggle-label]');

		if (label) {
			label.textContent = expanded ? 'Collapse' : 'Expand';
		}
	}

	function setHidden(root, rows, hidden) {
		if (hidden) {
			clearSelectionForRows(root, rows);
		}

		rows.forEach(function (row) {
			row.classList.toggle('is-hidden', hidden);
			row.hidden = hidden;
			row.setAttribute('aria-hidden', hidden ? 'true' : 'false');
		});
	}

	function toggleChildren(root, button) {
		var targetId = button.getAttribute('data-pat-target');

		if (!targetId) {
			return;
		}

		var parentRow = document.getElementById(targetId);
		var parentId = parentRow ? getParentIdFromRow(parentRow) : 0;

		if (!parentRow) {
			return;
		}

		var expanded = button.getAttribute('aria-expanded') === 'true';
		var showChildren = !expanded;
		var childRows = getChildRowsByParentDomId(targetId);
		var variationState = getVariationState(parentId);

		if (!showChildren) {
			updateToggleButton(button, false);
			parentRow.classList.remove('is-open');
			setHidden(root, childRows, true);
			return;
		}

		if (childRows.length) {
			updateToggleButton(button, true);
			parentRow.classList.add('is-open');
			setHidden(root, childRows, false);
			cacheVariationRows(parentId, variationState.rows.length ? variationState.rows : childRows.map(function (row) {
				return {
					id: parseInt(row.getAttribute('data-pat-row-id'), 10) || 0,
					row_type: getRowType(row)
				};
			}), variationState.html || '');
			return;
		}

		if (variationState.loaded && variationState.html) {
			childRows = insertVariationMarkup(parentRow, variationState.html);
			snapshotEditableFields(root);
			refreshSelectionAfterDomChange(root);
			updateToggleButton(button, true);
			parentRow.classList.add('is-open');
			setHidden(root, childRows, false);
			return;
		}

		if (variationState.loading) {
			return;
		}

		markVariationLoading(parentId, 'Loading variations...');

		requestVariations(parentId).then(function (response) {
			var payload = response && response.data && !Array.isArray(response.rows) ? response.data : response;
			var rows = payload && Array.isArray(payload.rows) ? payload.rows : [];
			var html = payload && payload.html ? payload.html : '';
			var message = payload && payload.message ? payload.message : '';

			if (!payload || !payload.success) {
				throw new Error(message || 'Variation load failed.');
			}

			cacheVariationRows(parentId, rows, html);

			if (html) {
				childRows = insertVariationMarkup(parentRow, html);
				snapshotEditableFields(root);
				refreshSelectionAfterDomChange(root);
			} else {
				childRows = getChildRowsByParentDomId(targetId);
			}

			updateToggleButton(button, true);
			parentRow.classList.add('is-open');
			setHidden(root, childRows, false);
			syncVariationRowState(parentId, {
				status: 'loaded',
				message: message
			});
		}).catch(function (error) {
			markVariationError(parentId, error && error.message ? error.message : 'Variation load failed.');
			updateToggleButton(button, false);
			parentRow.classList.remove('is-open');
		});
	}

	function bindToggles(root) {
		var buttons = root.querySelectorAll('[data-pat-toggle-children]');

		Array.prototype.forEach.call(buttons, function (button) {
			button.addEventListener('click', function (event) {
				event.preventDefault();
				toggleChildren(root, button);
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
