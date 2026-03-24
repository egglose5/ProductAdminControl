<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Product_Editor_Page {
	const PAGE_SLUG = 'pat-product-editor';

	public function get_page_slug(): string {
		return self::PAGE_SLUG;
	}

	public function can_access(): bool {
		return current_user_can( PAT_Requirements::required_capability() );
	}

	public function render(): void {
		if ( ! $this->can_access() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'product-admin-tool' ) );
		}

		$template = PAT_PLUGIN_PATH . 'includes/admin/views/product-editor-page.php';

		if ( ! file_exists( $template ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Product editor template is missing.', 'product-admin-tool' ) . '</p></div>';
			return;
		}

		include $template;
	}
}
