<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Save_Controller {
	const AJAX_ACTION = 'pat_save_rows';
	const NONCE_ACTION = 'pat-save-rows';
	const NONCE_FIELD  = 'nonce';

	/**
	 * @var PAT_Save_History_Store|null
	 */
	private $history_store;

	public function __construct() {
		$this->history_store = class_exists( 'PAT_Save_History_Store' ) ? new PAT_Save_History_Store() : null;
	}

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

		$results         = array();
		$overall_success = true;
		$batch_id        = class_exists( 'PAT_Save_History_Store' ) ? PAT_Save_History_Store::generate_batch_id( 'save' ) : '';
		$user_id         = get_current_user_id();
		$successful_rows = array();
		$failure_result  = null;
		$failure_index   = null;

		foreach ( $rows as $index => $row ) {
			$result    = $this->save_row( $row, $index );
			$results[] = $result;

			if ( isset( $result['status'] ) && 'saved' === $result['status'] ) {
				$successful_rows[] = $result;
				continue;
			}

			$overall_success = false;
			$failure_result  = $result;
			$failure_index   = $index;
			break;
		}

		if ( ! $overall_success ) {
			$rollback = $this->rollback_successful_rows( $successful_rows, $batch_id, $user_id );
			$error_message = ! empty( $rollback['success'] )
				? __( 'Save canceled because one row failed. Completed row changes were rolled back.', 'product-admin-tool' )
				: __( 'Save failed and automatic rollback could not fully complete. Review the affected rows before continuing.', 'product-admin-tool' );

			foreach ( $successful_rows as $success_index => $successful_row ) {
				$results[ $success_index ] = $this->build_rolled_back_result( $successful_row, $error_message );
			}

			if ( is_array( $failure_result ) ) {
				$this->log_row_error(
					$batch_id,
					isset( $failure_result['row_type'] ) ? (string) $failure_result['row_type'] : '',
					isset( $failure_result['id'] ) ? absint( $failure_result['id'] ) : 0,
					$user_id,
					isset( $failure_result['message'] ) ? (string) $failure_result['message'] : __( 'Save failed.', 'product-admin-tool' ),
					array(
						'index'         => $failure_index,
						'changes'       => isset( $failure_result['changes'] ) && is_array( $failure_result['changes'] ) ? $failure_result['changes'] : array(),
						'client_row_id' => isset( $failure_result['client_row_id'] ) ? (string) $failure_result['client_row_id'] : '',
						'rolled_back'   => ! empty( $rollback['success'] ),
					)
				);
			}

			if ( empty( $rollback['success'] ) ) {
				foreach ( $rollback['errors'] as $rollback_error ) {
					$this->log_row_error(
						$batch_id,
						isset( $rollback_error['row_type'] ) ? (string) $rollback_error['row_type'] : '',
						isset( $rollback_error['id'] ) ? absint( $rollback_error['id'] ) : 0,
						$user_id,
						isset( $rollback_error['message'] ) ? (string) $rollback_error['message'] : __( 'Automatic rollback failed.', 'product-admin-tool' ),
						array(
							'rollback' => true,
						)
					);
				}
			}

			for ( $index = (int) $failure_index + 1; $index < count( $rows ); $index++ ) {
				$results[] = $this->build_skipped_result(
					$rows[ $index ],
					$index,
					__( 'Batch save was canceled before this row was written.', 'product-admin-tool' )
				);
			}
		} else {
			$has_logged_changes = false;

			foreach ( $successful_rows as $successful_row ) {
				$history_changes = isset( $successful_row['_pat_history_changes'] ) && is_array( $successful_row['_pat_history_changes'] )
					? $successful_row['_pat_history_changes']
					: array();

				if ( empty( $history_changes ) || ! $this->history_store || '' === $batch_id ) {
					continue;
				}

				$written = $this->history_store->log_field_changes(
					$batch_id,
					'save',
					isset( $successful_row['row_type'] ) ? (string) $successful_row['row_type'] : '',
					isset( $successful_row['id'] ) ? absint( $successful_row['id'] ) : 0,
					$user_id,
					$history_changes,
					array(
						'request_action' => self::AJAX_ACTION,
						'created_entity' => ! empty( $successful_row['data']['is_created'] ),
						'is_generated'   => ! empty( $successful_row['_pat_normalized_row']['is_generated'] ),
					)
				);

				if ( $written > 0 ) {
					$has_logged_changes = true;
				}
			}

			if ( $has_logged_changes && $this->history_store && '' !== $batch_id ) {
				$this->history_store->push_undo_batch( $user_id, $batch_id );
			}
		}

		$results = array_map( array( $this, 'strip_internal_result_metadata' ), $results );

		wp_send_json(
			array(
				'success' => $overall_success,
				'results' => $results,
				'batch_id' => $batch_id,
			),
			$overall_success ? 200 : 409
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
		$client_row_id = isset( $normalized['client_row_id'] ) ? (string) $normalized['client_row_id'] : (string) $row_id;
		$before_values = $this->capture_entity_values_before_save( $normalized );

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

		$result_id = isset( $service_result['id'] ) ? $service_result['id'] : $row_id;
		$status = isset( $service_result['status'] ) ? (string) $service_result['status'] : 'saved';
		$history_changes = 'saved' === $status
			? $this->build_successful_field_changes(
				$row_type,
				absint( $result_id ),
				$changes,
				$before_values,
				isset( $service_result['data'] ) && is_array( $service_result['data'] ) ? $service_result['data'] : array(),
				$normalized
			)
			: array();

		return $this->build_result(
			$index,
			$result_id,
			$status,
			isset( $service_result['message'] ) ? (string) $service_result['message'] : __( 'Saved successfully.', 'product-admin-tool' ),
			array(
				'row_type' => $row_type,
				'changes'  => $changes,
				'_pat_history_changes' => $history_changes,
				'_pat_before_values' => $before_values,
				'_pat_normalized_row' => $normalized,
				'client_row_id' => isset( $service_result['client_row_id'] ) ? (string) $service_result['client_row_id'] : $client_row_id,
				'data'     => isset( $service_result['data'] ) && is_array( $service_result['data'] ) ? $service_result['data'] : array(),
				'errors'   => isset( $service_result['errors'] ) && is_array( $service_result['errors'] ) ? $service_result['errors'] : array(),
			)
		);
	}

	/**
	 * Capture current values for fields before save.
	 *
	 * @param array<string, mixed> $row Normalized row.
	 * @return array<string, mixed>
	 */
	private function capture_entity_values_before_save( array $row ): array {
		$fields = array_keys( isset( $row['changes'] ) && is_array( $row['changes'] ) ? $row['changes'] : array() );

		if ( empty( $fields ) ) {
			return array();
		}

		$row_type = isset( $row['row_type'] ) ? sanitize_key( (string) $row['row_type'] ) : '';
		$row_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;

		if ( 'variation' === $row_type && ! empty( $row['is_generated'] ) ) {
			return array();
		}

		return $this->fetch_entity_values( $row_type, $row_id, $fields );
	}

	/**
	 * Fetch current entity values from WooCommerce models.
	 *
	 * @param string   $row_type Row type.
	 * @param int      $row_id Entity id.
	 * @param string[] $fields Fields to fetch.
	 * @return array<string, mixed>
	 */
	private function fetch_entity_values( string $row_type, int $row_id, array $fields ): array {
		if ( $row_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$product = wc_get_product( $row_id );

		if ( ! $product || ! is_object( $product ) ) {
			return array();
		}

		$values = array();

		foreach ( $fields as $field ) {
			switch ( $field ) {
				case 'title':
					$values['title'] = method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '';
					break;

				case 'sku':
					$values['sku'] = method_exists( $product, 'get_sku' ) ? (string) $product->get_sku() : '';
					break;

				case 'status':
					$values['status'] = method_exists( $product, 'get_status' ) ? (string) $product->get_status() : '';
					break;

				case 'regular_price':
					$values['regular_price'] = method_exists( $product, 'get_regular_price' ) ? (string) $product->get_regular_price() : '';
					break;

				case 'sale_price':
					$values['sale_price'] = method_exists( $product, 'get_sale_price' ) ? (string) $product->get_sale_price() : '';
					break;

				case 'stock_quantity':
					$values['stock_quantity'] = method_exists( $product, 'get_stock_quantity' ) ? $product->get_stock_quantity() : '';
					break;

				case 'weight':
					$values['weight'] = method_exists( $product, 'get_weight' ) ? (string) $product->get_weight() : '';
					break;

				case 'length':
					$values['length'] = method_exists( $product, 'get_length' ) ? (string) $product->get_length() : '';
					break;

				case 'width':
					$values['width'] = method_exists( $product, 'get_width' ) ? (string) $product->get_width() : '';
					break;

				case 'height':
					$values['height'] = method_exists( $product, 'get_height' ) ? (string) $product->get_height() : '';
					break;

				case 'shipping_class_id':
					$values['shipping_class_id'] = method_exists( $product, 'get_shipping_class_id' ) ? (int) $product->get_shipping_class_id() : 0;
					break;

				case 'package_type':
					$values['package_type'] = (string) get_post_meta( $row_id, '_pat_package_type', true );
					break;

				case 'menu_order':
					$values['menu_order'] = method_exists( $product, 'get_menu_order' ) ? (int) $product->get_menu_order() : 0;
					break;
			}
		}

		if ( 'variation' === $row_type && method_exists( $product, 'get_parent_id' ) ) {
			$values['parent_id'] = (int) $product->get_parent_id();
		}

		return $values;
	}

	/**
	 * Persist field-level history entries for successful saves.
	 *
	 * @param array<string, mixed> $changes Requested changes.
	 * @param array<string, mixed> $before_values Values before save.
	 * @param array<string, mixed> $service_data Values returned after save.
	 * @param array<string, mixed> $normalized_row Row payload.
	 */
	private function build_successful_field_changes( string $row_type, int $row_id, array $changes, array $before_values, array $service_data, array $normalized_row ): array {
		$fields = array_keys( $changes );

		if ( empty( $fields ) ) {
			return array();
		}

		$after_values = $this->fetch_entity_values( $row_type, $row_id, $fields );

		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field, $after_values ) && array_key_exists( $field, $service_data ) ) {
				$after_values[ $field ] = $service_data[ $field ];
			}
		}

		$history_changes = array();
		$parent_id = 0;

		if ( 'variation' === $row_type ) {
			$parent_id = isset( $service_data['parent_id'] ) ? absint( $service_data['parent_id'] ) : ( isset( $normalized_row['parent_id'] ) ? absint( $normalized_row['parent_id'] ) : 0 );
		}

		foreach ( $fields as $field ) {
			$old_value = $before_values[ $field ] ?? '';
			$new_value = $after_values[ $field ] ?? ( $service_data[ $field ] ?? ( $changes[ $field ] ?? '' ) );

			if ( $this->normalize_history_value( $old_value ) === $this->normalize_history_value( $new_value ) ) {
				continue;
			}

			$history_changes[] = array(
				'field_key' => $field,
				'old_value' => $old_value,
				'new_value' => $new_value,
				'parent_id' => $parent_id,
			);
		}

		if ( empty( $history_changes ) ) {
			return array();
		}

		return $history_changes;
	}

	/**
	 * Log failed row save attempts.
	 *
	 * @param array<string, mixed> $context Context payload.
	 */
	private function log_row_error( string $batch_id, string $row_type, int $row_id, int $user_id, string $message, array $context = array() ): void {
		if ( ! $this->history_store || '' === $batch_id ) {
			return;
		}

		$this->history_store->log_error( $batch_id, $row_type, $row_id, $user_id, $message, $context );
	}

	/**
	 * Convert history values to comparable strings.
	 *
	 * @param mixed $value Raw value.
	 */
	private function normalize_history_value( $value ): string {
		if ( null === $value ) {
			return '';
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		if ( is_scalar( $value ) ) {
			return trim( (string) $value );
		}

		return wp_json_encode( $value );
	}

	/**
	 * Attempt to restore previously saved rows after a later row fails.
	 *
	 * @param array<int, array<string, mixed>> $successful_rows Successful row results.
	 * @return array<string, mixed>
	 */
	private function rollback_successful_rows( array $successful_rows, string $batch_id, int $user_id ): array {
		$errors = array();
		$successful_rows = array_reverse( $successful_rows );

		foreach ( $successful_rows as $row_result ) {
			$normalized   = isset( $row_result['_pat_normalized_row'] ) && is_array( $row_result['_pat_normalized_row'] ) ? $row_result['_pat_normalized_row'] : array();
			$before_values = isset( $row_result['_pat_before_values'] ) && is_array( $row_result['_pat_before_values'] ) ? $row_result['_pat_before_values'] : array();
			$row_type     = isset( $normalized['row_type'] ) ? sanitize_key( (string) $normalized['row_type'] ) : '';
			$row_id       = isset( $row_result['id'] ) ? absint( $row_result['id'] ) : 0;

			if ( 'variation' === $row_type && ! empty( $normalized['is_generated'] ) && ! empty( $row_result['data']['is_created'] ) ) {
				if ( $row_id <= 0 || ! function_exists( 'wp_delete_post' ) || false === wp_delete_post( $row_id, true ) ) {
					$errors[] = array(
						'row_type' => $row_type,
						'id'       => $row_id,
						'message'  => __( 'Generated variation could not be removed during rollback.', 'product-admin-tool' ),
					);
				}

				continue;
			}

			$inverse_changes = array();

			foreach ( isset( $normalized['changes'] ) && is_array( $normalized['changes'] ) ? $normalized['changes'] : array() as $field_key => $unused_value ) {
				$inverse_changes[ $field_key ] = $before_values[ $field_key ] ?? '';
			}

			if ( empty( $inverse_changes ) ) {
				continue;
			}

			$inverse_row = array(
				'id'       => $row_id,
				'row_type' => $row_type,
				'changes'  => $inverse_changes,
			);

			if ( 'variation' === $row_type && ! empty( $normalized['parent_id'] ) ) {
				$inverse_row['parent_id'] = absint( $normalized['parent_id'] );
			}

			$rollback_result = $this->dispatch_to_service( $inverse_row );

			if ( is_wp_error( $rollback_result ) || ! isset( $rollback_result['status'] ) || 'saved' !== $rollback_result['status'] ) {
				$errors[] = array(
					'row_type' => $row_type,
					'id'       => $row_id,
					'message'  => is_wp_error( $rollback_result )
						? $rollback_result->get_error_message()
						: ( isset( $rollback_result['message'] ) ? (string) $rollback_result['message'] : __( 'Rollback failed.', 'product-admin-tool' ) ),
				);
			}
		}

		return array(
			'success' => empty( $errors ),
			'errors'  => $errors,
			'batch_id' => $batch_id,
			'user_id' => $user_id,
		);
	}

	/**
	 * Convert a previously-saved result into a rolled-back error state.
	 */
	private function build_rolled_back_result( array $result, string $message ): array {
		$result['status']  = 'error';
		$result['message'] = $message;
		$result['errors']  = array(
			'row' => $message,
		);

		return $result;
	}

	/**
	 * Build an error result for a row that was never attempted because the batch was canceled.
	 *
	 * @param mixed $row Raw row payload.
	 * @return array<string, mixed>
	 */
	private function build_skipped_result( $row, int $index, string $message ): array {
		$row_id = is_array( $row ) && isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		$row_type = is_array( $row ) && isset( $row['row_type'] ) ? sanitize_key( (string) $row['row_type'] ) : '';
		$client_row_id = is_array( $row ) && isset( $row['client_row_id'] )
			? sanitize_text_field( (string) $row['client_row_id'] )
			: ( $row_id > 0 ? (string) $row_id : '' );

		return $this->build_result(
			$index,
			$row_id,
			'error',
			$message,
			array(
				'row_type'      => $row_type,
				'client_row_id' => $client_row_id,
				'errors'        => array(
					'row' => $message,
				),
			)
		);
	}

	/**
	 * Remove server-only metadata before returning a result to the browser.
	 */
	private function strip_internal_result_metadata( array $result ): array {
		unset(
			$result['_pat_history_changes'],
			$result['_pat_before_values'],
			$result['_pat_normalized_row']
		);

		return $result;
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
		$client_row_id = isset( $row['client_row_id'] ) ? sanitize_text_field( (string) $row['client_row_id'] ) : ( isset( $row['id'] ) ? sanitize_text_field( (string) $row['id'] ) : '' );
		$is_generated = ! empty( $row['is_generated'] );
		$parent_id = isset( $row['parent_id'] ) ? absint( $row['parent_id'] ) : 0;
		$temp_id = isset( $row['temp_id'] ) ? sanitize_text_field( (string) $row['temp_id'] ) : '';
		$attributes = isset( $row['attributes'] ) && is_array( $row['attributes'] ) ? $row['attributes'] : array();

		if ( ! in_array( $row_type, array( 'product', 'variation' ), true ) ) {
			return new WP_Error( 'pat_missing_row_type', __( 'Each row must declare a valid row_type.', 'product-admin-tool' ) );
		}

		if ( $row_id <= 0 && ! ( 'variation' === $row_type && $is_generated ) ) {
			return new WP_Error( 'pat_missing_row_id', __( 'Each row must include a valid id.', 'product-admin-tool' ) );
		}

		if ( ! is_array( $changes ) ) {
			return new WP_Error( 'pat_invalid_changes', __( 'Each row must include a changes array.', 'product-admin-tool' ) );
		}

		if ( 'variation' === $row_type && $is_generated ) {
			if ( $parent_id <= 0 ) {
				return new WP_Error( 'pat_missing_parent_id', __( 'Generated variation rows must include a valid parent_id.', 'product-admin-tool' ) );
			}

			if ( empty( $attributes ) ) {
				return new WP_Error( 'pat_missing_attributes', __( 'Generated variation rows must include attributes.', 'product-admin-tool' ) );
			}
		}

		return array(
			'id'       => $row_id,
			'row_type' => $row_type,
			'changes'  => $changes,
			'client_row_id' => $client_row_id,
			'is_generated' => $is_generated,
			'parent_id' => $parent_id,
			'temp_id' => $temp_id,
			'attributes' => $attributes,
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
	private function build_result( int $index, $row_id, string $status, string $message, array $extra = array() ): array {
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
