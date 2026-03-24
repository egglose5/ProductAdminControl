# Phase 4 Plan: Hardening, Lazy Variation Loading, And Variation Generation

## Goal

Strengthen the Phase 3 editing baseline before introducing generated variation rows.

Phase 4 should not jump straight into row generation. The correct order is:

1. fix Phase 3 correctness gaps
2. implement real lazy-loaded variation expansion
3. add generated variation row preview and creation

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

## Phase 4C: Generated Variation Row Preview

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

## Phase 4D: Variation Creation Path

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
- generated row preview for one parent at a time
- explicit save/create flow for generated variation rows

### Out Of Scope

- bulk variation generation across category trees
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
5. Add generated-row preview logic for one expanded parent.
6. Add variation creation service and save integration.
7. Add generated-row state styling and user feedback.

## Suggested Subagent Split

When implementation starts, Phase 4 can be split like this:

1. Phase 3 hardening fixes
2. variation fetch controller
3. variation row renderer/serializer
4. client-side lazy expansion and caching
5. generation service for missing combinations
6. generated-row UI and save integration

## Definition Of Done

Phase 4 is done when:

- Phase 3 review findings are fixed
- variation rows are fetched only when expanded
- expansion errors are visible and recoverable
- missing variation combinations can be previewed for a parent product
- generated rows can be saved into real WooCommerce variations
- duplicate combinations are prevented
- the first page load remains fast on large catalogs
