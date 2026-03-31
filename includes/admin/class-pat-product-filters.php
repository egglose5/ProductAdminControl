<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Product_Filters {
	const TEMPLATE = 'includes/admin/views/product-filters.php';

	/**
	 * Render the filter bar shell.
	 */
	public function render( array $args = array() ): void {
		$template = PAT_PLUGIN_PATH . self::TEMPLATE;

		if ( ! file_exists( $template ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Product filters template is missing.', 'product-admin-tool' ) . '</p></div>';
			return;
		}

		$pagination = isset( $args['pagination'] ) && is_array( $args['pagination'] ) ? $args['pagination'] : array();
		$current_page_slug = isset( $args['current_page_slug'] ) ? (string) $args['current_page_slug'] : '';
		$applied_filters = isset( $args['applied_filters'] ) && is_array( $args['applied_filters'] ) ? $args['applied_filters'] : array();

		include $template;
	}

	/**
	 * Get the current search term from the request.
	 *
	 * @return string
	 */
	public function get_search_term(): string {
		return $this->get_request_string( 's' );
	}

	/**
	 * Get the current product status filter.
	 *
	 * @return string
	 */
	public function get_status_filter(): string {
		return $this->get_request_string( 'status' );
	}

	/**
	 * Get the current product category filter slug.
	 *
	 * @return string
	 */
	public function get_category_filter(): string {
		return sanitize_title( $this->get_request_string( 'category' ) );
	}

	/**
	 * Check if parent rows should be hidden in the editor view.
	 *
	 * @return bool
	 */
	public function get_variations_only(): bool {
		return $this->get_request_bool( 'variations_only' );
	}

	/**
	 * Get the current page size.
	 *
	 * @return int
	 */
	public function get_per_page(): int {
		$per_page = isset( $_GET['per_page'] ) ? absint( wp_unslash( $_GET['per_page'] ) ) : 20; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $per_page <= 0 ) {
			$per_page = 20;
		}

		return min( $per_page, 200 );
	}

	/**
	 * Get the current page number.
	 *
	 * @return int
	 */
	public function get_current_page(): int {
		$paged = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $paged <= 0 ) {
			$paged = 1;
		}

		return $paged;
	}

	/**
	 * Return the available status filter options.
	 *
	 * @return array<string,string>
	 */
	public function get_status_options(): array {
		return array(
			''        => __( 'All statuses', 'product-admin-tool' ),
			'publish' => __( 'Published', 'product-admin-tool' ),
			'draft'   => __( 'Draft', 'product-admin-tool' ),
			'pending'  => __( 'Pending review', 'product-admin-tool' ),
			'private' => __( 'Private', 'product-admin-tool' ),
		);
	}

	/**
	 * Return the available product category options keyed by term slug.
	 *
	 * @return array<string, string>
	 */
	public function get_category_options(): array {
		$options = array(
			'' => __( 'All categories', 'product-admin-tool' ),
		);

		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return $options;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return $options;
		}

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$options[ (string) $term->slug ] = (string) $term->name;
		}

		return $options;
	}

	/**
	 * Return the available per-page options.
	 *
	 * @return int[]
	 */
	public function get_per_page_options(): array {
		return array( 20, 50, 100, 200 );
	}

	/**
	 * Build a simple pagination summary placeholder.
	 *
	 * @return string
	 */
	public function get_pagination_summary( array $pagination = array() ): string {
		$state = $this->normalize_pagination( $pagination );

		return sprintf(
			/* translators: 1: starting row number, 2: ending row number, 3: total items */
			__( 'Showing %1$d-%2$d of %3$d', 'product-admin-tool' ),
			$state['range_start'],
			$state['range_end'],
			$state['total_items']
		);
	}

	/**
	 * Build a pagination URL for the current editor page.
	 *
	 * @param int    $page              Target page.
	 * @param string $current_page_slug Current editor page slug.
	 * @param array  $pagination        Optional pagination payload for clamping.
	 * @return string
	 */
	public function get_page_url( int $page, string $current_page_slug, array $pagination = array() ): string {
		$state = $this->normalize_pagination( $pagination );

		if ( $state['total_pages'] > 0 ) {
			$page = min( max( 1, $page ), $state['total_pages'] );
		} else {
			$page = 1;
		}

		$args = array(
			'page'     => $current_page_slug,
			'paged'    => $page,
			's'        => $this->get_search_term(),
			'status'   => $this->get_status_filter(),
			'category' => $this->get_category_filter(),
			'variations_only' => $this->get_variations_only() ? '1' : '',
			'per_page' => $this->get_per_page(),
		);

		return add_query_arg(
			array_filter(
				$args,
				static function ( $value ) {
					return '' !== $value && null !== $value;
				}
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Normalize pagination data into a bounded state array.
	 *
	 * @param array $pagination Pagination payload.
	 * @return array<string, int|bool>
	 */
	public function normalize_pagination( array $pagination = array() ): array {
		$page        = isset( $pagination['page'] ) ? max( 1, absint( $pagination['page'] ) ) : $this->get_current_page();
		$per_page    = isset( $pagination['per_page'] ) ? max( 1, absint( $pagination['per_page'] ) ) : $this->get_per_page();
		$total_items = isset( $pagination['total_items'] ) ? max( 0, absint( $pagination['total_items'] ) ) : 0;
		$total_pages = isset( $pagination['total_pages'] ) ? max( 0, absint( $pagination['total_pages'] ) ) : 0;

		if ( $total_pages > 0 ) {
			$page = min( $page, $total_pages );
		} else {
			$page = 1;
		}

		if ( 0 === $total_items ) {
			return array(
				'page'              => $page,
				'per_page'          => $per_page,
				'total_items'       => 0,
				'total_pages'       => 0,
				'range_start'       => 0,
				'range_end'         => 0,
				'has_next_page'     => false,
				'has_previous_page' => false,
			);
		}

		$range_start = ( ( $page - 1 ) * $per_page ) + 1;
		$range_end   = min( $page * $per_page, $total_items );

		return array(
			'page'              => $page,
			'per_page'          => $per_page,
			'total_items'       => $total_items,
			'total_pages'       => $total_pages,
			'range_start'       => $range_start,
			'range_end'         => $range_end,
			'has_next_page'     => $total_pages > 0 && $page < $total_pages,
			'has_previous_page' => $page > 1,
		);
	}

	/**
	 * Get a sanitized string request value.
	 *
	 * @param string $key Request key.
	 * @return string
	 */
	private function get_request_string( string $key ): string {
		if ( ! isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}

		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Get a sanitized boolean request value.
	 *
	 * @param string $key Request key.
	 * @return bool
	 */
	private function get_request_bool( string $key ): bool {
		if ( ! isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		$value = strtolower( sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
	}
}
