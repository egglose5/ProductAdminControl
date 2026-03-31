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
		<span class="description"><?php esc_html_e( 'Only dirty rows will be included in the next batch save. Parent product rows are the primary Phase 3 target; variation editing remains scaffolded but secondary.', 'product-admin-tool' ); ?></span>
	</div>

	<?php if ( $filters instanceof PAT_Product_Filters ) : ?>
		<?php $filters->render( array( 'pagination' => $pagination, 'current_page_slug' => $current_page_slug, 'applied_filters' => isset( $grid_data['filters'] ) && is_array( $grid_data['filters'] ) ? $grid_data['filters'] : array() ) ); ?>
	<?php endif; ?>

	<div class="pat-toolbar pat-action-bar">
		<button type="button" class="button" data-pat-bulk-edit-trigger="true" disabled="disabled"><?php esc_html_e( 'Bulk Edit', 'product-admin-tool' ); ?></button>
		<button type="button" class="button" disabled="disabled"><?php esc_html_e( 'Fill Down', 'product-admin-tool' ); ?></button>
		<button type="button" class="button" data-pat-generate-variations-trigger="true" disabled="disabled"><?php esc_html_e( 'Generate Variations', 'product-admin-tool' ); ?></button>
		<span class="description"><?php esc_html_e( 'Select rows to use Bulk Edit. Fill Down and Generate Variations are scheduled for later phases.', 'product-admin-tool' ); ?></span>
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
			<option value="menu_order"><?php esc_html_e( 'Menu Order', 'product-admin-tool' ); ?></option>
		</select>
		<select class="pat-bulk-edit-value-status" data-pat-bulk-value-status style="display:none" aria-label="<?php esc_attr_e( 'Status value', 'product-admin-tool' ); ?>">
			<option value="publish"><?php esc_html_e( 'Published', 'product-admin-tool' ); ?></option>
			<option value="draft"><?php esc_html_e( 'Draft', 'product-admin-tool' ); ?></option>
			<option value="pending"><?php esc_html_e( 'Pending Review', 'product-admin-tool' ); ?></option>
			<option value="private"><?php esc_html_e( 'Private', 'product-admin-tool' ); ?></option>
		</select>
		<input type="text" class="regular-text pat-bulk-edit-value-text" data-pat-bulk-value-text style="display:none" placeholder="<?php esc_attr_e( 'Value...', 'product-admin-tool' ); ?>" aria-label="<?php esc_attr_e( 'Bulk edit value', 'product-admin-tool' ); ?>" />
		<button type="button" class="button button-primary" data-pat-bulk-apply="true"><?php esc_html_e( 'Apply to selection', 'product-admin-tool' ); ?></button>
		<button type="button" class="button" data-pat-bulk-cancel="true"><?php esc_html_e( 'Cancel', 'product-admin-tool' ); ?></button>
	</div>

	<?php if ( $grid_table instanceof PAT_Product_Grid_Table ) : ?>
		<?php $grid_table->render( $rows, array( 'empty_message' => __( 'No products matched the current filters or WooCommerce is not available.', 'product-admin-tool' ) ) ); ?>
	<?php endif; ?>
</div>
