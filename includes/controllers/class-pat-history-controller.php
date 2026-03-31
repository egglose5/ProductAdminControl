<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_History_Controller {
	const AJAX_ACTION  = 'pat_get_save_history';
	const NONCE_ACTION = 'pat-get-save-history';
	const NONCE_FIELD  = 'nonce';

	/**
	 * Register the authenticated AJAX hook.
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax_get_history' ) );
	}

	/**
	 * Return recent save/undo batches for the current user.
	 */
	public function handle_ajax_get_history(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array(
					'success' => false,
					'batches' => array(),
					'message' => __( 'You do not have permission to view save history.', 'product-admin-tool' ),
				),
				403
			);
		}

		check_ajax_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		if ( ! class_exists( 'PAT_Save_History_Store' ) ) {
			wp_send_json_error(
				array(
					'success' => false,
					'batches' => array(),
					'message' => __( 'Save history storage is not available.', 'product-admin-tool' ),
				),
				500
			);
		}

		$store   = new PAT_Save_History_Store();
		$user_id  = get_current_user_id();
		$limit    = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 10;
		$limit    = max( 1, min( 20, $limit ) );
		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['batch_id'] ) ) : '';

		if ( '' !== $batch_id ) {
			wp_send_json(
				array(
					'success' => true,
					'batch_id' => $batch_id,
					'entries' => $store->get_batch_entries( $batch_id ),
				),
				200
			);
		}

		$undo_batch_id = $store->peek_undo_batch( $user_id );
		$raw_batches   = $store->get_recent_batches( 0, $limit );

		$batches = array_map(
			static function ( array $batch ) use ( $undo_batch_id ): array {
				return array(
					'batch_id'     => (string) ( $batch['batch_id'] ?? '' ),
					'action_type'  => (string) ( $batch['action_type'] ?? 'save' ),
					'batch_time'   => (string) ( $batch['batch_time'] ?? '' ),
					'actor_name'   => (string) ( $batch['actor_name'] ?? '' ),
					'actor_user_id'=> (int) ( $batch['actor_user_id'] ?? 0 ),
					'entity_count' => (int) ( $batch['entity_count'] ?? 0 ),
					'field_count'  => (int) ( $batch['field_count'] ?? 0 ),
					'entry_count'  => (int) ( $batch['entry_count'] ?? 0 ),
					'undoable'     => '' !== $undo_batch_id
					                  && (string) ( $batch['batch_id'] ?? '' ) === $undo_batch_id
					                  && 'save' === (string) ( $batch['action_type'] ?? '' ),
				);
			},
			$raw_batches
		);

		wp_send_json(
			array(
				'success'       => true,
				'batches'       => $batches,
				'undo_batch_id' => $undo_batch_id,
			),
			200
		);
	}
}
