<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap pat-product-editor" data-pat-editor-root="true" data-pat-editor-mode="<?php echo esc_attr( isset( $editor_mode ) ? (string) $editor_mode : 'phase-3-shell' ); ?>" data-pat-editor-state="idle">
	<h1><?php esc_html_e( 'Product Editor', 'product-admin-tool' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Phase 4 hardens the save baseline and converts variation expansion into a real lazy-loaded AJAX flow while keeping the storefront untouched.', 'product-admin-tool' ); ?>
	</p>

	<?php if ( ! $woocommerce_available ) : ?>
		<div class="notice notice-warning inline"><p><?php echo esc_html( PAT_Requirements::missing_woocommerce_notice() ); ?></p></div>
	<?php endif; ?>

	<div class="pat-toolbar pat-save-bar" data-pat-save-toolbar="true" aria-label="<?php esc_attr_e( 'Save changes toolbar', 'product-admin-tool' ); ?>">
		<button type="button" class="button button-primary" data-pat-save-trigger="true" disabled="disabled"><?php esc_html_e( 'Save changes', 'product-admin-tool' ); ?></button>
		<div class="pat-save-meta">
			<span class="pat-save-state" data-pat-save-status="idle" aria-live="polite"><?php esc_html_e( 'No pending changes.', 'product-admin-tool' ); ?></span>
			<span class="pat-save-count" data-pat-dirty-count="true"><?php esc_html_e( '0 changed rows', 'product-admin-tool' ); ?></span>
			<span class="pat-save-count" data-pat-selected-count="true"><?php esc_html_e( '0 selected rows', 'product-admin-tool' ); ?></span>
		</div>
		<span class="description"><?php esc_html_e( 'Nothing is written until Save changes is pressed. Only dirty rows are included in a save batch, and recent saves are recorded in the history ledger below.', 'product-admin-tool' ); ?></span>
	</div>

	<?php if ( $filters instanceof PAT_Product_Filters ) : ?>
		<?php $filters->render( array( 'pagination' => $pagination, 'current_page_slug' => $current_page_slug, 'applied_filters' => isset( $grid_data['filters'] ) && is_array( $grid_data['filters'] ) ? $grid_data['filters'] : array() ) ); ?>
	<?php endif; ?>

	<div class="pat-toolbar pat-action-bar">
		<button type="button" class="button" data-pat-bulk-edit-trigger="true" disabled="disabled"><?php esc_html_e( 'Bulk Edit', 'product-admin-tool' ); ?></button>
		<button type="button" class="button" data-pat-fill-down-trigger="true" disabled="disabled"><?php esc_html_e( 'Fill Down', 'product-admin-tool' ); ?></button>
		<button type="button" class="button" data-pat-open-editor-trigger="true" disabled="disabled" title="<?php esc_attr_e( 'Load variation rows for selected parent products', 'product-admin-tool' ); ?>"><?php esc_html_e( 'Open in Editor', 'product-admin-tool' ); ?></button>
		<button type="button" class="button" data-pat-generate-variations-trigger="true" disabled="disabled"><?php esc_html_e( 'Generate Variations', 'product-admin-tool' ); ?></button>
		<button type="button" class="button" data-pat-undo-trigger="true"><?php esc_html_e( 'Undo Last Save', 'product-admin-tool' ); ?></button>
		<button type="button" class="button" data-pat-history-trigger="true" title="<?php esc_attr_e( 'View recent save history', 'product-admin-tool' ); ?>"><?php esc_html_e( 'Save History', 'product-admin-tool' ); ?></button>
		<button type="button" class="button" data-pat-deselect-parents-trigger="true" title="<?php esc_attr_e( 'Deselect parent product rows', 'product-admin-tool' ); ?>"><?php esc_html_e( 'Deselect parent products', 'product-admin-tool' ); ?></button>
		<button type="button" class="button" data-pat-hide-parents-trigger="true" title="<?php esc_attr_e( 'Hide parent product rows', 'product-admin-tool' ); ?>"><?php esc_html_e( 'Hide parent products', 'product-admin-tool' ); ?></button>
		<span class="description"><?php esc_html_e( 'Select rows to use Bulk Edit or Fill Down. Generate Variations is ready to use.', 'product-admin-tool' ); ?></span>
	</div>

	<div class="pat-history-panel" data-pat-history-panel="true" hidden aria-label="<?php esc_attr_e( 'Save history', 'product-admin-tool' ); ?>">
		<div class="pat-history-panel-header">
			<strong><?php esc_html_e( 'Recent Save Ledger', 'product-admin-tool' ); ?></strong>
			<button type="button" class="button-link pat-history-panel-close" data-pat-history-close="true" aria-label="<?php esc_attr_e( 'Close history panel', 'product-admin-tool' ); ?>">&#x2715;</button>
		</div>
		<div class="pat-history-list" data-pat-history-list="true">
			<p class="pat-history-loading"><?php esc_html_e( 'Loading history\xe2\x80\xa6', 'product-admin-tool' ); ?></p>
		</div>
	</div>

	<div class="pat-toolbar pat-bulk-edit-bar" data-pat-bulk-edit-bar="true" hidden aria-label="<?php esc_attr_e( 'Bulk edit selected rows', 'product-admin-tool' ); ?>">
		<strong class="pat-bulk-edit-label"><?php esc_html_e( 'Bulk edit', 'product-admin-tool' ); ?> <span data-pat-bulk-edit-row-count>0 <?php esc_html_e( 'rows', 'product-admin-tool' ); ?></span>:</strong>
		<select class="pat-bulk-edit-field" data-pat-bulk-field-select aria-label="<?php esc_attr_e( 'Field to bulk edit', 'product-admin-tool' ); ?>">
			<option value=""><?php esc_html_e( '— Select field —', 'product-admin-tool' ); ?></option>
			<option value="title"><?php esc_html_e( 'Title', 'product-admin-tool' ); ?></option>
			<option value="sku"><?php esc_html_e( 'SKU', 'product-admin-tool' ); ?></option>
			<option value="status"><?php esc_html_e( 'Status', 'product-admin-tool' ); ?></option>
			<option value="regular_price"><?php esc_html_e( 'Regular Price', 'product-admin-tool' ); ?></option>
			<option value="sale_price"><?php esc_html_e( 'Sale Price', 'product-admin-tool' ); ?></option>
			<option value="stock_quantity"><?php esc_html_e( 'Stock Quantity', 'product-admin-tool' ); ?></option>
			<option value="weight"><?php esc_html_e( 'Weight', 'product-admin-tool' ); ?></option>
			<option value="length"><?php esc_html_e( 'Length', 'product-admin-tool' ); ?></option>
			<option value="width"><?php esc_html_e( 'Width', 'product-admin-tool' ); ?></option>
			<option value="height"><?php esc_html_e( 'Height', 'product-admin-tool' ); ?></option>
			<option value="package_type"><?php esc_html_e( 'Package Type', 'product-admin-tool' ); ?></option>
			<option value="menu_order"><?php esc_html_e( 'Menu Order', 'product-admin-tool' ); ?></option>
		</select>
		<select class="pat-bulk-edit-value-status" data-pat-bulk-value-status style="display:none" aria-label="<?php esc_attr_e( 'Status value', 'product-admin-tool' ); ?>">
			<option value="publish"><?php esc_html_e( 'Published', 'product-admin-tool' ); ?></option>
			<option value="draft"><?php esc_html_e( 'Draft', 'product-admin-tool' ); ?></option>
			<option value="pending"><?php esc_html_e( 'Pending Review', 'product-admin-tool' ); ?></option>
			<option value="private"><?php esc_html_e( 'Private', 'product-admin-tool' ); ?></option>
		</select>
		<input type="text" class="regular-text pat-bulk-edit-value-text" data-pat-bulk-value-text style="display:none" placeholder="<?php esc_attr_e( 'Value...', 'product-admin-tool' ); ?>" aria-label="<?php esc_attr_e( 'Bulk edit value', 'product-admin-tool' ); ?>" />
		<label style="margin-left: 15px; font-weight: normal; display: flex; align-items: center;">
			<input
				type="checkbox"
				data-pat-bulk-exclude-parents="true"
				style="margin-right: 6px;"
				aria-label="<?php esc_attr_e( 'Exclude parent rows from operation', 'product-admin-tool' ); ?>"
			/>
			<?php esc_html_e( 'Exclude parents', 'product-admin-tool' ); ?>
		</label>
		<button type="button" class="button button-primary" data-pat-bulk-apply="true"><?php esc_html_e( 'Apply to selection', 'product-admin-tool' ); ?></button>
		<button type="button" class="button" data-pat-bulk-cancel="true"><?php esc_html_e( 'Cancel', 'product-admin-tool' ); ?></button>
	</div>

	<?php if ( $grid_table instanceof PAT_Product_Grid_Table ) : ?>
		<?php $grid_table->render( $rows, array( 'empty_message' => __( 'No products matched the current filters or WooCommerce is not available.', 'product-admin-tool' ) ) ); ?>
	<?php endif; ?>
</div>
