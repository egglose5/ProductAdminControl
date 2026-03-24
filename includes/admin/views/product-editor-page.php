<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap pat-product-editor">
	<h1><?php esc_html_e( 'Product Editor', 'product-admin-tool' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'This screen will become the spreadsheet-style editor for WooCommerce products and variations.', 'product-admin-tool' ); ?>
	</p>

	<div class="pat-toolbar pat-filter-bar">
		<label for="pat-search" class="screen-reader-text"><?php esc_html_e( 'Search products', 'product-admin-tool' ); ?></label>
		<input id="pat-search" type="search" class="regular-text" placeholder="<?php esc_attr_e( 'Search products, SKUs, or variations', 'product-admin-tool' ); ?>" />

		<select id="pat-family-filter">
			<option value=""><?php esc_html_e( 'All product families', 'product-admin-tool' ); ?></option>
			<option value="neverending-notebooks"><?php esc_html_e( 'Neverending Notebooks', 'product-admin-tool' ); ?></option>
		</select>

		<select id="pat-status-filter">
			<option value=""><?php esc_html_e( 'All statuses', 'product-admin-tool' ); ?></option>
			<option value="publish"><?php esc_html_e( 'Published', 'product-admin-tool' ); ?></option>
			<option value="draft"><?php esc_html_e( 'Draft', 'product-admin-tool' ); ?></option>
		</select>

		<button type="button" class="button button-primary"><?php esc_html_e( 'Apply Filters', 'product-admin-tool' ); ?></button>
	</div>

	<div class="pat-toolbar pat-action-bar">
		<button type="button" class="button"><?php esc_html_e( 'Bulk Edit', 'product-admin-tool' ); ?></button>
		<button type="button" class="button"><?php esc_html_e( 'Fill Down', 'product-admin-tool' ); ?></button>
		<button type="button" class="button"><?php esc_html_e( 'Generate Variations', 'product-admin-tool' ); ?></button>
		<span class="description"><?php esc_html_e( 'Actions will operate on the current category scope and its child categories.', 'product-admin-tool' ); ?></span>
	</div>

	<div class="pat-grid-shell">
		<table class="widefat fixed striped pat-grid">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" disabled="disabled" /></th>
					<th><?php esc_html_e( 'Product', 'product-admin-tool' ); ?></th>
					<th><?php esc_html_e( 'Type', 'product-admin-tool' ); ?></th>
					<th><?php esc_html_e( 'SKU', 'product-admin-tool' ); ?></th>
					<th><?php esc_html_e( 'Price', 'product-admin-tool' ); ?></th>
					<th><?php esc_html_e( 'Stock', 'product-admin-tool' ); ?></th>
					<th><?php esc_html_e( 'Status', 'product-admin-tool' ); ?></th>
					<th><?php esc_html_e( 'Notes', 'product-admin-tool' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr class="pat-empty-state">
					<td colspan="8">
						<strong><?php esc_html_e( 'Products and variations will load here later.', 'product-admin-tool' ); ?></strong>
						<p><?php esc_html_e( 'Phase 1 only provides the layout shell. The grid, row expansion, and edit actions will be wired in the next phase.', 'product-admin-tool' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
