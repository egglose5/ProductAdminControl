<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$filters      = isset( $filters ) && $filters instanceof PAT_Product_Filters ? $filters : new PAT_Product_Filters();
$search_term  = $filters->get_search_term();
$status_value = $filters->get_status_filter();
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

		<label class="screen-reader-text" for="pat-per-page"><?php esc_html_e( 'Rows per page', 'product-admin-tool' ); ?></label>
		<select id="pat-per-page" name="per_page">
			<?php foreach ( $filters->get_per_page_options() as $option ) : ?>
				<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( $per_page, $option ); ?>>
					<?php echo esc_html( number_format_i18n( $option ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Filters', 'product-admin-tool' ); ?></button>
	</div>
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
