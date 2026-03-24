# Phase 3 Plan: Inline Editing And Save Pipeline

## Goal

Turn the read-only product grid into a safe, spreadsheet-style editor for common WooCommerce product and variation fields.

Phase 3 should introduce editing without yet taking on the full bulk-action system or programmatic variation generation. The focus is:

- editable cells
- dirty-state tracking
- validation
- save requests
- row-level save results

## Target Outcome

At the end of Phase 3, an admin should be able to:

1. Open the Product Editor screen.
2. See parent product rows in the grid.
3. Edit a limited set of parent product fields inline.
4. Mark rows as dirty when values change.
5. Save a batch of changed product rows.
6. Receive per-row success or error feedback.

Variation editing can begin in Phase 3 if the save pipeline is stable, but parent product editing should land first.

## Phase 3 Scope

### In Scope

- editable parent product cells
- dirty-state tracking in the browser
- save toolbar and save button state
- AJAX save endpoint
- nonce and capability checks
- field sanitization and validation
- parent product save service
- row-level save results shown in the grid

### Optional If Stable

- variation row editing for the same field set
- basic optimistic UI refresh of updated values

### Out Of Scope

- bulk actions across selected rows
- recursive category execution
- programmatic variation row generation
- undo/history
- custom field editing
- true lazy-loaded variation fetch

## Recommended First Editable Fields

Keep the first editable field set narrow.

### Parent Product Fields

- `title`
- `sku`
- `status`
- `regular_price`
- `sale_price`
- `stock_quantity`
- `menu_order`

### Variation Fields

If variation editing is included late in the phase, use the same reduced set:

- `sku`
- `regular_price`
- `sale_price`
- `stock_quantity`
- `status`
- `menu_order`

## Editing Behavior

### Cell Interaction

- click into a cell to edit
- leave read-only cells alone
- tab should move forward through editable cells
- shift-tab should move backward
- enter should commit the current cell edit
- escape should revert the current cell to its original value

### Dirty Tracking

Track changes at both levels:

- cell dirty state
- row dirty state

The row should remember:

- original values
- current draft values
- validation errors
- save status (`clean`, `dirty`, `saving`, `saved`, `error`)

### Save Behavior

- only dirty rows are sent
- save button is disabled when there are no dirty rows
- save button is disabled while a batch is in flight
- rows save in one request payload
- each row gets its own success/error result back

## Backend Architecture

## New Classes To Add

### Controllers

- `PAT_Save_Controller`

Responsibilities:

- register AJAX action
- verify nonce
- verify `manage_woocommerce`
- decode and validate request payload
- dispatch row saves
- return structured JSON response

### Services

- `PAT_Product_Save_Service`
- `PAT_Variation_Save_Service`

Responsibilities:

- sanitize field values
- load WooCommerce product objects
- reject unsupported product types or invalid rows
- apply field updates
- save objects safely
- return normalized result payloads

### Support

- `PAT_Row_Validation`
- `PAT_Save_Result`

Responsibilities:

- reusable field validation rules
- standard result shape for UI handling

## Save Request Shape

Use a single batch payload.

```json
{
  "rows": [
    {
      "id": 123,
      "row_type": "product",
      "changes": {
        "title": "Bogborne Sage",
        "sku": "NN-BS-001",
        "regular_price": "42.00",
        "sale_price": "",
        "stock_quantity": "8",
        "status": "publish",
        "menu_order": 10
      }
    }
  ]
}
```

## Save Response Shape

```json
{
  "success": true,
  "results": [
    {
      "id": 123,
      "row_type": "product",
      "status": "saved",
      "message": "Saved successfully.",
      "data": {
        "title": "Bogborne Sage",
        "sku": "NN-BS-001",
        "regular_price": "42.00",
        "sale_price": "",
        "stock_quantity": 8,
        "status": "publish",
        "menu_order": 10
      }
    }
  ]
}
```

For validation failures:

```json
{
  "success": false,
  "results": [
    {
      "id": 123,
      "row_type": "product",
      "status": "error",
      "message": "SKU is invalid.",
      "errors": {
        "sku": "SKU is invalid."
      }
    }
  ]
}
```

## Validation Rules

### General

- row ID must be valid
- row type must be recognized
- capability must be `manage_woocommerce`
- nonce must match

### Field Rules

- `title`: non-empty string after sanitize
- `sku`: string, allow empty, must not collide if uniqueness enforcement is added
- `status`: must be one of allowed Woo statuses
- `regular_price`: decimal or empty
- `sale_price`: decimal or empty
- `stock_quantity`: integer or empty
- `menu_order`: integer

### Safety Rules

- do not allow edits to unsupported product types in the first pass
- reject rows that do not load as WooCommerce products
- for now, only allow editing known whitelisted fields

## Frontend Architecture

## JS Responsibilities

The existing admin script should gain a small state layer.

Suggested responsibilities:

- discover editable cells
- store original/current row values
- mark rows dirty
- validate obvious client-side issues
- assemble save payload
- submit save request
- update row UI from response

### Suggested DOM Conventions

- `data-pat-row-id`
- `data-pat-row-type`
- `data-pat-field`
- `data-pat-original-value`
- `data-pat-status`

### UI Signals

- dirty cell highlight
- dirty row badge or border
- saving row state
- saved row flash
- inline error message area

## Recommended Build Order

1. Add save controller and result contract.
2. Add parent product save service.
3. Convert a small set of parent cells to editable inputs.
4. Add JS dirty tracking for parent rows.
5. Add save button and batch save request.
6. Render row-level save feedback.
7. Add variation editing only after parent editing is stable.

## Implementation Slices

## Slice 1: Save Foundation

- create save controller
- register AJAX endpoint
- return mock success for valid payload structure

## Slice 2: Product Row Editing

- make parent product cells editable
- track dirty rows in JS
- send changed rows to backend

## Slice 3: Real Product Saves

- implement product save service
- persist whitelisted product fields
- refresh row values from response

## Slice 4: Validation And Feedback

- add row-level errors
- highlight invalid cells
- preserve dirty rows on partial failure

## Slice 5: Variation Editing

- allow editing visible variation rows
- add variation save service
- keep variation row handling separate from parent product saves

## Risks To Control

- accidental writes to the wrong product type
- row shape drift between frontend and backend payloads
- save responses that do not fully re-normalize values
- race conditions if save is triggered twice
- price/stock values being sanitized differently on client and server

## Definition Of Done

Phase 3 is done when:

- the Product Editor supports inline editing for parent product rows
- dirty rows are clearly visible
- a batch save request persists edits successfully
- invalid rows return structured errors without breaking the grid
- the editor remains stable on large paginated result sets
- all PHP files lint cleanly and the JS path works without console errors in normal use

## Suggested Next Subagent Split

When implementation begins, Phase 3 can be split into:

1. save controller and routing
2. product save service
3. variation save service scaffold
4. editable grid markup changes
5. frontend dirty-state and save JS
6. save-result rendering and row status UI
