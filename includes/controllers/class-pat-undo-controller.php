<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Undo_Controller {
	const AJAX_ACTION = 'pat_undo_last_save';
	const NONCE_ACTION = 'pat-undo-last-save';
	const NONCE_FIELD  = 'nonce';

	/**
	 * Register the authenticated AJAX hook.
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax_undo_last_save' ) );
	}

	/**
	 * Undo the most recent save batch for the current user.
	 */
	public function handle_ajax_undo_last_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array(
					'success' => false,
					'results' => array(),
					'message' => __( 'You do not have permission to undo product saves.', 'product-admin-tool' ),
				),
				403
			);
		}

		check_ajax_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		if ( ! class_exists( 'PAT_Save_History_Store' ) ) {
			wp_send_json_error(
				array(
					'success' => false,
					'results' => array(),
					'message' => __( 'Undo history storage is not available.', 'product-admin-tool' ),
				),
				500
			);
		}

		$store   = new PAT_Save_History_Store();
		$user_id = get_current_user_id();
		$batch_id = $store->peek_undo_batch( $user_id );

		if ( '' === $batch_id ) {
			wp_send_json_success(
				array(
					'success' => false,
					'results' => array(),
					'message' => __( 'No save history is available to undo.', 'product-admin-tool' ),
				)
			);
		}

		$entries = $store->get_batch_field_entries( $batch_id, 'save' );

		if ( empty( $entries ) ) {
			$store->pop_undo_batch( $user_id );
			wp_send_json_success(
				array(
					'success' => false,
					'results' => array(),
					'message' => __( 'The latest undo batch no longer contains restorable changes.', 'product-admin-tool' ),
				)
			);
		}

		$inverse_rows     = $this->build_inverse_rows( $entries );
		$results          = array();
		$overall_success  = true;
		$undo_batch_id    = PAT_Save_History_Store::generate_batch_id( 'undo' );
		$applied_rows     = array();

		foreach ( $inverse_rows as $row_key => $row ) {
			$service_result = $this->dispatch_to_service( $row );
			$status         = isset( $service_result['status'] ) ? (string) $service_result['status'] : 'error';
			$row_id         = isset( $service_result['id'] ) ? absint( $service_result['id'] ) : absint( $row['id'] );

			$results[] = array(
				'id'            => $row_id,
				'client_row_id' => (string) $row_id,
				'row_type'      => $row['row_type'],
				'status'        => $status,
				'message'       => isset( $service_result['message'] ) ? (string) $service_result['message'] : __( 'Undo failed for row.', 'product-admin-tool' ),
				'data'          => isset( $service_result['data'] ) && is_array( $service_result['data'] ) ? $service_result['data'] : array(),
				'errors'        => isset( $service_result['errors'] ) && is_array( $service_result['errors'] ) ? $service_result['errors'] : array(),
				'_pat_undo_row' => $row,
			);

			if ( 'saved' !== $status ) {
				$overall_success = false;
				$rollback = $this->rollback_applied_undo_rows( $applied_rows );
				$error_message = ! empty( $rollback['success'] )
					? __( 'Undo canceled because one row failed. Completed undo changes were rolled back.', 'product-admin-tool' )
					: __( 'Undo failed and automatic rollback could not fully complete. Review the affected rows before continuing.', 'product-admin-tool' );

				foreach ( $applied_rows as $applied_index => $applied_row ) {
					if ( isset( $results[ $applied_index ] ) && is_array( $results[ $applied_index ] ) ) {
						$results[ $applied_index ]['status']  = 'error';
						$results[ $applied_index ]['message'] = $error_message;
						$results[ $applied_index ]['errors']  = array(
							'row' => $error_message,
						);
					}
				}

				$store->log_error(
					$undo_batch_id,
					$row['row_type'],
					$row_id,
					$user_id,
					isset( $service_result['message'] ) ? (string) $service_result['message'] : __( 'Undo row save failed.', 'product-admin-tool' ),
					array(
						'source_batch_id' => $batch_id,
						'row_key'         => $row_key,
						'rolled_back'     => ! empty( $rollback['success'] ),
					)
				);

				if ( empty( $rollback['success'] ) ) {
					foreach ( $rollback['errors'] as $rollback_error ) {
						$store->log_error(
							$undo_batch_id,
							isset( $rollback_error['row_type'] ) ? (string) $rollback_error['row_type'] : '',
							isset( $rollback_error['id'] ) ? absint( $rollback_error['id'] ) : 0,
							$user_id,
							isset( $rollback_error['message'] ) ? (string) $rollback_error['message'] : __( 'Undo rollback failed.', 'product-admin-tool' ),
							array(
								'source_batch_id' => $batch_id,
								'rollback'        => true,
							)
						);
					}
				}

				break;
			}

			$applied_rows[] = array(
				'result_index' => count( $results ) - 1,
				'row'          => $row,
				'row_id'       => $row_id,
			);
		}

		if ( $overall_success ) {
			foreach ( $applied_rows as $applied_row ) {
				$row      = $applied_row['row'];
				$row_id   = isset( $applied_row['row_id'] ) ? absint( $applied_row['row_id'] ) : absint( $row['id'] );
				$changes  = array();

				foreach ( $row['changes'] as $field_key => $field_value ) {
					$forward   = $row['forward_changes'][ $field_key ] ?? array();
					$changes[] = array(
						'field_key' => $field_key,
						'old_value' => $forward['new_value'] ?? '',
						'new_value' => $forward['old_value'] ?? $field_value,
						'parent_id' => isset( $row['parent_id'] ) ? absint( $row['parent_id'] ) : 0,
					);
				}

				$store->log_field_changes(
					$undo_batch_id,
					'undo',
					$row['row_type'],
					$row_id,
					$user_id,
					$changes,
					array(
						'source_batch_id' => $batch_id,
					)
				);
			}

			$store->pop_undo_batch( $user_id );
		}

		$results = array_map( array( $this, 'strip_internal_result_metadata' ), $results );

		wp_send_json(
			array(
				'success'        => $overall_success,
				'results'        => $results,
				'batch_id'       => $undo_batch_id,
				'undone_batch_id'=> $batch_id,
				'message'        => $overall_success
					? __( 'Undo completed successfully.', 'product-admin-tool' )
					: __( 'Undo was canceled because one or more rows failed.', 'product-admin-tool' ),
			),
			$overall_success ? 200 : 409
		);
	}

	/**
	 * Group history entries into per-row inverse save payloads.
	 *
	 * @param array<int, array<string, mixed>> $entries Field history entries.
	 * @return array<string, array<string, mixed>>
	 */
	private function build_inverse_rows( array $entries ): array {
		$rows = array();

		foreach ( $entries as $entry ) {
			$row_type = isset( $entry['row_type'] ) ? sanitize_key( (string) $entry['row_type'] ) : '';
			$entity_id = isset( $entry['entity_id'] ) ? absint( $entry['entity_id'] ) : 0;
			$field_key = isset( $entry['field_key'] ) ? sanitize_key( (string) $entry['field_key'] ) : '';

			if ( '' === $row_type || 0 === $entity_id || '' === $field_key ) {
				continue;
			}

			$row_key = $row_type . ':' . $entity_id;

			if ( ! isset( $rows[ $row_key ] ) ) {
				$rows[ $row_key ] = array(
					'id'              => $entity_id,
					'row_type'        => $row_type,
					'parent_id'       => isset( $entry['parent_id'] ) ? absint( $entry['parent_id'] ) : 0,
					'changes'         => array(),
					'forward_changes' => array(),
				);
			}

			$rows[ $row_key ]['changes'][ $field_key ] = $entry['old_value'];
			$rows[ $row_key ]['forward_changes'][ $field_key ] = array(
				'old_value' => $entry['old_value'],
				'new_value' => $entry['new_value'],
			);
		}

		return $rows;
	}

	/**
	 * Dispatch an inverse row update to the existing save services.
	 *
	 * @param array<string, mixed> $row Inverse row payload.
	 * @return array<string, mixed>
	 */
	private function dispatch_to_service( array $row ): array {
		$row_type = isset( $row['row_type'] ) ? sanitize_key( (string) $row['row_type'] ) : '';

		if ( 'product' === $row_type ) {
			return $this->save_with_service( 'PAT_Product_Save_Service', $row );
		}

		if ( 'variation' === $row_type ) {
			return $this->save_with_service( 'PAT_Variation_Save_Service', $row );
		}

		return array(
			'id'      => isset( $row['id'] ) ? absint( $row['id'] ) : 0,
			'row_type'=> $row_type,
			'status'  => 'error',
			'message' => __( 'Unsupported row type for undo.', 'product-admin-tool' ),
			'errors'  => array( 'row_type' => __( 'Unsupported row type for undo.', 'product-admin-tool' ) ),
		);
	}

	/**
	 * Call an available save method on a service class.
	 *
	 * @param string               $class_name Service class name.
	 * @param array<string, mixed> $row        Payload.
	 * @return array<string, mixed>
	 */
	private function save_with_service( string $class_name, array $row ): array {
		if ( ! class_exists( $class_name ) ) {
			return array(
				'id'      => isset( $row['id'] ) ? absint( $row['id'] ) : 0,
				'row_type'=> isset( $row['row_type'] ) ? sanitize_key( (string) $row['row_type'] ) : '',
				'status'  => 'error',
				'message' => __( 'Save service is not available for undo.', 'product-admin-tool' ),
				'errors'  => array( 'service' => __( 'Save service is not available for undo.', 'product-admin-tool' ) ),
			);
		}

		$service = new $class_name();

		if ( method_exists( $service, 'save_row' ) ) {
			$result = $service->save_row( $row );
			return is_array( $result ) ? $result : array();
		}

		if ( method_exists( $service, 'save' ) ) {
			$result = $service->save( $row );
			return is_array( $result ) ? $result : array();
		}

		if ( method_exists( $service, 'save_rows' ) ) {
			$result = $service->save_rows( array( $row ) );
			if ( is_array( $result ) && isset( $result[0] ) && is_array( $result[0] ) ) {
				return $result[0];
			}
		}

		return array(
			'id'      => isset( $row['id'] ) ? absint( $row['id'] ) : 0,
			'row_type'=> isset( $row['row_type'] ) ? sanitize_key( (string) $row['row_type'] ) : '',
			'status'  => 'error',
			'message' => __( 'Save service does not expose a supported method for undo.', 'product-admin-tool' ),
			'errors'  => array( 'service' => __( 'Save service does not expose a supported method for undo.', 'product-admin-tool' ) ),
		);
	}

	/**
	 * Re-apply forward changes if an undo batch fails mid-stream.
	 *
	 * @param array<int, array<string, mixed>> $applied_rows Successfully undone rows.
	 * @return array<string, mixed>
	 */
	private function rollback_applied_undo_rows( array $applied_rows ): array {
		$errors = array();

		foreach ( array_reverse( $applied_rows ) as $applied_row ) {
			$row      = isset( $applied_row['row'] ) && is_array( $applied_row['row'] ) ? $applied_row['row'] : array();
			$row_type = isset( $row['row_type'] ) ? sanitize_key( (string) $row['row_type'] ) : '';
			$row_id   = isset( $applied_row['row_id'] ) ? absint( $applied_row['row_id'] ) : ( isset( $row['id'] ) ? absint( $row['id'] ) : 0 );
			$changes  = array();

			foreach ( isset( $row['forward_changes'] ) && is_array( $row['forward_changes'] ) ? $row['forward_changes'] : array() as $field_key => $forward_change ) {
				$changes[ $field_key ] = $forward_change['new_value'] ?? '';
			}

			if ( empty( $changes ) ) {
				continue;
			}

			$rollback_result = $this->dispatch_to_service(
				array(
					'id'       => $row_id,
					'row_type' => $row_type,
					'parent_id'=> isset( $row['parent_id'] ) ? absint( $row['parent_id'] ) : 0,
					'changes'  => $changes,
				)
			);

			if ( is_wp_error( $rollback_result ) || ! isset( $rollback_result['status'] ) || 'saved' !== $rollback_result['status'] ) {
				$errors[] = array(
					'row_type' => $row_type,
					'id'       => $row_id,
					'message'  => is_wp_error( $rollback_result )
						? $rollback_result->get_error_message()
						: ( isset( $rollback_result['message'] ) ? (string) $rollback_result['message'] : __( 'Undo rollback failed.', 'product-admin-tool' ) ),
				);
			}
		}

		return array(
			'success' => empty( $errors ),
			'errors'  => $errors,
		);
	}

	/**
	 * Remove controller-only metadata before returning JSON.
	 */
	private function strip_internal_result_metadata( array $result ): array {
		unset( $result['_pat_undo_row'] );

		return $result;
	}
}
