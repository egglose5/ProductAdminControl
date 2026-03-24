<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Save_Controller {
	const AJAX_ACTION = 'pat_save_rows';
	const NONCE_ACTION = 'pat-save-rows';
	const NONCE_FIELD  = 'nonce';

	/**
	 * Register the authenticated AJAX hook.
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax_save_rows' ) );
	}

	/**
	 * Handle the batch save request.
	 */
	public function handle_ajax_save_rows(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array(
					'success' => false,
					'results' => array(),
					'message' => __( 'You do not have permission to save products.', 'product-admin-tool' ),
				),
				403
			);
		}

		check_ajax_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$rows = $this->get_rows_from_request();

		if ( is_wp_error( $rows ) ) {
			wp_send_json_error(
				array(
					'success' => false,
					'results' => array(),
					'message' => $rows->get_error_message(),
				),
				400
			);
		}

		$results = array();
		$overall_success = true;

		foreach ( $rows as $index => $row ) {
			$result = $this->save_row( $row, $index );
			$results[] = $result;

			if ( isset( $result['status'] ) && 'error' === $result['status'] ) {
				$overall_success = false;
			}
		}

		wp_send_json(
			array(
				'success' => $overall_success,
				'results' => $results,
			),
			$overall_success ? 200 : 207
		);
	}

	/**
	 * Save a single row through the appropriate service if available.
	 *
	 * @param mixed $row   Raw row payload.
	 * @param int   $index Row index.
	 * @return array<string, mixed>
	 */
	private function save_row( $row, int $index ): array {
		$normalized = $this->normalize_row( $row );

		if ( is_wp_error( $normalized ) ) {
			return $this->build_result(
				$index,
				0,
				'error',
				$normalized->get_error_message(),
				array(
					'errors' => array(
						'row' => $normalized->get_error_message(),
					),
				)
			);
		}

		$row_id   = $normalized['id'];
		$row_type = $normalized['row_type'];
		$changes  = $normalized['changes'];

		$service_result = $this->dispatch_to_service( $normalized );

		if ( is_wp_error( $service_result ) ) {
			return $this->build_result(
				$index,
				$row_id,
				'error',
				$service_result->get_error_message(),
				array(
					'errors' => array(
						'row' => $service_result->get_error_message(),
					),
				)
			);
		}

		return $this->build_result(
			$index,
			$row_id,
			isset( $service_result['status'] ) ? (string) $service_result['status'] : 'saved',
			isset( $service_result['message'] ) ? (string) $service_result['message'] : __( 'Saved successfully.', 'product-admin-tool' ),
			array(
				'row_type' => $row_type,
				'changes'  => $changes,
				'data'     => isset( $service_result['data'] ) && is_array( $service_result['data'] ) ? $service_result['data'] : array(),
			)
		);
	}

	/**
	 * Dispatch a row to the appropriate save service.
	 *
	 * @param array<string, mixed> $row Normalized row.
	 * @return array<string, mixed>|WP_Error
	 */
	private function dispatch_to_service( array $row ) {
		$row_type = $row['row_type'];

		if ( 'product' === $row_type ) {
			return $this->save_with_service( 'PAT_Product_Save_Service', $row );
		}

		if ( 'variation' === $row_type ) {
			return $this->save_with_service( 'PAT_Variation_Save_Service', $row );
		}

		return new WP_Error( 'pat_invalid_row_type', __( 'Unsupported row type.', 'product-admin-tool' ) );
	}

	/**
	 * Call a save service if it exists.
	 *
	 * @param string               $class_name Service class name.
	 * @param array<string, mixed> $row        Normalized row.
	 * @return array<string, mixed>|WP_Error
	 */
	private function save_with_service( string $class_name, array $row ) {
		if ( ! class_exists( $class_name ) ) {
			return new WP_Error( 'pat_missing_service', __( 'Save service is not available yet.', 'product-admin-tool' ) );
		}

		$service = new $class_name();

		if ( method_exists( $service, 'save_row' ) ) {
			return $service->save_row( $row );
		}

		if ( method_exists( $service, 'save' ) ) {
			return $service->save( $row );
		}

		if ( method_exists( $service, 'save_rows' ) ) {
			return $service->save_rows( array( $row ) );
		}

		return new WP_Error( 'pat_unsupported_service', __( 'Save service does not expose a supported method.', 'product-admin-tool' ) );
	}

	/**
	 * Normalize and validate a single row payload.
	 *
	 * @param mixed $row Raw row payload.
	 * @return array<string, mixed>|WP_Error
	 */
	private function normalize_row( $row ) {
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'pat_invalid_row', __( 'Each row must be an array.', 'product-admin-tool' ) );
		}

		$row_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		$row_type = isset( $row['row_type'] ) ? sanitize_key( (string) $row['row_type'] ) : '';
		$changes = isset( $row['changes'] ) ? $row['changes'] : array();

		if ( $row_id <= 0 ) {
			return new WP_Error( 'pat_missing_row_id', __( 'Each row must include a valid id.', 'product-admin-tool' ) );
		}

		if ( ! in_array( $row_type, array( 'product', 'variation' ), true ) ) {
			return new WP_Error( 'pat_missing_row_type', __( 'Each row must declare a valid row_type.', 'product-admin-tool' ) );
		}

		if ( ! is_array( $changes ) ) {
			return new WP_Error( 'pat_invalid_changes', __( 'Each row must include a changes array.', 'product-admin-tool' ) );
		}

		return array(
			'id'       => $row_id,
			'row_type' => $row_type,
			'changes'  => $changes,
		);
	}

	/**
	 * Read the rows payload from the request.
	 *
	 * @return array|WP_Error
	 */
	private function get_rows_from_request() {
		if ( ! isset( $_POST['rows'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return new WP_Error( 'pat_missing_rows', __( 'Missing rows payload.', 'product-admin-tool' ) );
		}

		$raw_rows = wp_unslash( $_POST['rows'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( is_array( $raw_rows ) ) {
			return array_values( $raw_rows );
		}

		if ( is_string( $raw_rows ) && '' !== trim( $raw_rows ) ) {
			$decoded = json_decode( $raw_rows, true );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return new WP_Error( 'pat_invalid_rows_json', __( 'Rows payload is not valid JSON.', 'product-admin-tool' ) );
			}

			if ( ! is_array( $decoded ) ) {
				return new WP_Error( 'pat_invalid_rows_payload', __( 'Rows payload must decode to an array.', 'product-admin-tool' ) );
			}

			return array_values( $decoded );
		}

		return new WP_Error( 'pat_invalid_rows_payload', __( 'Rows payload must be an array or JSON string.', 'product-admin-tool' ) );
	}

	/**
	 * Build a normalized result payload.
	 *
	 * @param int    $index       Row index.
	 * @param int    $row_id      Row ID.
	 * @param string $status      Result status.
	 * @param string $message     Result message.
	 * @param array  $extra       Additional payload data.
	 * @return array<string, mixed>
	 */
	private function build_result( int $index, int $row_id, string $status, string $message, array $extra = array() ): array {
		return array_merge(
			array(
				'index'   => $index,
				'id'      => $row_id,
				'status'  => $status,
				'message' => $message,
			),
			$extra
		);
	}
}
