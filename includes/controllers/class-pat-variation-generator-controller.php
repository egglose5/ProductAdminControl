<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX controller for previewing generated variation rows.
 */
class PAT_Variation_Generator_Controller {
	const AJAX_ACTION = 'pat_preview_variations';
	const NONCE_ACTION = 'pat-preview-variations';
	const NONCE_FIELD  = 'nonce';

	/**
	 * @var PAT_Variation_Generator_Service|null
	 */
	private $generator_service;

	/**
	 * @var PAT_Variation_Repository|null
	 */
	private $variation_repository;

	/**
	 * @var PAT_Variation_Row_Renderer|null
	 */
	private $row_renderer;

	/**
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * @param PAT_Variation_Generator_Service|null $generator_service Optional preview service.
	 * @param PAT_Variation_Repository|null        $variation_repository Optional variation repository.
	 * @param PAT_Variation_Row_Renderer|null      $row_renderer Optional row renderer.
	 */
	public function __construct( $generator_service = null, $variation_repository = null, $row_renderer = null ) {
		$this->generator_service = is_object( $generator_service ) ? $generator_service : null;
		$this->variation_repository = is_object( $variation_repository ) ? $variation_repository : null;
		$this->row_renderer = is_object( $row_renderer ) ? $row_renderer : null;
	}

	/**
	 * Register preview endpoint.
	 */
	public function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax_preview' ) );
	}

	/**
	 * Handle preview requests.
	 */
	public function handle_ajax_preview(): void {
		$parent_id = isset( $_POST['parent_id'] ) ? absint( wp_unslash( $_POST['parent_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $parent_id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'A valid parent product ID is required.', 'product-admin-tool' ),
					'parent_id' => 0,
					'rows' => array(),
					'html' => '',
				),
				400
			);
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to generate variation previews.', 'product-admin-tool' ),
					'parent_id' => $parent_id,
					'rows' => array(),
					'html' => '',
				),
				403
			);
		}

		if ( ! $this->verify_nonce() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security check failed.', 'product-admin-tool' ),
					'parent_id' => $parent_id,
					'rows' => array(),
					'html' => '',
				),
				403
			);
		}

		$service = $this->get_generator_service();

		if ( ! $service ) {
			wp_send_json_error(
				array(
					'message' => __( 'Variation generator service is not available.', 'product-admin-tool' ),
					'parent_id' => $parent_id,
					'rows' => array(),
					'html' => '',
				),
				503
			);
		}

		$preview = $service->preview_missing_combinations( $parent_id );

		if ( empty( $preview['success'] ) ) {
			wp_send_json_error(
				array(
					'message' => isset( $preview['message'] ) ? (string) $preview['message'] : __( 'Variation preview generation failed.', 'product-admin-tool' ),
					'parent_id' => $parent_id,
					'rows' => array(),
					'html' => '',
					'summary' => isset( $preview['summary'] ) && is_array( $preview['summary'] ) ? $preview['summary'] : array(),
					'errors' => isset( $preview['errors'] ) && is_array( $preview['errors'] ) ? $preview['errors'] : array(),
				),
				400
			);
		}

		$generated_rows = isset( $preview['generated_rows'] ) && is_array( $preview['generated_rows'] ) ? $preview['generated_rows'] : array();
		$existing_rows  = $this->load_existing_rows( $parent_id );
		$render_rows    = array_merge( $existing_rows, $generated_rows );
		$html           = $this->render_rows_markup( $parent_id, $render_rows );

		wp_send_json_success(
			array(
				'parent_id' => $parent_id,
				'message' => isset( $preview['message'] ) ? (string) $preview['message'] : '',
				'summary' => isset( $preview['summary'] ) && is_array( $preview['summary'] ) ? $preview['summary'] : array(),
				'generated_rows' => $generated_rows,
				'rows' => $render_rows,
				'html' => $html,
			)
		);
	}

	/**
	 * @param int $parent_id Parent product ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function load_existing_rows( int $parent_id ): array {
		$repository = $this->get_variation_repository();

		if ( ! $repository ) {
			return array();
		}

		$rows = method_exists( $repository, 'find_by_parent_id' ) ? $repository->find_by_parent_id( $parent_id ) : array();

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param int   $parent_id Parent product ID.
	 * @param array $rows Variation rows.
	 * @return string
	 */
	private function render_rows_markup( int $parent_id, array $rows ): string {
		$renderer = $this->get_row_renderer();

		if ( ! $renderer || ! method_exists( $renderer, 'get_markup' ) ) {
			return '';
		}

		return (string) $renderer->get_markup(
			array(
				'parent_id' => $parent_id,
				'parent_dom_id' => 'pat-row-' . $parent_id,
			),
			$rows,
			array(
				'empty_message' => __( 'No variation rows to display.', 'product-admin-tool' ),
			)
		);
	}

	/**
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
	 * @return PAT_Variation_Generator_Service|null
	 */
	private function get_generator_service() {
		if ( $this->generator_service ) {
			return $this->generator_service;
		}

		if ( class_exists( 'PAT_Variation_Generator_Service' ) ) {
			$this->generator_service = new PAT_Variation_Generator_Service();
		}

		return $this->generator_service;
	}

	/**
	 * @return PAT_Variation_Repository|null
	 */
	private function get_variation_repository() {
		if ( $this->variation_repository ) {
			return $this->variation_repository;
		}

		if ( class_exists( 'PAT_Variation_Repository' ) ) {
			$this->variation_repository = new PAT_Variation_Repository();
		}

		return $this->variation_repository;
	}

	/**
	 * @return PAT_Variation_Row_Renderer|null
	 */
	private function get_row_renderer() {
		if ( $this->row_renderer ) {
			return $this->row_renderer;
		}

		if ( class_exists( 'PAT_Variation_Row_Renderer' ) ) {
			$this->row_renderer = new PAT_Variation_Row_Renderer();
		}

		return $this->row_renderer;
	}
}
