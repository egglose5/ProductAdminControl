<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Variation_Row_Renderer {
	const TEMPLATE_PATH = 'includes/admin/views/variation-rows.php';

	/**
	 * Render variation rows for a parent product.
	 *
	 * @param int|string|array $parent_reference Parent row DOM id, parent id, or a parent context array.
	 * @param array             $variation_rows   Normalized variation rows.
	 * @param array             $args             Optional render arguments.
	 * @return void
	 */
	public function render( $parent_reference, array $variation_rows = array(), array $args = array() ): void {
		echo $this->get_markup( $parent_reference, $variation_rows, $args );
	}

	/**
	 * Build the variation row markup.
	 *
	 * @param int|string|array $parent_reference Parent row DOM id, parent id, or a parent context array.
	 * @param array             $variation_rows   Normalized variation rows.
	 * @param array             $args             Optional render arguments.
	 * @return string
	 */
	public function get_markup( $parent_reference, array $variation_rows = array(), array $args = array() ): string {
		$template = $this->get_template_path();

		if ( ! file_exists( $template ) ) {
			return '';
		}

		$parent_context = $this->normalize_parent_reference( $parent_reference );
		$rows           = $this->normalize_variation_rows( $variation_rows );
		$parent_id      = isset( $parent_context['parent_id'] ) ? (int) $parent_context['parent_id'] : 0;
		$parent_dom_id   = isset( $parent_context['parent_dom_id'] ) ? (string) $parent_context['parent_dom_id'] : '';
		$empty_message   = isset( $args['empty_message'] ) ? (string) $args['empty_message'] : '';

		ob_start();
		include $template;
		return (string) ob_get_clean();
	}

	/**
	 * Get the template path.
	 *
	 * @return string
	 */
	private function get_template_path(): string {
		return trailingslashit( PAT_PLUGIN_PATH ) . ltrim( self::TEMPLATE_PATH, '/' );
	}

	/**
	 * Normalize the parent reference.
	 *
	 * @param int|string|array $parent_reference Parent row DOM id, parent id, or parent context array.
	 * @return array
	 */
	private function normalize_parent_reference( $parent_reference ): array {
		$parent_id = 0;
		$parent_dom_id = '';

		if ( is_array( $parent_reference ) ) {
			if ( isset( $parent_reference['parent_id'] ) ) {
				$parent_id = (int) $parent_reference['parent_id'];
			} elseif ( isset( $parent_reference['id'] ) ) {
				$parent_id = (int) $parent_reference['id'];
			}

			if ( isset( $parent_reference['parent_dom_id'] ) ) {
				$parent_dom_id = (string) $parent_reference['parent_dom_id'];
			} elseif ( isset( $parent_reference['dom_id'] ) ) {
				$parent_dom_id = (string) $parent_reference['dom_id'];
			}
		} elseif ( is_int( $parent_reference ) || ctype_digit( (string) $parent_reference ) ) {
			$parent_id = (int) $parent_reference;
		} elseif ( is_string( $parent_reference ) ) {
			$parent_dom_id = $parent_reference;
		}

		if ( '' === $parent_dom_id && $parent_id > 0 ) {
			$parent_dom_id = 'pat-row-' . $parent_id;
		}

		if ( 0 === $parent_id && '' !== $parent_dom_id ) {
			if ( preg_match( '/(?:^|[^0-9])(\d+)$/', $parent_dom_id, $matches ) ) {
				$parent_id = (int) $matches[1];
			}
		}

		return array(
			'parent_id'     => $parent_id,
			'parent_dom_id' => $parent_dom_id,
		);
	}

	/**
	 * Normalize variation rows to the renderer contract.
	 *
	 * @param array $variation_rows Normalized variation rows.
	 * @return array
	 */
	private function normalize_variation_rows( array $variation_rows ): array {
		$rows = array();

		foreach ( $variation_rows as $variation_row ) {
			if ( ! is_array( $variation_row ) ) {
				continue;
			}

			$row_id = isset( $variation_row['id'] ) ? (int) $variation_row['id'] : 0;
			$temp_id = isset( $variation_row['temp_id'] ) ? (string) $variation_row['temp_id'] : '';

			if ( $row_id <= 0 && '' !== $temp_id ) {
				$row_id = $temp_id;
			}

			$rows[] = array(
				'id'                => $row_id,
				'temp_id'           => $temp_id,
				'title'             => isset( $variation_row['title'] ) ? (string) $variation_row['title'] : '',
				'sku'               => isset( $variation_row['sku'] ) ? (string) $variation_row['sku'] : '',
				'price'             => isset( $variation_row['price'] ) ? (string) $variation_row['price'] : '',
				'regular_price'     => isset( $variation_row['regular_price'] ) ? (string) $variation_row['regular_price'] : ( isset( $variation_row['price'] ) ? (string) $variation_row['price'] : '' ),
				'sale_price'        => isset( $variation_row['sale_price'] ) ? (string) $variation_row['sale_price'] : '',
				'stock'             => isset( $variation_row['stock'] ) ? (string) $variation_row['stock'] : '',
				'stock_quantity'    => isset( $variation_row['stock_quantity'] ) ? (string) $variation_row['stock_quantity'] : ( isset( $variation_row['stock'] ) ? (string) $variation_row['stock'] : '' ),
				'status'            => isset( $variation_row['status'] ) ? (string) $variation_row['status'] : '',
				'menu_order'        => isset( $variation_row['menu_order'] ) ? (int) $variation_row['menu_order'] : 0,
				'is_generated'      => ! empty( $variation_row['is_generated'] ),
				'is_preview'        => ! empty( $variation_row['is_preview'] ),
				'attribute_summary' => isset( $variation_row['attribute_summary'] ) ? (string) $variation_row['attribute_summary'] : '',
			);
		}

		return $rows;
	}
}
