<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Admin_Menu {
	private $editor_page;

	public function __construct( PAT_Product_Editor_Page $editor_page ) {
		$this->editor_page = $editor_page;
	}

	public function register(): void {
		add_menu_page(
			__( 'Product Admin Tool', 'product-admin-tool' ),
			__( 'Product Admin Tool', 'product-admin-tool' ),
			PAT_Requirements::required_capability(),
			PAT_Admin_Screen::get_menu_slug(),
			array( $this, 'render_dashboard' ),
			'dashicons-products',
			56
		);

		add_submenu_page(
			PAT_Admin_Screen::get_menu_slug(),
			__( 'Editor', 'product-admin-tool' ),
			__( 'Editor', 'product-admin-tool' ),
			PAT_Requirements::required_capability(),
			$this->editor_page->get_page_slug(),
			array( $this, 'render_editor' )
		);

		remove_submenu_page( PAT_Admin_Screen::get_menu_slug(), PAT_Admin_Screen::get_menu_slug() );
	}

	public function render_dashboard(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Product Admin Tool', 'product-admin-tool' ); ?></h1>
			<p><?php esc_html_e( 'This plugin will host a custom WooCommerce product and variation editor without changing the customer-facing catalog.', 'product-admin-tool' ); ?></p>
			<p><?php esc_html_e( 'Next step: build the product grid, variation rows, and programmatic row generation logic.', 'product-admin-tool' ); ?></p>
		</div>
		<?php
	}

	public function render_editor(): void {
		$this->editor_page->render();
	}
}
