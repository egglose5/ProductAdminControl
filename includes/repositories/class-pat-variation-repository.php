<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Variation_Repository {
	/**
	 * Load variation rows for one or more parent product IDs.
	 *
	 * @param array $parent_ids Parent product IDs.
	 * @param array $args       Optional query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_by_parent_ids( array $parent_ids, array $args = array() ): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$parent_ids = $this->normalize_ids( $parent_ids );

		if ( empty( $parent_ids ) ) {
			return array();
		}

		$query_args = $this->build_query_args( $parent_ids, $args );
		$query      = new WP_Query( $query_args );
		$rows       = array();

		if ( empty( $query->posts ) ) {
			wp_reset_postdata();
			return array();
		}

		foreach ( $query->posts as $post ) {
			$row = $this->normalize_variation_row( $post );

			if ( null !== $row ) {
				$rows[] = $row;
			}
		}

		wp_reset_postdata();

		return $rows;
	}

	/**
	 * Load variation rows for a single parent product ID.
	 *
	 * @param int   $parent_id Parent product ID.
	 * @param array $args      Optional query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_by_parent_id( int $parent_id, array $args = array() ): array {
		return $this->find_by_parent_ids( array( $parent_id ), $args );
	}

	/**
	 * Normalize a variation post into a compact grid row.
	 *
	 * @param WP_Post $post Variation post object.
	 * @return array<string, mixed>|null
	 */
	private function normalize_variation_row( $post ): ?array {
		if ( ! $post instanceof WP_Post || 'product_variation' !== $post->post_type ) {
			return null;
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;

		$parent_id = (int) $post->post_parent;
		$sku       = '';
		$price     = '';
		$regular_price = '';
		$sale_price = '';
		$stock     = '';
		$menu_order = (int) $post->menu_order;
		$status    = $post->post_status;
		$summary   = $this->build_attribute_summary( $post, $product );

		if ( $product && is_object( $product ) ) {
			if ( method_exists( $product, 'get_sku' ) ) {
				$sku = (string) $product->get_sku();
			}

			if ( method_exists( $product, 'get_price' ) ) {
				$price = (string) $product->get_price();
			}

			if ( method_exists( $product, 'get_regular_price' ) ) {
				$regular_price = (string) $product->get_regular_price();
			}

			if ( method_exists( $product, 'get_sale_price' ) ) {
				$sale_price = (string) $product->get_sale_price();
			}

			if ( method_exists( $product, 'get_stock_quantity' ) ) {
				$stock_quantity = $product->get_stock_quantity();
				$stock          = null === $stock_quantity ? '' : (string) $stock_quantity;
			}

			if ( method_exists( $product, 'get_status' ) ) {
				$status = (string) $product->get_status();
			}

			if ( method_exists( $product, 'get_menu_order' ) ) {
				$menu_order = (int) $product->get_menu_order();
			}
		} else {
			$sku   = (string) get_post_meta( $post->ID, '_sku', true );
			$price = (string) get_post_meta( $post->ID, '_price', true );
			$regular_price = (string) get_post_meta( $post->ID, '_regular_price', true );
			$sale_price    = (string) get_post_meta( $post->ID, '_sale_price', true );

			$stock_quantity = get_post_meta( $post->ID, '_stock', true );
			$stock          = '' === $stock_quantity ? '' : (string) $stock_quantity;
		}

		return array(
			'id'               => (int) $post->ID,
			'parent_id'        => $parent_id,
			'title'            => (string) get_the_title( $post ),
			'summary'          => $summary,
			'sku'              => $sku,
			'stock'            => $stock,
			'status'           => $status,
			'price'            => $price,
			'regular_price'    => $regular_price,
			'sale_price'       => $sale_price,
			'menu_order'       => $menu_order,
			'attribute_summary' => $summary,
		);
	}

	/**
	 * Build a variation attribute summary string.
	 *
	 * @param WP_Post      $post    Variation post object.
	 * @param WC_Product|null $product Optional WooCommerce product object.
	 * @return string
	 */
	private function build_attribute_summary( $post, $product = null ): string {
		$attributes = array();

		if ( $product && is_object( $product ) && method_exists( $product, 'get_variation_attributes' ) ) {
			$variation_attributes = (array) $product->get_variation_attributes();

			foreach ( $variation_attributes as $attribute_name => $attribute_value ) {
				if ( '' === $attribute_value || null === $attribute_value ) {
					continue;
				}

				$label       = str_replace( 'attribute_', '', (string) $attribute_name );
				$label       = str_replace( array( 'pa_', '-' ), array( '', ' ' ), $label );
				$label       = ucwords( str_replace( array( '_', '/' ), ' ', $label ) );
				$attributes[] = trim( $label . ': ' . (string) $attribute_value );
			}
		} else {
			$post_meta = get_post_meta( $post->ID );

			foreach ( $post_meta as $meta_key => $meta_values ) {
				if ( 0 !== strpos( (string) $meta_key, 'attribute_' ) ) {
					continue;
				}

				$value = is_array( $meta_values ) ? reset( $meta_values ) : $meta_values;

				if ( '' === $value || null === $value ) {
					continue;
				}

				$label        = str_replace( array( 'attribute_', 'pa_', '-' ), array( '', '', ' ' ), (string) $meta_key );
				$label        = ucwords( str_replace( array( '_', '/' ), ' ', $label ) );
				$attributes[] = trim( $label . ': ' . (string) $value );
			}
		}

		return implode( ' | ', $attributes );
	}

	/**
	 * Build the WP_Query args used for variation lookups.
	 *
	 * @param array $parent_ids Parent IDs.
	 * @param array $args       Optional overrides.
	 * @return array
	 */
	private function build_query_args( array $parent_ids, array $args = array() ): array {
		$defaults = array(
			'post_type'      => 'product_variation',
			'post_parent__in' => $parent_ids,
			'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
			'posts_per_page' => -1,
			'orderby'       => array(
				'menu_order' => 'ASC',
				'ID'         => 'ASC',
			),
			'no_found_rows'  => true,
			'fields'         => 'all',
		);

		$args = wp_parse_args( $args, $defaults );

		if ( isset( $args['post_parent__in'] ) ) {
			$args['post_parent__in'] = $this->normalize_ids( (array) $args['post_parent__in'] );
		}

		return $args;
	}

	/**
	 * Normalize a list of IDs.
	 *
	 * @param array $ids Raw IDs.
	 * @return array<int>
	 */
	private function normalize_ids( array $ids ): array {
		$ids = array_map(
			static function ( $id ): int {
				return absint( $id );
			},
			$ids
		);

		$ids = array_filter( $ids );

		return array_values( array_unique( $ids ) );
	}
}
