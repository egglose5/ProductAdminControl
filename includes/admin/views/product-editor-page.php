<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap pat-product-editor">
	<h1><?php esc_html_e( 'Product Editor', 'product-admin-tool' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Phase 2 shows a read-only WooCommerce product grid with child variation rows. Editing and bulk actions come next.', 'product-admin-tool' ); ?>
	</p>

	<?php if ( ! $woocommerce_available ) : ?>
		<div class="notice notice-warning inline"><p><?php echo esc_html( PAT_Requirements::missing_woocommerce_notice() ); ?></p></div>
	<?php endif; ?>

	<?php if ( $filters instanceof PAT_Product_Filters ) : ?>
		<?php $filters->render( array( 'pagination' => $pagination, 'current_page_slug' => $current_page_slug ) ); ?>
	<?php endif; ?>

	<div class="pat-toolbar pat-action-bar">
		<button type="button" class="button" disabled="disabled"><?php esc_html_e( 'Bulk Edit', 'product-admin-tool' ); ?></button>
		<button type="button" class="button" disabled="disabled"><?php esc_html_e( 'Fill Down', 'product-admin-tool' ); ?></button>
		<button type="button" class="button" disabled="disabled"><?php esc_html_e( 'Generate Variations', 'product-admin-tool' ); ?></button>
		<span class="description"><?php esc_html_e( 'Bulk actions remain disabled until the save pipeline is built. Category-scoped execution will still default to parent categories plus all descendants.', 'product-admin-tool' ); ?></span>
	</div>

	<?php if ( $grid_table instanceof PAT_Product_Grid_Table ) : ?>
		<?php $grid_table->render( $rows, array( 'empty_message' => __( 'No products matched the current filters or WooCommerce is not available.', 'product-admin-tool' ) ) ); ?>
	<?php endif; ?>
</div>
