(function (window, document) {
	'use strict';

	var ROW_SELECTOR = '[data-pat-row-id]';
	var FIELD_SELECTOR = '[data-pat-field]';
	var variationLoadState = Object.create(null);
	var selectionState = {
		selectedKeys: Object.create(null),
		anchorKey: '',
		focusKey: '',
		hideParentRows: false,
		bulkEditExcludeParents: false
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

	function isParentRow(row) {
		if (!row) {
			return false;
		}

		return getRowType(row) === 'product' && row.classList.contains('pat-parent-row');
	}

	function isVariationRow(row) {
		if (!row) {
			return false;
		}

		return getRowType(row) === 'variation' && row.classList.contains('pat-child-row');
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

	function deselectParents(root) {
		var selectedParents = root.querySelectorAll(ROW_SELECTOR + '.is-selected[data-pat-row-type="product"]');

		Array.prototype.forEach.call(selectedParents, function (row) {
			setRowSelected(row, false);
		});

		updateSelectedCount(root);
	}

	function syncHideParentsControls(root) {
		var filterCheckbox = root.querySelector('[data-pat-variations-only-filter]');
		var hideButton = root.querySelector('[data-pat-hide-parents-trigger]');

		if (filterCheckbox) {
			filterCheckbox.checked = !!selectionState.hideParentRows;
		}

		if (hideButton) {
			hideButton.classList.toggle('is-active', !!selectionState.hideParentRows);
		}
	}

	function setHideParents(root, shouldHide) {
		selectionState.hideParentRows = !!shouldHide;

		var parentRows = root.querySelectorAll(ROW_SELECTOR + '[data-pat-row-type="product"]');

		if (selectionState.hideParentRows) {
			deselectParents(root);
		}

		Array.prototype.forEach.call(parentRows, function (row) {
			if (selectionState.hideParentRows) {
				row.hidden = true;
				row.classList.add('is-hidden');
				row.setAttribute('data-pat-hidden-by-user', 'true');
				return;
			}

			if ('true' === row.getAttribute('data-pat-hidden-by-user')) {
				row.hidden = false;
				row.classList.remove('is-hidden');
				row.removeAttribute('data-pat-hidden-by-user');
			}
		});

		syncHideParentsControls(root);
		syncSelectionUI(root);
	}

	function getRowsByType(root, rowType) {
		var rows = root.querySelectorAll(ROW_SELECTOR);

		return Array.prototype.filter.call(rows, function (row) {
			return isRowVisible(row) && getRowType(row) === rowType;
		});
	}

	function updateSelectedCount(root) {
		var selectedCountNode = root.querySelector('[data-pat-selected-count]');
		var selectedRows = root.querySelectorAll(ROW_SELECTOR + '.is-selected');
		var count = selectedRows.length;

		if (selectedCountNode) {
			selectedCountNode.textContent = count + (1 === count ? ' selected row' : ' selected rows');
		}

		updateBulkEditBar(root);
		updateGenerateButton(root);
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
		var generatedRowNodes = root.querySelectorAll(ROW_SELECTOR + '[data-pat-generated="true"]');
		var rowMap = Object.create(null);

		Array.prototype.forEach.call(dirtyRowNodes, function (row) {
			var key = row.getAttribute('data-pat-row-id') || '';
			rowMap[key] = row;
		});

		Array.prototype.forEach.call(generatedRowNodes, function (row) {
			var key = row.getAttribute('data-pat-row-id') || '';

			if (!rowMap[key]) {
				rowMap[key] = row;
			}
		});

		Object.keys(rowMap).forEach(function (key) {
			var row = rowMap[key];
			var changes = {};
			var fields = row.querySelectorAll(FIELD_SELECTOR);
			var isGenerated = 'true' === row.getAttribute('data-pat-generated');

			Array.prototype.forEach.call(fields, function (field) {
				if (!isGenerated && !isFieldDirty(field)) {
					return;
				}

				changes[field.getAttribute('data-pat-field')] = readFieldValue(field);
			});

			if (isGenerated && !Object.keys(changes).length) {
				return;
			}

			var attributes = {};
			var rawAttributes = row.getAttribute('data-pat-generated-attributes');

			if (rawAttributes) {
				try {
					attributes = JSON.parse(rawAttributes);
				} catch (e) {
					attributes = {};
				}
			}

			var rowIdRaw = row.getAttribute('data-pat-row-id') || '';

			rows.push({
				id: parseInt(rowIdRaw, 10) || 0,
				client_row_id: rowIdRaw,
				row_type: getRowType(row),
				changes: changes,
				is_generated: isGenerated,
				temp_id: row.getAttribute('data-pat-temp-id') || '',
				parent_id: parseInt(row.getAttribute('data-pat-parent-id'), 10) || 0,
				attributes: attributes
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
		var responseData = result && result.data ? result.data : {};
		var createdId = responseData && Object.prototype.hasOwnProperty.call(responseData, 'id') ? toStringValue(responseData.id) : '';

		Array.prototype.forEach.call(fields, function (field) {
			var fieldName = field.getAttribute('data-pat-field');
			var newValue = responseData && Object.prototype.hasOwnProperty.call(responseData, fieldName) ? responseData[fieldName] : readFieldValue(field);

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

		if (createdId) {
			row.setAttribute('data-pat-row-id', createdId);
			row.setAttribute('data-pat-generated', 'false');
			row.removeAttribute('data-pat-temp-id');
			row.removeAttribute('data-pat-generated-attributes');
			row.classList.remove('is-generated-row');

			Array.prototype.forEach.call(fields, function (field) {
				field.setAttribute('data-pat-row-id', createdId);
			});

			var idNode = row.querySelector('.pat-row-id');

			if (idNode) {
				idNode.textContent = 'ID: ' + createdId;
			}

			var existingBadges = row.querySelectorAll('.pat-row-meta .pat-badge');

			Array.prototype.forEach.call(existingBadges, function (badge) {
				badge.parentNode.removeChild(badge);
			});

			if (responseData && responseData.is_created) {
				var rowMeta = row.querySelector('.pat-row-meta');

				if (rowMeta) {
					var createdBadge = document.createElement('span');
					createdBadge.className = 'pat-badge';
					createdBadge.textContent = 'Created';
					rowMeta.insertBefore(createdBadge, rowMeta.firstChild);
				}
			}
		}

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
			if (!result) {
				return;
			}

			var resultKey = '';

			if (result.client_row_id) {
				resultKey = String(result.client_row_id);
			} else if ('number' === typeof result.id || 'string' === typeof result.id) {
				resultKey = String(result.id);
			}

			if (!resultKey) {
				return;
			}

			resultMap[resultKey] = result;
		});

		requestRows.forEach(function (requestedRow) {
			var requestKey = requestedRow.client_row_id ? String(requestedRow.client_row_id) : String(requestedRow.id);
			var row = root.querySelector(ROW_SELECTOR + '[data-pat-row-id="' + requestKey + '"]');
			var result = resultMap[requestKey];

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

	function requestUndoLastSave(batchId) {
		if (!window.PATAdmin || !window.PATAdmin.ajaxUrl || !window.PATAdmin.undoAction) {
			return Promise.resolve({
				success: false,
				message: 'Undo endpoint is not configured.',
				results: []
			});
		}

		var formData = new window.FormData();
		formData.append('action', window.PATAdmin.undoAction);
		formData.append(window.PATAdmin.undoNonceField || 'nonce', window.PATAdmin.undoNonce || '');

		if (batchId) {
			formData.append('batch_id', String(batchId));
		}

		return window.fetch(window.PATAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		}).then(function (response) {
			return response.json().catch(function () {
				return {
					success: false,
					message: 'Undo response could not be parsed.',
					results: []
				};
			});
		});
	}

	function applyUndoResults(root, response) {
		var payload = response && response.data && !Array.isArray(response.results) ? response.data : response;
		var results = payload && Array.isArray(payload.results) ? payload.results : [];
		var requestRows = [];
		var forwardedResults = [];
		var removedRows = false;

		results.forEach(function (result) {
			if (result && 'deleted' === result.status) {
				var deletedRowKey = result.client_row_id ? String(result.client_row_id) : String(result && result.id ? result.id : 0);
				var deletedRow = root.querySelector(ROW_SELECTOR + '[data-pat-row-id="' + deletedRowKey + '"]');

				if (deletedRow && deletedRow.parentNode) {
					deletedRow.parentNode.removeChild(deletedRow);
					removedRows = true;
				}

				if (result.data && result.data.parent_id) {
					clearVariationState(result.data.parent_id);
				}

				return;
			}

			forwardedResults.push(result);
			requestRows.push({
				id: result && result.id ? result.id : 0,
				client_row_id: result && result.client_row_id ? String(result.client_row_id) : String(result && result.id ? result.id : 0)
			});
		});

		if (removedRows) {
			refreshSelectionAfterDomChange(root);
		}

		applySaveResults(root, {
			success: payload && payload.success,
			message: payload && payload.message ? payload.message : '',
			results: forwardedResults
		}, requestRows);
	}

	function handleUndoClick(root, batchId) {
		updateToolbar(root, 'saving', batchId ? 'Undoing selected saved batch...' : 'Undoing latest saved batch...');

		requestUndoLastSave(batchId).then(function (response) {
			applyUndoResults(root, response);
			var payload = response && response.data && !Array.isArray(response.results) ? response.data : response;
			updateToolbar(root, payload && payload.success ? 'saved' : 'error', payload && payload.message ? payload.message : 'Undo request completed.');
			refreshHistoryPanelIfOpen(root);
		}).catch(function (error) {
			updateToolbar(root, 'error', error && error.message ? error.message : 'Undo failed.');
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
			var lookupKey = row.client_row_id ? row.client_row_id : String(row.id);
			var rowNode = root.querySelector(ROW_SELECTOR + '[data-pat-row-id="' + lookupKey + '"]');

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
				refreshHistoryPanelIfOpen(root);
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

		var fillDownTrigger = root.querySelector('[data-pat-fill-down-trigger]');

		if (fillDownTrigger) {
			fillDownTrigger.disabled = (count < 2);
		}

		var openEditorTrigger = root.querySelector('[data-pat-open-editor-trigger]');

		if (openEditorTrigger) {
			var selectedParentCount = Array.prototype.filter.call(selectedRows, function (row) {
				return isParentRow(row);
			}).length;

			openEditorTrigger.disabled = (0 === selectedParentCount);
		}

		var bar = root.querySelector('[data-pat-bulk-edit-bar]');

		if (!bar) {
			return;
		}

		var countLabel = bar.querySelector('[data-pat-bulk-edit-row-count]');
		var excludeCheckbox = bar.querySelector('[data-pat-bulk-exclude-parents]');
		var displayCount = count;

		if (excludeCheckbox && excludeCheckbox.checked) {
			var variationCount = Array.prototype.filter.call(selectedRows, function (row) {
				return isVariationRow(row);
			}).length;

			displayCount = variationCount;
		}

		if (countLabel) {
			countLabel.textContent = displayCount + (1 === displayCount ? ' row' : ' rows');
		}
	}

	function openBulkEditBar(root, operation) {
		var bar = root.querySelector('[data-pat-bulk-edit-bar]');

		if (!bar) {
			return;
		}

		operation = operation || 'bulk-edit';
		bar.setAttribute('data-pat-operation', operation);
		bar.hidden = false;
		updateBulkEditBar(root);
	}

	function closeBulkEditBar(root) {
		var bar = root.querySelector('[data-pat-bulk-edit-bar]');

		if (!bar) {
			return;
		}

		bar.hidden = true;
		bar.setAttribute('data-pat-operation', 'bulk-edit');

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

		var excludeParents = bar.querySelector('[data-pat-bulk-exclude-parents]') ? bar.querySelector('[data-pat-bulk-exclude-parents]').checked : false;
		var selectedRows = root.querySelectorAll(ROW_SELECTOR + '.is-selected');

		Array.prototype.forEach.call(selectedRows, function (row) {
			if (row.hidden || row.classList.contains('is-hidden')) {
				return;
			}

			if (excludeParents && isParentRow(row)) {
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

	function applyFillDown(root) {
		var selectedRows = root.querySelectorAll(ROW_SELECTOR + '.is-selected');
		var excludeParents = root.querySelector('[data-pat-bulk-edit-bar] [data-pat-bulk-exclude-parents]') ? root.querySelector('[data-pat-bulk-edit-bar] [data-pat-bulk-exclude-parents]').checked : false;
		var visibleRows = Array.prototype.filter.call(selectedRows, function (row) {
			if (!row.hidden && !row.classList.contains('is-hidden')) {
				if (excludeParents && isParentRow(row)) {
					return false;
				}

				return true;
			}

			return false;
		});

		if (visibleRows.length < 2) {
			return;
		}

		var fieldSelect = root.querySelector('[data-pat-bulk-edit-bar] [data-pat-bulk-field-select]');

		if (!fieldSelect || !fieldSelect.value) {
			return;
		}

		var fieldName = fieldSelect.value;
		var sourceRow = visibleRows[0];
		var sourceField = sourceRow.querySelector(FIELD_SELECTOR + '[data-pat-field="' + fieldName + '"]');

		if (!sourceField) {
			return;
		}

		var sourceValue = 'value' in sourceField ? sourceField.value : sourceField.textContent;

		visibleRows.slice(1).forEach(function (row) {
			var field = row.querySelector(FIELD_SELECTOR + '[data-pat-field="' + fieldName + '"]');

			if (!field) {
				return;
			}

			if ('value' in field) {
				field.value = sourceValue;
			} else if (field.isContentEditable) {
				field.textContent = sourceValue;
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
				openBulkEditBar(root, 'bulk-edit');
			});
		}

		var fillDownTrigger = root.querySelector('[data-pat-fill-down-trigger]');

		if (fillDownTrigger) {
			fillDownTrigger.addEventListener('click', function (event) {
				event.preventDefault();
				openBulkEditBar(root, 'fill-down');
			});
		}

		var openEditorTrigger = root.querySelector('[data-pat-open-editor-trigger]');

		if (openEditorTrigger) {
			openEditorTrigger.addEventListener('click', function (event) {
				event.preventDefault();
				handleOpenEditorClick(root);
			});
		}

		var undoTrigger = root.querySelector('[data-pat-undo-trigger]');

		if (undoTrigger) {
			undoTrigger.addEventListener('click', function (event) {
				event.preventDefault();
				handleUndoClick(root);
			});
		}

		root.addEventListener('click', function (event) {
			if (event.target.closest('[data-pat-bulk-cancel]') && root.contains(event.target)) {
				closeBulkEditBar(root);
			}

			if (event.target.closest('[data-pat-bulk-apply]') && root.contains(event.target)) {
				var bar = root.querySelector('[data-pat-bulk-edit-bar]');
				var operation = bar ? bar.getAttribute('data-pat-operation') : 'bulk-edit';
				if ('fill-down' === operation) {
					applyFillDown(root);
				} else {
					applyBulkEdit(root);
				}
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

		root.addEventListener('change', function (event) {
			var excludeCheckbox = event.target.closest('[data-pat-bulk-exclude-parents]');

			if (excludeCheckbox && root.contains(excludeCheckbox)) {
				selectionState.bulkEditExcludeParents = excludeCheckbox.checked;
				updateBulkEditBar(root);
				return;
			}
		});

		root.addEventListener('change', function (event) {
			var filterCheckbox = event.target.closest('[data-pat-variations-only-filter]');

			if (filterCheckbox && root.contains(filterCheckbox)) {
				setHideParents(root, !!filterCheckbox.checked);
				return;
			}
		});

		root.addEventListener('click', function (event) {
			var deselectButton = event.target.closest('[data-pat-deselect-parents-trigger]');

			if (deselectButton && root.contains(deselectButton)) {
				event.preventDefault();
				deselectParents(root);
				return;
			}

			var hideParentsButton = event.target.closest('[data-pat-hide-parents-trigger]');

			if (hideParentsButton && root.contains(hideParentsButton)) {
				event.preventDefault();
				setHideParents(root, !selectionState.hideParentRows);
				return;
			}
		});
	}

	function setUpEditableShell(root) {
		snapshotEditableFields(root);

		var filterCheckbox = root.querySelector('[data-pat-variations-only-filter]');
		var undoTrigger = root.querySelector('[data-pat-undo-trigger]');

		if (undoTrigger && (!window.PATAdmin || !window.PATAdmin.undoAction)) {
			undoTrigger.disabled = true;
		}

		if (filterCheckbox) {
			setHideParents(root, !!filterCheckbox.checked);
		} else {
			syncHideParentsControls(root);
		}

		bindEditableEvents(root);
		bindSelectionEvents(root);
		bindBulkEditEvents(root);
		bindVariationGeneration(root);
		bindSaveToolbar(root);
		bindHistoryPanel(root);
		updateGenerateButton(root);
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

	function requestVariationPreview(parentId) {
		if (!window.PATAdmin || !window.PATAdmin.ajaxUrl || !window.PATAdmin.variationPreviewAction) {
			return Promise.resolve({
				success: false,
				message: 'Variation preview endpoint is not configured.',
				rows: [],
				html: ''
			});
		}

		var formData = new window.FormData();
		formData.append('action', window.PATAdmin.variationPreviewAction);
		formData.append(window.PATAdmin.variationPreviewNonceField || 'nonce', window.PATAdmin.variationPreviewNonce || '');
		formData.append('parent_id', String(parentId));

		return window.fetch(window.PATAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		}).then(function (response) {
			return response.json().catch(function () {
				return {
					success: false,
					message: 'Variation preview response could not be parsed.',
					rows: [],
					html: ''
				};
			});
		});
	}

	function getSelectedParentRows(root) {
		var selectedRows = root.querySelectorAll(ROW_SELECTOR + '.is-selected[data-pat-row-type="product"]');

		return Array.prototype.filter.call(selectedRows, function (row) {
			return !row.hidden && !row.classList.contains('is-hidden');
		});
	}

	function getSelectedPreviewParents(root) {
		var parents = getSelectedParentRows(root);

		return parents.filter(function (row) {
			return 'true' === row.getAttribute('data-pat-children-lazy');
		});
	}

	function ensureParentExpanded(root, parentRow) {
		if (!parentRow) {
			return Promise.resolve();
		}

		var parentDomId = getParentDomId(parentRow);
		var button = root.querySelector('[data-pat-toggle-children][data-pat-target="' + parentDomId + '"]');
		var parentId = getParentIdFromRow(parentRow);

		if (!button || !parentId) {
			return Promise.resolve();
		}

		var childRows = getChildRowsByParentDomId(parentDomId);
		var variationState = getVariationState(parentId);

		if (childRows.length) {
			updateToggleButton(button, true);
			parentRow.classList.add('is-open');
			setHidden(root, childRows, false);
			return Promise.resolve();
		}

		if (variationState.loaded && variationState.html) {
			childRows = insertVariationMarkup(parentRow, variationState.html);
			snapshotEditableFields(root);
			refreshSelectionAfterDomChange(root);
			updateToggleButton(button, true);
			parentRow.classList.add('is-open');
			setHidden(root, childRows, false);
			return Promise.resolve();
		}

		if (variationState.loading) {
			return Promise.resolve();
		}

		markVariationLoading(parentId, 'Loading variations...');

		return requestVariations(parentId).then(function (response) {
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
				childRows = getChildRowsByParentDomId(parentDomId);
			}

			updateToggleButton(button, true);
			parentRow.classList.add('is-open');
			setHidden(root, childRows, false);
			syncVariationRowState(parentId, {
				status: 'loaded',
				message: message
			});
		});
	}

	function handleOpenEditorClick(root) {
		var parents = getSelectedParentRows(root);

		if (!parents.length) {
			updateToolbar(root, 'idle', 'Select at least one parent row to open in editor.');
			return;
		}

		updateToolbar(root, 'saving', 'Loading selected parents into editor...');

		parents.reduce(function (promise, parentRow) {
			return promise.then(function () {
				return ensureParentExpanded(root, parentRow);
			});
		}, Promise.resolve()).then(function () {
			updateToolbar(root, getEditorState(root), 'Selected parents opened for spreadsheet editing.');
		}).catch(function (error) {
			updateToolbar(root, 'error', error && error.message ? error.message : 'Could not open selected parents in editor.');
		});
	}

	function updateGenerateButton(root) {
		var trigger = root.querySelector('[data-pat-generate-variations-trigger]');

		if (!trigger) {
			return;
		}

		trigger.disabled = 0 === getSelectedPreviewParents(root).length;
	}

	function applyVariationPreview(root, parentRow, payload) {
		if (!parentRow) {
			return;
		}

		var parentId = getParentIdFromRow(parentRow);
		var parentDomId = getParentDomId(parentRow);
		var rows = payload && Array.isArray(payload.rows) ? payload.rows : [];
		var html = payload && payload.html ? payload.html : '';
		var message = payload && payload.message ? payload.message : '';
		var button = root.querySelector('[data-pat-toggle-children][data-pat-target="' + parentDomId + '"]');
		var childRows = [];

		cacheVariationRows(parentId, rows, html);

		if (html) {
			childRows = insertVariationMarkup(parentRow, html);
			snapshotEditableFields(root);
			refreshSelectionAfterDomChange(root);
		} else {
			childRows = getChildRowsByParentDomId(parentDomId);
		}

		if (button) {
			updateToggleButton(button, true);
		}

		parentRow.classList.add('is-open');
		setHidden(root, childRows, false);
		syncVariationRowState(parentId, {
			status: 'loaded',
			message: message
		});
	}

	function bindVariationGeneration(root) {
		var trigger = root.querySelector('[data-pat-generate-variations-trigger]');

		if (!trigger) {
			return;
		}

		trigger.addEventListener('click', function (event) {
			event.preventDefault();

			var parents = getSelectedPreviewParents(root);

			if (!parents.length) {
				updateToolbar(root, 'idle', 'Select at least one variable parent row to generate previews.');
				return;
			}

			updateToolbar(root, 'saving', 'Generating variation previews...');

			Promise.all(parents.map(function (parentRow) {
				var parentId = getParentIdFromRow(parentRow);
				markVariationLoading(parentId, 'Generating variation previews...');

				return requestVariationPreview(parentId).then(function (response) {
					var payload = response && response.data && !Array.isArray(response.rows) ? response.data : response;

					if (!payload || !payload.success) {
						throw new Error(payload && payload.message ? payload.message : 'Variation preview generation failed.');
					}

					applyVariationPreview(root, parentRow, payload);
					return payload;
				});
			})).then(function (payloads) {
				var generatedCount = 0;

				payloads.forEach(function (payload) {
					var generatedRows = payload && Array.isArray(payload.generated_rows) ? payload.generated_rows : [];
					generatedCount += generatedRows.length;
				});

				updateToolbar(root, 'saved', 'Generated preview rows: ' + generatedCount + '.');
			}).catch(function (error) {
				updateToolbar(root, 'error', error && error.message ? error.message : 'Variation preview generation failed.');
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

	function requestSaveHistory(batchId) {
		if (!window.PATAdmin || !window.PATAdmin.ajaxUrl || !window.PATAdmin.historyAction) {
			return Promise.resolve({ success: false, batches: [], entries: [] });
		}

		var formData = new window.FormData();
		formData.append('action', window.PATAdmin.historyAction);
		formData.append(window.PATAdmin.historyNonceField || 'nonce', window.PATAdmin.historyNonce || '');

		if (batchId) {
			formData.append('batch_id', String(batchId));
		}

		return window.fetch(window.PATAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		}).then(function (response) {
			return response.json().catch(function () {
				return { success: false, batches: [] };
			});
		});
	}

	function escapeHistoryHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function formatHistoryBatchTime(raw) {
		if (!raw) {
			return '—';
		}

		var d = new Date(String(raw).replace(' ', 'T') + 'Z');

		if (isNaN(d.getTime())) {
			return String(raw);
		}

		return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
	}

	function formatHistoryValue(value) {
		if (null === value || 'undefined' === typeof value || '' === value) {
			return '—';
		}

		if ('string' === typeof value || 'number' === typeof value || 'boolean' === typeof value) {
			return String(value);
		}

		try {
			return JSON.stringify(value);
		} catch (error) {
			return String(value);
		}
	}

	function renderHistoryPanel(root, response) {
		var panel = root.querySelector('[data-pat-history-panel]');

		if (!panel) {
			return;
		}

		var list = panel.querySelector('[data-pat-history-list]');

		if (!list) {
			return;
		}

		var payload = response && response.data && !Array.isArray(response.batches) ? response.data : response;
		var batches = payload && Array.isArray(payload.batches) ? payload.batches : [];

		if (!batches.length) {
			list.innerHTML = '<p class="pat-history-empty">No save history found.</p>';
			return;
		}

		var html = '<table class="pat-history-table">';
		html += '<thead><tr>';
		html += '<th>Type</th>';
		html += '<th>Time</th>';
		html += '<th>By</th>';
		html += '<th>Products</th>';
		html += '<th>Fields</th>';
		html += '<th></th>';
		html += '</tr></thead><tbody>';

		batches.forEach(function (batch) {
			var actionType = batch && batch.action_type ? String(batch.action_type) : 'save';
			var actionLabel = 'save' === actionType ? 'Save' : ('undo' === actionType ? 'Undo' : 'Error');
			var isUndoable = batch && batch.undoable;
			var batchId = batch && batch.batch_id ? String(batch.batch_id) : '';
			var actorName = batch && batch.actor_name ? String(batch.actor_name) : 'Unknown user';

			html += '<tr class="pat-history-entry">';
			html += '<td><span class="pat-history-action-type pat-history-action-' + escapeHistoryHtml(actionType) + '">' + escapeHistoryHtml(actionLabel) + '</span>';

			if (isUndoable) {
				html += ' <span class="pat-history-undoable-badge">&#x2713; undoable</span>';
			}

			html += '</td>';
			html += '<td class="pat-history-time">' + escapeHistoryHtml(formatHistoryBatchTime(batch && batch.batch_time ? batch.batch_time : '')) + '</td>';
			html += '<td class="pat-history-actor">' + escapeHistoryHtml(actorName) + '</td>';
			html += '<td class="pat-history-stat">' + (batch && batch.entity_count ? Number(batch.entity_count) : 0) + '</td>';
			html += '<td class="pat-history-stat">' + (batch && batch.field_count ? Number(batch.field_count) : 0) + '</td>';
			html += '<td class="pat-history-actions">';
			html += '<button type="button" class="button button-small" data-pat-history-details="' + escapeHistoryHtml(batchId) + '">Details</button>';

			if (isUndoable && batchId) {
				html += '<button type="button" class="button button-small" data-pat-undo-batch-id="' + escapeHistoryHtml(batchId) + '">Undo</button>';
			}

			html += '</td>';
			html += '</tr>';
		});

		html += '</tbody></table>';
		list.innerHTML = html;
	}

	function renderHistoryDetails(entries) {
		if (!entries || !entries.length) {
			return '<p class="pat-history-empty">No field-level entries were stored for this batch.</p>';
		}

		var html = '<table class="pat-history-detail-table">';
		html += '<thead><tr>';
		html += '<th>Row</th>';
		html += '<th>Field</th>';
		html += '<th>Before</th>';
		html += '<th>After</th>';
		html += '<th>By</th>';
		html += '<th>Time</th>';
		html += '</tr></thead><tbody>';

		entries.forEach(function (entry) {
			var isError = entry && entry.action_type && 'save_error' === String(entry.action_type);
			var label = entry && entry.entity_label ? String(entry.entity_label) : '';
			var rowType = entry && entry.row_type ? String(entry.row_type) : '';
			var rowCell = label ? label : (rowType ? rowType + ' #' + Number(entry && entry.entity_id ? entry.entity_id : 0) : 'Batch event');
			var fieldCell = isError ? 'Error' : (entry && entry.field_key ? String(entry.field_key) : '—');
			var beforeValue = isError && entry && entry.request_context && entry.request_context.message
				? String(entry.request_context.message)
				: formatHistoryValue(entry ? entry.old_value : '');
			var afterValue = isError
				? (entry && entry.request_context && entry.request_context.rolled_back ? 'Rolled back' : 'Not saved')
				: formatHistoryValue(entry ? entry.new_value : '');

			html += '<tr class="pat-history-detail-entry' + (isError ? ' is-error' : '') + '">';
			html += '<td>' + escapeHistoryHtml(rowCell) + '</td>';
			html += '<td>' + escapeHistoryHtml(fieldCell) + '</td>';
			html += '<td>' + escapeHistoryHtml(beforeValue) + '</td>';
			html += '<td>' + escapeHistoryHtml(afterValue) + '</td>';
			html += '<td>' + escapeHistoryHtml(entry && entry.actor_name ? String(entry.actor_name) : 'Unknown user') + '</td>';
			html += '<td>' + escapeHistoryHtml(formatHistoryBatchTime(entry && entry.created_at ? entry.created_at : '')) + '</td>';
			html += '</tr>';
		});

		html += '</tbody></table>';
		return html;
	}

	function toggleHistoryDetails(root, button) {
		var batchId = button && button.getAttribute('data-pat-history-details');

		if (!batchId) {
			return;
		}

		var row = button.closest('.pat-history-entry');

		if (!row) {
			return;
		}

		var existing = row.nextElementSibling;

		if (existing && existing.classList.contains('pat-history-detail-row')) {
			existing.parentNode.removeChild(existing);
			button.textContent = 'Details';
			return;
		}

		requestSaveHistory(batchId).then(function (response) {
			var payload = response && response.data && !Array.isArray(response.entries) ? response.data : response;
			var entries = payload && Array.isArray(payload.entries) ? payload.entries : [];
			var detailRow = document.createElement('tr');
			var detailCell = document.createElement('td');

			detailRow.className = 'pat-history-detail-row';
			detailCell.colSpan = 6;
			detailCell.innerHTML = renderHistoryDetails(entries);
			detailRow.appendChild(detailCell);
			row.parentNode.insertBefore(detailRow, row.nextSibling);
			button.textContent = 'Hide';
		}).catch(function () {
			var detailRow = document.createElement('tr');
			var detailCell = document.createElement('td');

			detailRow.className = 'pat-history-detail-row';
			detailCell.colSpan = 6;
			detailCell.innerHTML = '<p class="pat-history-error">Could not load batch details.</p>';
			detailRow.appendChild(detailCell);
			row.parentNode.insertBefore(detailRow, row.nextSibling);
			button.textContent = 'Hide';
		});
	}

	function isHistoryPanelOpen(root) {
		var panel = root.querySelector('[data-pat-history-panel]');
		return panel && !panel.hidden;
	}

	function openHistoryPanel(root) {
		var panel = root.querySelector('[data-pat-history-panel]');

		if (!panel) {
			return;
		}

		panel.hidden = false;
		var list = panel.querySelector('[data-pat-history-list]');

		if (list) {
			list.innerHTML = '<p class="pat-history-loading">Loading history…</p>';
		}

		requestSaveHistory().then(function (response) {
			renderHistoryPanel(root, response);
		}).catch(function () {
			var panel2 = root.querySelector('[data-pat-history-panel]');
			var list2 = panel2 && panel2.querySelector('[data-pat-history-list]');

			if (list2) {
				list2.innerHTML = '<p class="pat-history-error">Could not load history.</p>';
			}
		});
	}

	function closeHistoryPanel(root) {
		var panel = root.querySelector('[data-pat-history-panel]');

		if (panel) {
			panel.hidden = true;
		}
	}

	function refreshHistoryPanelIfOpen(root) {
		if (isHistoryPanelOpen(root)) {
			requestSaveHistory().then(function (response) {
				renderHistoryPanel(root, response);
			});
		}
	}

	function bindHistoryPanel(root) {
		var historyTrigger = root.querySelector('[data-pat-history-trigger]');

		if (historyTrigger) {
			historyTrigger.addEventListener('click', function (event) {
				event.preventDefault();

				if (isHistoryPanelOpen(root)) {
					closeHistoryPanel(root);
				} else {
					openHistoryPanel(root);
				}
			});
		}

		root.addEventListener('click', function (event) {
			if (!root.contains(event.target)) {
				return;
			}

			var closeBtn = event.target.closest('[data-pat-history-close]');

			if (closeBtn) {
				event.preventDefault();
				closeHistoryPanel(root);
				return;
			}

			var undoBtn = event.target.closest('[data-pat-undo-batch-id]');

			if (undoBtn) {
				event.preventDefault();
				closeHistoryPanel(root);
				handleUndoClick(root, undoBtn.getAttribute('data-pat-undo-batch-id') || '');
				return;
			}

			var detailsBtn = event.target.closest('[data-pat-history-details]');

			if (detailsBtn) {
				event.preventDefault();
				toggleHistoryDetails(root, detailsBtn);
			}
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
