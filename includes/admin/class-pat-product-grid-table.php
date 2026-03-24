<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Product_Grid_Table {
	const TEMPLATE_PATH = 'includes/admin/views/product-grid-table.php';

	/**
	 * Render the product grid.
	 *
	 * @param array $rows Grid rows. Parent rows may include a `variations` array.
	 * @param array $args Optional render args.
	 * @return void
	 */
	public function render( array $rows = array(), array $args = array() ): void {
		$template = $this->get_template_path();

		if ( ! file_exists( $template ) ) {
			return;
		}

		$columns = $this->get_columns();
		$empty_message = isset( $args['empty_message'] ) ? (string) $args['empty_message'] : __( 'No products match the current filter set.', 'product-admin-tool' );

		include $template;
	}

	/**
	 * Get the table columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'select'   => __( 'Select', 'product-admin-tool' ),
			'product'  => __( 'Product', 'product-admin-tool' ),
			'type'     => __( 'Type', 'product-admin-tool' ),
			'sku'      => __( 'SKU', 'product-admin-tool' ),
			'price'    => __( 'Price', 'product-admin-tool' ),
			'stock'    => __( 'Stock', 'product-admin-tool' ),
			'status'   => __( 'Status', 'product-admin-tool' ),
			'details'  => __( 'Details', 'product-admin-tool' ),
		);
	}

	/**
	 * Get the template path.
	 *
	 * @return string
	 */
	private function get_template_path(): string {
		return trailingslashit( PAT_PLUGIN_PATH ) . ltrim( self::TEMPLATE_PATH, '/' );
	}
}
