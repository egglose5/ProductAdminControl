<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$filters      = isset( $filters ) && $filters instanceof PAT_Product_Filters ? $filters : new PAT_Product_Filters();
$search_term  = $filters->get_search_term();
$status_value = $filters->get_status_filter();
$category_value = $filters->get_category_filter();
$applied_filters = isset( $applied_filters ) && is_array( $applied_filters ) ? $applied_filters : array();
$category_scope = isset( $applied_filters['category_scope'] ) && is_array( $applied_filters['category_scope'] ) ? $applied_filters['category_scope'] : array();
$category_descendants = isset( $category_scope['descendant_count'] ) ? max( 0, absint( $category_scope['descendant_count'] ) ) : 0;
$category_selected_name = isset( $category_scope['selected_name'] ) ? (string) $category_scope['selected_name'] : '';
$category_parent_count = isset( $category_scope['parent_product_count'] ) ? max( 0, absint( $category_scope['parent_product_count'] ) ) : 0;
$category_variation_count = isset( $category_scope['variation_row_count'] ) ? max( 0, absint( $category_scope['variation_row_count'] ) ) : 0;
$per_page     = $filters->get_per_page();
$pagination   = isset( $pagination ) && is_array( $pagination ) ? $pagination : array();
$state        = $filters->normalize_pagination( $pagination );
$page         = $state['page'];
$summary      = $filters->get_pagination_summary( $pagination );
$total_pages  = $state['total_pages'];
$has_previous = $state['has_previous_page'];
$has_next     = $state['has_next_page'];
$previous_url = $has_previous ? $filters->get_page_url( max( 1, $page - 1 ), $current_page_slug, $pagination ) : '';
$next_url     = $has_next ? $filters->get_page_url( $page + 1, $current_page_slug, $pagination ) : '';

?>
<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
	<input type="hidden" name="page" value="<?php echo esc_attr( $current_page_slug ); ?>" />
	<input type="hidden" name="paged" value="1" />

	<div class="pat-toolbar pat-filter-bar" aria-label="<?php esc_attr_e( 'Product filters', 'product-admin-tool' ); ?>">
		<label class="screen-reader-text" for="pat-search"><?php esc_html_e( 'Search products', 'product-admin-tool' ); ?></label>
		<input
			id="pat-search"
			name="s"
			type="search"
			class="regular-text"
			value="<?php echo esc_attr( $search_term ); ?>"
			placeholder="<?php esc_attr_e( 'Search products, SKUs, or variations', 'product-admin-tool' ); ?>"
		/>

		<label class="screen-reader-text" for="pat-status-filter"><?php esc_html_e( 'Filter by status', 'product-admin-tool' ); ?></label>
		<select id="pat-status-filter" name="status">
			<?php foreach ( $filters->get_status_options() as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status_value, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="pat-category-filter"><?php esc_html_e( 'Filter by category', 'product-admin-tool' ); ?></label>
		<select id="pat-category-filter" name="category">
			<?php foreach ( $filters->get_category_options() as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $category_value, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="pat-per-page"><?php esc_html_e( 'Rows per page', 'product-admin-tool' ); ?></label>
		<select id="pat-per-page" name="per_page">
			<?php foreach ( $filters->get_per_page_options() as $option ) : ?>
				<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( $per_page, $option ); ?>>
					<?php echo esc_html( number_format_i18n( $option ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Filters', 'product-admin-tool' ); ?></button>

		<label style="margin-left: 20px; font-weight: normal; display: flex; align-items: center;">
			<input
				type="checkbox"
				name="variations_only"
				data-pat-variations-only-filter="true"
				style="margin-right: 6px;"
			/>
			<?php esc_html_e( 'Show variations only', 'product-admin-tool' ); ?>
		</label>
	</div>
	<?php if ( '' !== $category_value ) : ?>
		<p class="description">
			<?php
			$selected_category_label = '' !== $category_selected_name ? $category_selected_name : $category_value;

			if ( $category_descendants > 0 ) {
				echo esc_html(
					sprintf(
						/* translators: 1: category name, 2: descendant category count, 3: affected parent products, 4: affected variation rows */
						__( 'Scope preview: "%1$s" + %2$d subcategories, %3$d parent products, %4$d variation rows.', 'product-admin-tool' ),
						$selected_category_label,
						$category_descendants,
						$category_parent_count,
						$category_variation_count
					)
				);
			} else {
				echo esc_html(
					sprintf(
						/* translators: 1: category name, 2: affected parent products, 3: affected variation rows */
						__( 'Scope preview: "%1$s", %2$d parent products, %3$d variation rows.', 'product-admin-tool' ),
						$selected_category_label,
						$category_parent_count,
						$category_variation_count
					)
				);
			}
			?>
		</p>
	<?php endif; ?>
</form>

<div class="pat-pagination-summary">
	<span><?php echo esc_html( $summary ); ?></span>
	<?php if ( $total_pages > 0 ) : ?>
		<span class="description"><?php echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'product-admin-tool' ), $page, $total_pages ) ); ?></span>
	<?php endif; ?>
	<div class="pat-pagination-actions">
		<?php if ( $has_previous ) : ?>
			<a class="button" href="<?php echo esc_url( $previous_url ); ?>"><?php esc_html_e( 'Previous', 'product-admin-tool' ); ?></a>
		<?php else : ?>
			<span class="button disabled" aria-disabled="true"><?php esc_html_e( 'Previous', 'product-admin-tool' ); ?></span>
		<?php endif; ?>
		<?php if ( $has_next ) : ?>
			<a class="button" href="<?php echo esc_url( $next_url ); ?>"><?php esc_html_e( 'Next', 'product-admin-tool' ); ?></a>
		<?php else : ?>
			<span class="button disabled" aria-disabled="true"><?php esc_html_e( 'Next', 'product-admin-tool' ); ?></span>
		<?php endif; ?>
	</div>
</div>
