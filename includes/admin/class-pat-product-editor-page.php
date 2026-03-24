<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Product_Editor_Page {
	const PAGE_SLUG = 'pat-product-editor';

	private $filters;
	private $grid_service;
	private $grid_table;

	public function __construct() {
		$this->filters      = class_exists( 'PAT_Product_Filters' ) ? new PAT_Product_Filters() : null;
		$this->grid_service = class_exists( 'PAT_Product_Grid_Service' ) ? new PAT_Product_Grid_Service() : null;
		$this->grid_table   = class_exists( 'PAT_Product_Grid_Table' ) ? new PAT_Product_Grid_Table() : null;
	}

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

		$filters               = $this->filters;
		$grid_table            = $this->grid_table;
		$grid_data             = $this->get_grid_data();
		$rows                  = isset( $grid_data['rows'] ) && is_array( $grid_data['rows'] ) ? $grid_data['rows'] : array();
		$pagination            = isset( $grid_data['pagination'] ) && is_array( $grid_data['pagination'] ) ? $grid_data['pagination'] : array();
		$current_page_slug     = $this->get_page_slug();
		$woocommerce_available = PAT_Requirements::has_woocommerce();
		$template              = PAT_PLUGIN_PATH . 'includes/admin/views/product-editor-page.php';

		if ( ! file_exists( $template ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Product editor template is missing.', 'product-admin-tool' ) . '</p></div>';
			return;
		}

		include $template;
	}

	private function get_grid_data(): array {
		if ( ! $this->grid_service || ! PAT_Requirements::has_woocommerce() ) {
			return array(
				'rows'       => array(),
				'pagination' => array(
					'page'              => 1,
					'per_page'          => 20,
					'total_items'       => 0,
					'total_pages'       => 0,
					'range_start'       => 0,
					'range_end'         => 0,
					'has_next_page'     => false,
					'has_previous_page' => false,
				),
			);
		}

		return $this->grid_service->get_page(
			array(
				'page'               => $this->filters ? $this->filters->get_current_page() : 1,
				'per_page'           => $this->filters ? $this->filters->get_per_page() : 20,
				'search'             => $this->filters ? $this->filters->get_search_term() : '',
				'status'             => $this->filters ? $this->filters->get_status_filter() : '',
				'include_variations' => true,
			)
		);
	}
}
