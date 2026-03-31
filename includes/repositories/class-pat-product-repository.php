<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only repository for parent WooCommerce products.
 */
class PAT_Product_Repository {
	/**
	 * Fetch a paginated set of parent products for the editor grid.
	 *
	 * Supported args:
	 * - page: int
	 * - per_page: int
	 * - search: string
	 * - status: string|array
	 * - category: string (product_cat slug)
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_page( array $args = array() ): array {
		if ( ! function_exists( 'wc_get_product' ) || ! function_exists( 'wc_format_decimal' ) ) {
			return array(
				'rows'       => array(),
				'pagination' => array(
					'page'        => isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1,
					'per_page'    => isset( $args['per_page'] ) ? max( 1, absint( $args['per_page'] ) ) : 20,
					'total_items' => 0,
					'total_pages' => 0,
				),
				'filters'    => $this->build_filters_payload( $this->normalize_query_args( $args ) ),
			);
		}

		$query_args = $this->normalize_query_args( $args );
		$rows       = array();
		$query      = null;

		if ( '' !== $query_args['category'] && empty( $query_args['category_scope']['ids'] ) ) {
			return array(
				'rows'       => array(),
				'pagination' => array(
					'page'        => $query_args['page'],
					'per_page'    => $query_args['per_page'],
					'total_items' => 0,
					'total_pages' => 0,
				),
				'filters'    => $this->build_filters_payload( $query_args ),
			);
		}

		if ( '' === $query_args['search'] ) {
			$query = new WP_Query( $this->build_query_args( $query_args ) );
		} else {
			$matching_ids = $this->find_matching_product_ids( $query_args['search'], $query_args['status'] );

			if ( empty( $matching_ids ) ) {
				return array(
					'rows'       => array(),
					'pagination' => array(
						'page'        => $query_args['page'],
						'per_page'    => $query_args['per_page'],
						'total_items' => 0,
						'total_pages' => 0,
					),
					'filters'    => $this->build_filters_payload( $query_args ),
				);
			}

			$search_args              = $this->build_query_args( $query_args );
			$search_args['post__in']   = $matching_ids;
			$search_args['s']          = '';
			$search_args['orderby']    = array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			);

			$query = new WP_Query( $search_args );
		}

		if ( $query instanceof WP_Query ) {
			foreach ( $query->posts as $post_id ) {
				$product = wc_get_product( $post_id );

				if ( ! $product instanceof WC_Product ) {
					continue;
				}

				$rows[] = $this->normalize_product_row( $product );
			}
		}

		return array(
			'rows'       => $rows,
			'pagination' => array(
				'page'        => max( 1, (int) $query_args['page'] ),
				'per_page'    => max( 1, (int) $query_args['per_page'] ),
				'total_items' => $query instanceof WP_Query ? (int) $query->found_posts : 0,
				'total_pages' => $query instanceof WP_Query ? (int) $query->max_num_pages : 0,
			),
			'filters'    => $this->build_filters_payload( $query_args ),
		);
	}

	/**
	 * Build the normalized query arguments used to fetch products.
	 *
	 * @param array $args Input arguments.
	 * @return array
	 */
	private function normalize_query_args( array $args ): array {
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$per_page = isset( $args['per_page'] ) ? max( 1, absint( $args['per_page'] ) ) : 20;
		$search   = isset( $args['search'] ) ? sanitize_text_field( wp_unslash( $args['search'] ) ) : '';
		$status   = $this->normalize_status_filter( $args['status'] ?? '' );
		$category = isset( $args['category'] ) ? sanitize_title( wp_unslash( (string) $args['category'] ) ) : '';
		$category_scope = $this->resolve_category_scope( $category );

		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'search'   => $search,
			'status'   => $status,
			'category' => $category,
			'category_scope' => $category_scope,
		);
	}

	/**
	 * Build the WP_Query arguments used to fetch parent products.
	 *
	 * @param array $args Normalized query arguments.
	 * @return array
	 */
	private function build_query_args( array $args ): array {
		$query_args = array(
			'post_type'              => 'product',
			'post_parent'            => 0,
			'post_status'            => $args['status'] ? $args['status'] : array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page'         => $args['per_page'],
			'paged'                  => $args['page'],
			'orderby'                => 'menu_order title',
			'order'                  => 'ASC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'suppress_filters'       => false,
			'fields'                 => 'ids',
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);

		if ( '' !== $args['search'] ) {
			$query_args['s'] = $args['search'];
		}

		if ( '' !== $args['category'] ) {
			$category_ids = isset( $args['category_scope']['ids'] ) && is_array( $args['category_scope']['ids'] )
				? array_values( array_filter( array_map( 'absint', $args['category_scope']['ids'] ) ) )
				: array();

			$query_args['tax_query'] = array(
				array(
					'taxonomy'         => 'product_cat',
					'field'            => 'term_id',
					'terms'            => $category_ids,
					'operator'         => 'IN',
					'include_children' => false,
				),
			);
		}

		return $query_args;
	}

	/**
	 * Find parent product IDs matching search across title/content, parent SKU, and variation SKU.
	 *
	 * @param string       $search Search term.
	 * @param string|array $status Status filter.
	 * @return array<int>
	 */
	private function find_matching_product_ids( string $search, $status ): array {
		$matched_ids = array();

		$matched_ids = array_merge(
			$matched_ids,
			$this->find_parent_ids_by_text_search( $search, $status ),
			$this->find_parent_ids_by_sku_search( $search, $status ),
			$this->find_parent_ids_by_variation_sku_search( $search )
		);

		return $this->unique_ids_preserve_order( $matched_ids );
	}

	/**
	 * Find parent product IDs using the standard content search.
	 *
	 * @param string       $search Search term.
	 * @param string|array $status Status filter.
	 * @return array<int>
	 */
	private function find_parent_ids_by_text_search( string $search, $status ): array {
		$query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_parent'            => 0,
				'post_status'            => $status ? $status : array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => -1,
				'orderby'                => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				's'                      => $search,
			)
		);

		return array_map( 'absint', (array) $query->posts );
	}

	/**
	 * Find parent product IDs by parent SKU.
	 *
	 * @param string       $search Search term.
	 * @param string|array $status Status filter.
	 * @return array<int>
	 */
	private function find_parent_ids_by_sku_search( string $search, $status ): array {
		$query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_parent'            => 0,
				'post_status'            => $status ? $status : array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => -1,
				'orderby'                => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => '_sku',
						'value'   => $search,
						'compare' => 'LIKE',
					),
				),
			)
		);

		return array_map( 'absint', (array) $query->posts );
	}

	/**
	 * Find parent product IDs by variation SKU matches.
	 *
	 * @param string $search Search term.
	 * @return array<int>
	 */
	private function find_parent_ids_by_variation_sku_search( string $search ): array {
		$query = new WP_Query(
			array(
				'post_type'              => 'product_variation',
				'post_status'            => array( 'publish', 'private', 'draft', 'pending' ),
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => '_sku',
						'value'   => $search,
						'compare' => 'LIKE',
					),
				),
			)
		);

		$parent_ids = array();

		foreach ( (array) $query->posts as $variation_id ) {
			$parent_id = absint( get_post_field( 'post_parent', $variation_id ) );

			if ( $parent_id > 0 ) {
				$parent_ids[] = $parent_id;
			}
		}

		return $parent_ids;
	}

	/**
	 * Deduplicate IDs while preserving first-seen order.
	 *
	 * @param array<int> $ids IDs to deduplicate.
	 * @return array<int>
	 */
	private function unique_ids_preserve_order( array $ids ): array {
		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Normalize a product object into a compact row representation.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function normalize_product_row( WC_Product $product ): array {
		$term_names = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );

		if ( is_wp_error( $term_names ) ) {
			$term_names = array();
		}

		return array(
			'id'             => $product->get_id(),
			'parent_id'      => 0,
			'title'          => $product->get_name(),
			'type'           => $product->get_type(),
			'status'         => $product->get_status(),
			'sku'            => (string) $product->get_sku(),
			'regular_price'  => $this->format_price( $product->get_regular_price() ),
			'sale_price'     => $this->format_price( $product->get_sale_price() ),
			'price'          => $this->format_price( $product->get_price() ),
			'stock_status'   => $product->get_stock_status(),
			'stock_quantity' => $product->get_stock_quantity(),
			'weight'         => method_exists( $product, 'get_weight' ) ? (string) $product->get_weight() : '',
			'length'         => method_exists( $product, 'get_length' ) ? (string) $product->get_length() : '',
			'width'          => method_exists( $product, 'get_width' ) ? (string) $product->get_width() : '',
			'height'         => method_exists( $product, 'get_height' ) ? (string) $product->get_height() : '',
			'shipping_class_id' => method_exists( $product, 'get_shipping_class_id' ) ? (int) $product->get_shipping_class_id() : 0,
			'menu_order'     => (int) $product->get_menu_order(),
			'categories'     => array_values( array_filter( array_map( 'strval', (array) $term_names ) ) ),
			'has_children'   => $product->is_type( 'variable' ),
			'permalink'      => get_permalink( $product->get_id() ),
			'modified'       => get_post_modified_time( 'c', false, $product->get_id() ),
		);
	}

	/**
	 * Format a WooCommerce price string for grid display.
	 *
	 * @param string|int|float|null $price Raw price value.
	 * @return string
	 */
	private function format_price( $price ): string {
		if ( '' === $price || null === $price ) {
			return '';
		}

		return wc_format_decimal( $price, wc_get_price_decimals() );
	}

	/**
	 * Normalize the requested status filter.
	 *
	 * @param mixed $status Status filter input.
	 * @return string|array
	 */
	private function normalize_status_filter( $status ) {
		if ( is_array( $status ) ) {
			$status = array_filter( array_map( 'sanitize_key', $status ) );
			return $status ? array_values( $status ) : '';
		}

		$status = sanitize_key( (string) $status );

		if ( '' === $status || 'any' === $status ) {
			return '';
		}

		return $status;
	}

	/**
	 * Build normalized filters payload for grid consumers.
	 *
	 * @param array $query_args Normalized query args.
	 * @return array<string, mixed>
	 */
	private function build_filters_payload( array $query_args ): array {
		$category_scope = isset( $query_args['category_scope'] ) && is_array( $query_args['category_scope'] )
			? $query_args['category_scope']
			: array();

		return array(
			'search'   => isset( $query_args['search'] ) ? (string) $query_args['search'] : '',
			'status'   => isset( $query_args['status'] ) ? $query_args['status'] : '',
			'category' => isset( $query_args['category'] ) ? (string) $query_args['category'] : '',
			'category_scope' => array(
				'selected_term_id' => isset( $category_scope['selected_term_id'] ) ? absint( $category_scope['selected_term_id'] ) : 0,
				'selected_name'    => isset( $category_scope['selected_name'] ) ? (string) $category_scope['selected_name'] : '',
				'descendant_count' => isset( $category_scope['descendant_count'] ) ? max( 0, absint( $category_scope['descendant_count'] ) ) : 0,
				'total_term_count' => isset( $category_scope['total_term_count'] ) ? max( 0, absint( $category_scope['total_term_count'] ) ) : 0,
				'parent_product_count' => isset( $category_scope['parent_product_count'] ) ? max( 0, absint( $category_scope['parent_product_count'] ) ) : 0,
				'variation_row_count'  => isset( $category_scope['variation_row_count'] ) ? max( 0, absint( $category_scope['variation_row_count'] ) ) : 0,
			),
		);
	}

	/**
	 * Resolve selected category into a term scope including descendants.
	 *
	 * @param string $category_slug Selected category slug.
	 * @return array<string, mixed>
	 */
	private function resolve_category_scope( string $category_slug ): array {
		$scope = array(
			'ids'             => array(),
			'selected_term_id' => 0,
			'selected_name'   => '',
			'descendant_count' => 0,
			'total_term_count' => 0,
			'parent_product_count' => 0,
			'variation_row_count'  => 0,
		);

		if ( '' === $category_slug || ! taxonomy_exists( 'product_cat' ) ) {
			return $scope;
		}

		$term = get_term_by( 'slug', $category_slug, 'product_cat' );

		if ( ! $term instanceof WP_Term ) {
			return $scope;
		}

		$term_ids      = array( (int) $term->term_id );
		$descendants   = get_term_children( (int) $term->term_id, 'product_cat' );

		if ( ! is_wp_error( $descendants ) && ! empty( $descendants ) ) {
			$term_ids = array_merge( $term_ids, array_map( 'intval', $descendants ) );
		}

		$term_ids = array_values( array_unique( array_filter( array_map( 'absint', $term_ids ) ) ) );

		$scope['ids']              = $term_ids;
		$scope['selected_term_id'] = (int) $term->term_id;
		$scope['selected_name']    = (string) $term->name;
		$scope['total_term_count'] = count( $term_ids );
		$scope['descendant_count'] = max( 0, $scope['total_term_count'] - 1 );

		$scope_counts = $this->count_category_scope_rows( $term_ids );
		$scope['parent_product_count'] = $scope_counts['parent_product_count'];
		$scope['variation_row_count']  = $scope_counts['variation_row_count'];

		return $scope;
	}

	/**
	 * Count parent product and variation rows for a category scope.
	 *
	 * @param array<int> $term_ids Scoped product category term IDs.
	 * @return array<string, int>
	 */
	private function count_category_scope_rows( array $term_ids ): array {
		$term_ids = array_values( array_filter( array_map( 'absint', $term_ids ) ) );

		if ( empty( $term_ids ) ) {
			return array(
				'parent_product_count' => 0,
				'variation_row_count'  => 0,
			);
		}

		$parent_count_query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_parent'            => 0,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => 1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => false,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => $term_ids,
						'operator'         => 'IN',
						'include_children' => false,
					),
				),
			)
		);

		$parent_product_count = (int) $parent_count_query->found_posts;

		if ( $parent_product_count <= 0 ) {
			return array(
				'parent_product_count' => 0,
				'variation_row_count'  => 0,
			);
		}

		$parent_ids_query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_parent'            => 0,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => $term_ids,
						'operator'         => 'IN',
						'include_children' => false,
					),
				),
			)
		);

		$parent_ids = array_values( array_filter( array_map( 'absint', (array) $parent_ids_query->posts ) ) );
		$variation_row_count = 0;

		if ( ! empty( $parent_ids ) ) {
			foreach ( array_chunk( $parent_ids, 200 ) as $parent_chunk ) {
				$variation_count_query = new WP_Query(
					array(
						'post_type'              => 'product_variation',
						'post_status'            => array( 'publish', 'private', 'draft', 'pending' ),
						'posts_per_page'         => 1,
						'orderby'                => 'ID',
						'order'                  => 'ASC',
						'ignore_sticky_posts'    => true,
						'no_found_rows'          => false,
						'fields'                 => 'ids',
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
						'post_parent__in'        => $parent_chunk,
					)
				);

				$variation_row_count += (int) $variation_count_query->found_posts;
			}
		}

		return array(
			'parent_product_count' => max( 0, $parent_product_count ),
			'variation_row_count'  => max( 0, $variation_row_count ),
		);
	}
}
