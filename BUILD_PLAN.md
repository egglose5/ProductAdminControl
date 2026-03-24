# Product Admin Tool Build Plan

## Product Goal

Build a standalone WooCommerce admin plugin that provides a spreadsheet-style editor for products and variations without changing the storefront experience.

The plugin should help manage large catalogs where:

- each design is its own WooCommerce product
- products may include one or more variations
- editing many records from the standard WooCommerce admin is too slow
- row creation should be assisted programmatically instead of requiring repetitive manual entry

## Core Requirements

### Must Have

- Admin-only product grid
- Support for simple products, variable products, and individual variations
- Inline editing for common fields
- Bulk save flow
- Search and filter tools
- Category-scoped actions that always include child categories
- Programmatic row generation for variation rows
- No frontend rendering changes

### Nice to Have

- Saved views or presets
- Bulk actions across selected rows
- Import/export for the visible grid
- Undo or change log
- Custom field support

### Non-Goals for MVP

- Replacing WooCommerce product pages
- Replacing category archives
- Frontend design changes
- Full import/export engine
- Complex workflow automation

## MVP Scope

The first usable version should allow an admin to:

1. Open a dedicated Product Admin Tool screen.
2. View parent products in a tabular grid.
3. Expand a variable product to see its variations as child rows.
4. Edit basic fields for both products and variations.
5. Generate missing variation rows programmatically from selected attributes.
6. Save changes in a predictable batch flow.

## Recommended Data Model

### Editable Product Fields

- ID
- Title
- SKU
- Status
- Regular price
- Sale price
- Stock status
- Stock quantity
- Product type
- Parent product
- Menu order

### Editable Variation Fields

- Variation ID
- Parent ID
- SKU
- Enabled status
- Regular price
- Sale price
- Stock status
- Stock quantity
- Attribute values
- Menu order

### Future Optional Fields

- Custom meta fields
- Catalog family
- Internal sort value
- Migration reference
- QA status

## Programmatic Row Generation

This needs a strict definition early because it affects both UI and save logic.

### Proposed Meaning

Programmatic row generation should:

- read a variable product's declared variation attributes
- calculate the valid attribute combinations
- compare them against existing child variations
- generate missing rows in the grid before save
- optionally create those missing variations on save

### Example

If a product has:

- `Leather Color` = `Brown`, `Black`
- `Size` = `A5`, `A6`

the plugin should be able to generate four variation rows:

- Brown / A5
- Brown / A6
- Black / A5
- Black / A6

If two already exist, only the missing two should be created.

## Architecture

## Admin UI Layer

Create a dedicated admin page under Product Admin Tool.

Primary responsibilities:

- render the grid shell
- handle filters and search
- enqueue admin assets
- expose AJAX or REST endpoints

Suggested classes:

- `PAT_Admin_Menu`
- `PAT_Product_Editor_Page`
- `PAT_Admin_Assets`

## Data Access Layer

Use a repository-style layer so the table UI does not call WooCommerce objects directly everywhere.

Primary responsibilities:

- query products and variations
- normalize Woo data into row objects
- fetch taxonomies and attributes

Suggested classes:

- `PAT_Product_Repository`
- `PAT_Variation_Repository`
- `PAT_Attribute_Repository`

## Row Mapping Layer

Build a dedicated mapper that turns WooCommerce data into a consistent grid row format.

Primary responsibilities:

- produce product rows
- produce variation rows
- mark editable fields
- flag missing or invalid values

Suggested classes:

- `PAT_Row_Mapper`
- `PAT_Row_Collection`

## Save Layer

Saving needs to be structured and defensive because variation writes can fail in multiple ways.

Primary responsibilities:

- validate payloads
- update products
- update variations
- create new variation records when needed
- return row-level success or error messages

Suggested classes:

- `PAT_Save_Controller`
- `PAT_Product_Save_Service`
- `PAT_Variation_Save_Service`
- `PAT_Variation_Generator_Service`

## Frontend Tech Choice for Admin Screen

Recommended first pass:

- server-rendered PHP page
- lightweight JavaScript for row expansion, editing state, and save requests

Avoid a heavy React admin app in v1 unless the grid becomes too complex. The first milestone should prove the workflow before introducing a larger frontend stack.

## Save Strategy

Use a staged save model.

### Step 1

Collect edited rows client-side.

### Step 2

Send a structured payload to a secure endpoint.

### Step 3

Validate capabilities, nonces, product ownership, and field rules.

### Step 4

Process parent product updates first.

### Step 5

Process variation updates and creations second.

### Step 6

Return a result set with:

- updated rows
- created rows
- failed rows
- error messages per row

## Category Scope Rule

Bulk actions and iteration flows should treat category targeting as recursive by default.

If the user targets a top-level product category, the plugin should automatically include:

- that category
- all descendant product categories
- all products assigned anywhere in that descendant tree

This should not be an optional toggle in the first version. It is the default behavior.

To reduce accidental damage, the UI should show a scope preview before execution:

- selected top-level category
- number of descendant categories included
- number of parent products affected
- number of variation rows affected

## Security and Safety

- Require `manage_woocommerce`
- Use nonces on all save actions
- Sanitize every editable field
- Validate Woo product types before writes
- Prevent accidental writes to unsupported product types in MVP
- Log save failures for admin review

## Performance Guardrails

- Paginate results
- Lazy-load variation rows when a parent row expands
- Avoid loading the full catalog into memory on first page load
- Cache static lookup data where reasonable
- Save in batches

## Phase Plan

## Phase 1: Foundation

Goal: get the plugin ready for real feature work.

- establish class structure
- add admin page shell
- add asset loading
- add WooCommerce dependency checks
- add basic coding conventions

## Phase 2: Read-Only Grid

Goal: display products and their variations cleanly.

- query products with pagination
- render product rows
- add expand/collapse for variations
- lazy-load variation rows
- add search and basic filters

## Phase 3: Inline Editing

Goal: allow safe edits for common fields.

- add editable cells
- track dirty rows
- save product edits
- save variation edits
- return row-level validation errors

## Phase 4: Programmatic Variation Row Generation

Goal: generate missing variation rows from parent attributes.

- inspect parent variation attributes
- generate missing combinations
- show preview rows in grid
- create missing variations on save
- prevent duplicate combinations

## Phase 5: Catalog Workflow Improvements

Goal: make the tool useful for ongoing operations.

- custom field support
- bulk selection and bulk edits
- saved filters
- export current view

## Selection and Bulk Action Semantics

Selection needs two different concepts:

### Row Selection

Used for direct spreadsheet actions on visible rows.

Examples:

- set selected rows to draft
- fill down SKU prefix
- update selected variation prices

### Category Scope Selection

Used for catalog-wide actions based on taxonomy scope.

Examples:

- update all products under `Neverending Notebooks` and every child category
- generate rows for all variable products under a category tree
- mark all products in a category branch as reviewed

For MVP, category scope selection should expand recursively and then operate on the resulting product set.

## Recommended Build Order

1. Build the read-only product grid first.
2. Add variation expansion and lazy loading.
3. Add save infrastructure before inline editing.
4. Add inline editing for a small field set.
5. Add variation generation after the save layer is stable.
6. Add bulk editing and custom fields after the core workflow works.

## Immediate Next Implementation Tasks

1. Add a dedicated editor page class separate from the menu class.
2. Add asset registration for a future grid script and stylesheet.
3. Add a product repository that returns paginated WooCommerce products.
4. Render a simple read-only table with product rows.
5. Add an expand action that can later load variations.

## Open Decisions

- Which fields belong in the very first editable set
- Whether to use admin AJAX or REST routes for saves
- Whether categories should be visible in the grid by default
- Whether custom fields are MVP or phase 5
- Whether generated rows should be preview-only until explicit confirmation
