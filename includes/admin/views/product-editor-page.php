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
		<?php $filters->render( array( 'pagination' => $pagination, 'current_page_slug' => $current_page_slug ) ); ?>
	<?php endif; ?>

	<div class="pat-toolbar pat-action-bar">
		<button type="button" class="button" disabled="disabled"><?php esc_html_e( 'Bulk Edit', 'product-admin-tool' ); ?></button>
		<button type="button" class="button" disabled="disabled"><?php esc_html_e( 'Fill Down', 'product-admin-tool' ); ?></button>
		<button type="button" class="button" disabled="disabled"><?php esc_html_e( 'Generate Variations', 'product-admin-tool' ); ?></button>
		<span class="description"><?php esc_html_e( 'Bulk actions remain disabled. Variation preview generation is being added behind the scenes, but the first Phase 4 focus is hardening and lazy loading.', 'product-admin-tool' ); ?></span>
	</div>

	<?php if ( $grid_table instanceof PAT_Product_Grid_Table ) : ?>
		<?php $grid_table->render( $rows, array( 'empty_message' => __( 'No products matched the current filters or WooCommerce is not available.', 'product-admin-tool' ) ) ); ?>
	<?php endif; ?>
</div>
