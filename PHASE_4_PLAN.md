# Phase 4 Plan: Hardening, Lazy Variation Loading, And Variation Generation

## Goal

Strengthen the Phase 3 editing baseline before introducing generated variation rows.

Phase 4 should not jump straight into row generation. The correct order is:

1. fix Phase 3 correctness gaps
2. implement real lazy-loaded variation expansion
3. add multi-line row selection behavior
4. add generated variation row preview and creation

That sequence reduces the risk of building generation logic on top of unstable save and row-state behavior.

## Recommended Phase 4 Structure

## Phase 4A: Hardening

Goal: make the current editing/save baseline trustworthy.

### Required Fixes

- preserve field-level save errors through the controller response
- fix row-state handling so reverted rows can return to `clean`
- validate `sale_price` correctly even when `regular_price` is not part of the same request
- verify toolbar/save counts stay accurate after partial failures
- ensure saved rows rehydrate correctly from server-normalized values

### Why This Comes First

Generated rows will multiply save operations and failure cases.

If Phase 3 error propagation and row-state handling are still weak, Phase 4 generation work will become harder to debug and less safe to use.

## Phase 4B: Real Lazy Variation Expansion

Goal: stop treating the current lazy-loading UI as a placeholder and make expansion actually load data on demand.

### Target Behavior

1. Initial page load fetches parent product rows only.
2. Expanding a variable product requests variation rows for that parent via AJAX.
3. Returned variation rows are rendered into the grid beneath the parent.
4. Re-expanding an already loaded parent should use cached client data unless explicitly refreshed.

### New Backend Pieces

- `PAT_Variation_Controller` or similar AJAX endpoint
- server-rendered variation row partial, or JSON row payload plus JS renderer

### New Frontend Pieces

- per-parent loading state
- row insertion beneath the parent row
- cached expanded variation payloads
- graceful error state if variation fetch fails

### Data Contract

Variation fetch response should return:

- parent product ID
- variation rows
- success/error status
- optional message

### Why This Comes Before Generation

Generated variation rows belong in the same visual/behavioral space as fetched variation rows. If expansion is not real yet, generated-row UX will be unstable and inconsistent.

## Phase 4C: Multi-Line Row Selection

Goal: introduce spreadsheet-style contiguous row selection that works across parent and loaded variation rows.

### Target Behavior

1. A row click selects one row.
2. Shift + click selects a contiguous range from the active anchor row.
3. Ctrl/Cmd + click toggles individual rows without clearing existing selection.
4. Keyboard shift + arrow extends selection up/down one row at a time.
5. Selection remains stable when rows are edited and saved.

### Selection Rules

- only currently rendered rows are selectable
- collapsed variation rows are not part of active selection
- when a parent is collapsed, child selections under that parent are cleared
- selection state is independent from dirty/save row state
- selection styling must be clearly distinct from dirty/error/saved styling

### Frontend Pieces

- enable row checkboxes for interaction instead of disabled placeholders
- add a client-side selection state manager keyed by row ID
- support mouse and keyboard range-selection semantics
- add toolbar selection count output (`selected rows`)
- preserve selection after variation lazy-load insertion/removal

### Why This Comes Before Generation

Generated rows add more selectable items and future bulk operations depend on trustworthy selection semantics. Building selection first avoids retrofitting generated-row UX later.

## Phase 4D: Generated Variation Row Preview

Goal: calculate and preview missing variation combinations before creation.

### Target Behavior

For a variable product:

1. Read the product's variation attributes.
2. Compute all valid combinations.
3. Compare those combinations against existing child variations.
4. Identify missing combinations only.
5. Show generated preview rows in the grid as unsaved/new rows.

### Preview Row Rules

- generated rows should be visually distinct from saved rows
- generated rows should have `row_type = variation`
- generated rows should include an internal marker like `is_generated = true`
- generated rows should remain editable before save
- generated rows should not exist in WooCommerce until explicitly saved

### Recommended Initial Field Defaults

Generated rows should start with:

- empty SKU
- inherited or blank price fields
- empty or inherited stock quantity
- attribute summary filled in
- `draft` or inherited status depending on product strategy

## Phase 4E: Variation Creation Path

Goal: persist generated variation rows safely.

### New Backend Responsibility

Add a dedicated variation creation service, separate from the update service.

Suggested class:

- `PAT_Variation_Generator_Service`

Responsibilities:

- validate parent product is variable
- validate requested attribute combination
- detect duplicates before creation
- create new `WC_Product_Variation`
- assign attributes
- assign editable fields
- save and return normalized row data

### Save Semantics

When the save payload includes generated rows:

- existing variation rows use update logic
- generated rows use creation logic
- result payloads must clearly distinguish `created` from `saved`

## Phase 4 Scope

### In Scope

- Phase 3 correctness fixes
- real variation fetch-on-expand
- variation loading state and cache
- multi-line contiguous row selection in the grid
- generated row preview for one parent at a time
- explicit save/create flow for generated variation rows

### Out Of Scope

- bulk variation generation across category trees
- bulk write actions across selected rows
- advanced generation presets
- undo/history
- custom-field generation templates
- full import/export

## UI Model

## Parent Rows

- still editable for parent product fields
- expand/collapse variations
- show loading/error state for variation fetch

## Variation Rows

- fetched lazily when expanded
- remain editable
- support save feedback

## Selection Model

- selection can include parent rows and visible variation rows
- anchor row is tracked for shift-range selection
- selection survives save responses and row rehydration
- selection count appears in the toolbar for future bulk-action readiness

## Generated Variation Rows

- appear under the parent alongside existing rows
- visually marked as `new` or `generated`
- removable before save
- become normal variation rows after successful creation

## Backend Architecture

### Existing Classes To Extend

- `PAT_Save_Controller`
- `PAT_Variation_Save_Service`
- `PAT_Product_Grid_Service`
- admin grid view template for selectable row checkboxes
- `assets/js/pat-admin.js` for selection state and range behavior
- `assets/css/pat-admin.css` for selected-row visual state

### New Classes To Add

- `PAT_Variation_Controller`
- `PAT_Variation_Row_Renderer` or JSON row serializer
- `PAT_Variation_Generator_Service`
- optional `PAT_Attribute_Repository`

## Recommended Request Shapes

## Variation Fetch

```json
{
  "parent_id": 123
}
```

Response:

```json
{
  "success": true,
  "parent_id": 123,
  "rows": [
    {
      "id": 456,
      "row_type": "variation",
      "parent_id": 123,
      "attribute_summary": "Leather Color: Brown",
      "sku": "NN-BS-001-BR",
      "regular_price": "42.00",
      "sale_price": "",
      "stock_quantity": 4,
      "status": "publish",
      "menu_order": 0
    }
  ]
}
```

## Generated Variation Preview

```json
{
  "parent_id": 123,
  "mode": "preview_missing"
}
```

Response:

```json
{
  "success": true,
  "parent_id": 123,
  "generated_rows": [
    {
      "temp_id": "gen-123-1",
      "row_type": "variation",
      "parent_id": 123,
      "is_generated": true,
      "attribute_summary": "Leather Color: Black | Size: A6",
      "attributes": {
        "pa_leather-color": "black",
        "pa_size": "a6"
      },
      "sku": "",
      "regular_price": "",
      "sale_price": "",
      "stock_quantity": "",
      "status": "draft",
      "menu_order": 0
    }
  ]
}
```

## Validation Rules

### Hardening Rules

- row status must clear from `error` when no dirty fields remain
- controller must pass field-level `errors` to the client
- server-side sale price validation must compare against persisted regular price if needed

### Selection Rules

- selection must not be lost when edited fields toggle dirty/clean
- shift-range selection must operate on currently visible row order
- collapsed child rows must not remain selected
- lazy-loaded child row insertion must not clear existing parent selection

### Variation Fetch Rules

- parent ID must exist
- parent product must be variable
- current user must have `manage_woocommerce`
- nonce must be valid

### Generation Rules

- parent must have variation attributes configured
- generated combinations must be unique
- combinations already represented by existing variations must not be generated again
- save path must reject duplicate combinations if race conditions occur

## Recommended Build Order

1. Fix the three known Phase 3 review findings.
2. Add variation fetch endpoint.
3. Add client-side on-expand fetch and row injection.
4. Add loaded/cached variation state.
5. Add multi-line selection state manager and interaction events.
6. Enable selection checkboxes and selected-row styling.
7. Add generated-row preview logic for one expanded parent.
8. Add variation creation service and save integration.
9. Add generated-row state styling and user feedback.

## Suggested Subagent Split

When implementation starts, Phase 4 can be split like this:

1. Phase 3 hardening fixes
2. variation fetch controller
3. variation row renderer/serializer
4. client-side lazy expansion and caching
5. grid selection state manager and keyboard support
6. generation service for missing combinations
7. generated-row UI and save integration

## Definition Of Done

Phase 4 is done when:

- Phase 3 review findings are fixed
- variation rows are fetched only when expanded
- expansion errors are visible and recoverable
- multi-line contiguous selection works with mouse and keyboard
- selection remains correct after expand/collapse and save cycles
- missing variation combinations can be previewed for a parent product
- generated rows can be saved into real WooCommerce variations
- duplicate combinations are prevented
- the first page load remains fast on large catalogs
