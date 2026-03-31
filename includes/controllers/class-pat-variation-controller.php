<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX controller for lazy variation fetching.
 */
class PAT_Variation_Controller {
	const AJAX_ACTION = 'pat_fetch_variations';
	const NONCE_ACTION = 'pat-fetch-variations';
	const NONCE_FIELD  = 'nonce';

	/**
	 * @var PAT_Variation_Repository|null
	 */
	private $variation_repository;

	/**
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Constructor.
	 *
	 * @param PAT_Variation_Repository|null $variation_repository Optional injected repository.
	 */
	public function __construct( $variation_repository = null ) {
		$this->variation_repository = is_object( $variation_repository ) ? $variation_repository : null;
	}

	/**
	 * Register the authenticated AJAX hook.
	 */
	public function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;

		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax_fetch_variations' ) );
	}

	/**
	 * Handle the lazy variation fetch request.
	 */
	public function handle_ajax_fetch_variations(): void {
		$parent_id = $this->get_parent_id_from_request();

		if ( $parent_id <= 0 ) {
			$this->send_response(
				false,
				0,
				array(),
				__( 'A valid parent product ID is required.', 'product-admin-tool' ),
				400
			);
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			$this->send_response(
				false,
				$parent_id,
				array(),
				__( 'You do not have permission to fetch variations.', 'product-admin-tool' ),
				403
			);
		}

		if ( ! $this->verify_nonce() ) {
			$this->send_response(
				false,
				$parent_id,
				array(),
				__( 'Security check failed.', 'product-admin-tool' ),
				403
			);
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			$this->send_response(
				false,
				$parent_id,
				array(),
				__( 'WooCommerce is not available.', 'product-admin-tool' ),
				503
			);
		}

		$product = wc_get_product( $parent_id );

		if ( ! is_object( $product ) ) {
			$this->send_response(
				false,
				$parent_id,
				array(),
				__( 'Parent product could not be loaded.', 'product-admin-tool' ),
				404
			);
		}

		if ( method_exists( $product, 'is_type' ) && ! $product->is_type( 'variable' ) ) {
			$this->send_response(
				false,
				$parent_id,
				array(),
				__( 'Parent product is not variable.', 'product-admin-tool' ),
				400
			);
		}

		$rows = $this->load_variation_rows( $parent_id );

		if ( is_wp_error( $rows ) ) {
			$this->send_response(
				false,
				$parent_id,
				array(),
				$rows->get_error_message(),
				503
			);
		}

		$rows    = $this->normalize_rows( $rows, $parent_id );
		$message = empty( $rows ) ? __( 'No variation rows were found for this product.', 'product-admin-tool' ) : '';
		$html    = $this->render_rows_markup( $parent_id, $rows );

		$this->send_response( true, $parent_id, $rows, $message, 200, $html );
	}

	/**
	 * Resolve the requested parent product ID.
	 *
	 * @return int
	 */
	private function get_parent_id_from_request(): int {
		$keys = array( 'parent_id', 'product_id', 'id' );

		foreach ( $keys as $key ) {
			if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				continue;
			}

			return absint( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		return 0;
	}

	/**
	 * Verify the request nonce.
	 *
	 * @return bool
	 */
	private function verify_nonce(): bool {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * Load variation rows for the requested parent product.
	 *
	 * @param int $parent_id Parent product ID.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private function load_variation_rows( int $parent_id ) {
		$repository = $this->get_variation_repository();

		if ( ! $repository ) {
			return new WP_Error( 'pat_missing_variation_repository', __( 'Variation repository is not available.', 'product-admin-tool' ) );
		}

		if ( method_exists( $repository, 'find_by_parent_id' ) ) {
			$rows = $repository->find_by_parent_id( $parent_id );
		} elseif ( method_exists( $repository, 'find_by_parent_ids' ) ) {
			$rows = $repository->find_by_parent_ids( array( $parent_id ) );
		} else {
			return new WP_Error( 'pat_unsupported_variation_repository', __( 'Variation repository does not expose a supported fetch method.', 'product-admin-tool' ) );
		}

		if ( ! is_array( $rows ) ) {
			return new WP_Error( 'pat_invalid_variation_rows', __( 'Variation rows could not be loaded.', 'product-admin-tool' ) );
		}

		return $rows;
	}

	/**
	 * Normalize raw variation rows for the editor grid.
	 *
	 * @param array $rows Raw rows.
	 * @param int   $parent_id Parent product ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_rows( array $rows, int $parent_id ): array {
		$normalized = array();

		foreach ( $rows as $row ) {
			$normalized_row = $this->normalize_row( $row, $parent_id );

			if ( null !== $normalized_row ) {
				$normalized[] = $normalized_row;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize one variation row.
	 *
	 * @param mixed $row Raw row.
	 * @param int   $parent_id Parent product ID.
	 * @return array<string, mixed>|null
	 */
	private function normalize_row( $row, int $parent_id ): ?array {
		if ( ! is_array( $row ) ) {
			return null;
		}

		$row_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;

		if ( $row_id <= 0 ) {
			return null;
		}

		$title = isset( $row['title'] ) ? (string) $row['title'] : '';
		$summary = isset( $row['summary'] ) ? (string) $row['summary'] : '';

		if ( '' === $title && '' !== $summary ) {
			$title = $summary;
		}

		$sku    = isset( $row['sku'] ) ? (string) $row['sku'] : '';
		$price  = isset( $row['price'] ) ? (string) $row['price'] : '';
		$regular_price = isset( $row['regular_price'] ) ? (string) $row['regular_price'] : $price;
		$sale_price = isset( $row['sale_price'] ) ? (string) $row['sale_price'] : '';
		$stock  = isset( $row['stock_quantity'] ) ? (string) $row['stock_quantity'] : ( isset( $row['stock'] ) ? (string) $row['stock'] : '' );
		$status = isset( $row['status'] ) ? (string) $row['status'] : '';

		$normalized = array(
			'id'               => $row_id,
			'parent_id'        => $parent_id,
			'row_type'         => 'variation',
			'type'             => 'variation',
			'title'            => $title,
			'summary'          => $summary,
			'attribute_summary' => isset( $row['attribute_summary'] ) ? (string) $row['attribute_summary'] : $summary,
			'sku'              => $sku,
			'price'            => $price,
			'regular_price'    => $regular_price,
			'sale_price'       => $sale_price,
			'package_type'     => isset( $row['package_type'] ) ? (string) $row['package_type'] : '',
			'stock'            => $stock,
			'stock_quantity'   => $stock,
			'status'           => $status,
			'menu_order'       => isset( $row['menu_order'] ) ? (int) $row['menu_order'] : 0,
			'child_rows'       => array(),
			'children_lazy'    => false,
			'has_children'     => false,
		);

		if ( isset( $row['permalink'] ) ) {
			$normalized['permalink'] = (string) $row['permalink'];
		}

		return $normalized;
	}

	/**
	 * Resolve the variation repository.
	 *
	 * @return PAT_Variation_Repository|null
	 */
	private function get_variation_repository() {
		if ( is_object( $this->variation_repository ) ) {
			return $this->variation_repository;
		}

		if ( class_exists( 'PAT_Variation_Repository' ) ) {
			$this->variation_repository = new PAT_Variation_Repository();

			return $this->variation_repository;
		}

		return null;
	}

	/**
	 * Render row markup for the current variation payload.
	 *
	 * @param int   $parent_id Parent product ID.
	 * @param array $rows      Normalized variation rows.
	 * @return string
	 */
	private function render_rows_markup( int $parent_id, array $rows ): string {
		if ( ! class_exists( 'PAT_Variation_Row_Renderer' ) ) {
			return '';
		}

		$renderer = new PAT_Variation_Row_Renderer();

		if ( ! method_exists( $renderer, 'get_markup' ) ) {
			return '';
		}

		return $renderer->get_markup(
			array(
				'parent_id' => $parent_id,
			),
			$rows
		);
	}

	/**
	 * Send a structured JSON response.
	 *
	 * @param bool   $success   Whether the request succeeded.
	 * @param int    $parent_id Parent product ID.
	 * @param array  $rows      Normalized variation rows.
	 * @param string $message   Optional message.
	 * @param int    $status    HTTP status code.
	 * @param string $html      Optional rendered rows markup.
	 * @return void
	 */
	private function send_response( bool $success, int $parent_id, array $rows, string $message = '', int $status = 200, string $html = '' ): void {
		wp_send_json(
			array(
				'success'   => $success,
				'parent_id' => $parent_id,
				'rows'      => array_values( $rows ),
				'message'   => $message,
				'html'      => $html,
			),
			$status
		);
	}
}
