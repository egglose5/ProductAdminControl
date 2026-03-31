<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Save_History_Store {
	const SCHEMA_VERSION = '1';
	const SCHEMA_OPTION  = 'pat_save_history_schema_version';
	const UNDO_STACK_META_KEY = '_pat_save_undo_stack';

	/**
	 * Ensure the history table exists and matches the latest schema.
	 */
	public static function maybe_upgrade(): void {
		$current = (string) get_option( self::SCHEMA_OPTION, '' );

		if ( self::SCHEMA_VERSION === $current ) {
			return;
		}

		self::install();
	}

	/**
	 * Install or update the save history table.
	 */
	public static function install(): void {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			batch_id varchar(64) NOT NULL,
			action_type varchar(20) NOT NULL,
			row_type varchar(20) NOT NULL,
			entity_id bigint(20) unsigned NOT NULL DEFAULT 0,
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			field_key varchar(64) NOT NULL DEFAULT '',
			old_value longtext NULL,
			new_value longtext NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			request_context longtext NULL,
			PRIMARY KEY  (id),
			KEY batch_id (batch_id),
			KEY user_id (user_id),
			KEY action_type (action_type),
			KEY entity_lookup (row_type, entity_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
	}

	/**
	 * Build table name.
	 */
	public static function table_name(): string {
		global $wpdb;

		return isset( $wpdb ) ? $wpdb->prefix . 'pat_save_history' : 'wp_pat_save_history';
	}

	/**
	 * Generate a sortable batch identifier.
	 */
	public static function generate_batch_id( string $prefix = 'save' ): string {
		$prefix = sanitize_key( $prefix );
		$prefix = '' !== $prefix ? $prefix : 'save';

		return $prefix . '-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 8, false, false );
	}

	/**
	 * Persist field-level history entries.
	 *
	 * @param array<int, array<string, mixed>> $changes Field entries with field_key, old_value, new_value and optional parent_id.
	 * @param array<string, mixed>              $context Extra context payload.
	 */
	public function log_field_changes( string $batch_id, string $action_type, string $row_type, int $entity_id, int $user_id, array $changes, array $context = array() ): int {
		global $wpdb;

		if ( empty( $changes ) || ! isset( $wpdb ) ) {
			return 0;
		}

		$table_name = self::table_name();
		$written    = 0;

		foreach ( $changes as $change ) {
			$field_key = isset( $change['field_key'] ) ? sanitize_key( (string) $change['field_key'] ) : '';
			$parent_id = isset( $change['parent_id'] ) ? absint( $change['parent_id'] ) : 0;

			if ( '' === $field_key ) {
				continue;
			}

			$result = $wpdb->insert(
				$table_name,
				array(
					'batch_id'        => sanitize_text_field( $batch_id ),
					'action_type'     => sanitize_key( $action_type ),
					'row_type'        => sanitize_key( $row_type ),
					'entity_id'       => absint( $entity_id ),
					'parent_id'       => $parent_id,
					'field_key'       => $field_key,
					'old_value'       => $this->serialize_value( $change['old_value'] ?? '' ),
					'new_value'       => $this->serialize_value( $change['new_value'] ?? '' ),
					'user_id'         => absint( $user_id ),
					'created_at'      => current_time( 'mysql', true ),
					'request_context' => ! empty( $context ) ? wp_json_encode( $context ) : null,
				),
				array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
			);

			if ( false !== $result ) {
				$written++;
			}
		}

		return $written;
	}

	/**
	 * Persist a row-level error log.
	 *
	 * @param array<string, mixed> $context Extra context payload.
	 */
	public function log_error( string $batch_id, string $row_type, int $entity_id, int $user_id, string $message, array $context = array() ): void {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return;
		}

		$payload_context             = $context;
		$payload_context['message']  = $message;
		$payload_context['severity'] = 'error';

		$wpdb->insert(
			self::table_name(),
			array(
				'batch_id'        => sanitize_text_field( $batch_id ),
				'action_type'     => 'save_error',
				'row_type'        => sanitize_key( $row_type ),
				'entity_id'       => absint( $entity_id ),
				'parent_id'       => isset( $context['parent_id'] ) ? absint( $context['parent_id'] ) : 0,
				'field_key'       => '',
				'old_value'       => '',
				'new_value'       => '',
				'user_id'         => absint( $user_id ),
				'created_at'      => current_time( 'mysql', true ),
				'request_context' => wp_json_encode( $payload_context ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Load all field history records for a batch and action type.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_batch_field_entries( string $batch_id, string $action_type = 'save' ): array {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return array();
		}

		$sql = $wpdb->prepare(
			"SELECT id, batch_id, action_type, row_type, entity_id, parent_id, field_key, old_value, new_value, user_id, created_at, request_context
			 FROM " . self::table_name() . '
			 WHERE batch_id = %s AND action_type = %s AND field_key <> %s
			 ORDER BY id ASC',
			$batch_id,
			$action_type,
			''
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			function ( array $row ): array {
				$row['entity_id'] = absint( $row['entity_id'] ?? 0 );
				$row['parent_id'] = absint( $row['parent_id'] ?? 0 );
				$row['user_id']   = absint( $row['user_id'] ?? 0 );
				$row['old_value'] = $this->deserialize_value( $row['old_value'] ?? '' );
				$row['new_value'] = $this->deserialize_value( $row['new_value'] ?? '' );
				$row['request_context'] = $this->deserialize_context( $row['request_context'] ?? '' );

				return $row;
			},
			$rows
		);
	}

	/**
	 * Push a save batch onto the per-user undo stack.
	 */
	public function push_undo_batch( int $user_id, string $batch_id ): void {
		if ( $user_id <= 0 || '' === $batch_id ) {
			return;
		}

		$stack = $this->get_undo_stack( $user_id );
		$stack = array_values( array_filter( $stack, 'is_string' ) );

		if ( ! in_array( $batch_id, $stack, true ) ) {
			$stack[] = $batch_id;
		}

		update_user_meta( $user_id, self::UNDO_STACK_META_KEY, $stack );
	}

	/**
	 * Return the current top-of-stack batch id.
	 */
	public function peek_undo_batch( int $user_id ): string {
		$stack = $this->get_undo_stack( $user_id );

		if ( empty( $stack ) ) {
			return '';
		}

		$last = end( $stack );

		return is_string( $last ) ? $last : '';
	}

	/**
	 * Pop and return the top batch id from the user undo stack.
	 */
	public function pop_undo_batch( int $user_id ): string {
		$stack = $this->get_undo_stack( $user_id );

		if ( empty( $stack ) ) {
			return '';
		}

		$last = array_pop( $stack );
		update_user_meta( $user_id, self::UNDO_STACK_META_KEY, $stack );

		return is_string( $last ) ? $last : '';
	}

	/**
	 * @return array<int, string>
	 */
	private function get_undo_stack( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$stack = get_user_meta( $user_id, self::UNDO_STACK_META_KEY, true );

		if ( ! is_array( $stack ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$stack,
				function ( $value ): bool {
					return is_string( $value ) && '' !== trim( $value );
				}
			)
		);
	}

	/**
	 * Retrieve recent save/undo batches for a user, grouped by batch.
	 *
	 * Returns up to $limit rows, most-recent first, with aggregate counts.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $limit   Maximum number of distinct batches to return.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_batches( int $user_id, int $limit = 10 ): array {
		global $wpdb;

		if ( $user_id <= 0 || ! isset( $wpdb ) ) {
			return array();
		}

		$limit = max( 1, min( 50, $limit ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- table_name is derived from $wpdb->prefix, not user input.
		$sql = $wpdb->prepare(
			'SELECT batch_id,
			        action_type,
			        MIN(created_at) AS batch_time,
			        COUNT(DISTINCT entity_id) AS entity_count,
			        COUNT(*) AS field_count
			 FROM ' . self::table_name() . '
			 WHERE user_id = %d
			   AND action_type IN (\'save\', \'undo\')
			   AND field_key <> \'\'
			 GROUP BY batch_id, action_type
			 ORDER BY batch_time DESC
			 LIMIT %d',
			$user_id,
			$limit
		);
		// phpcs:enable

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( array $row ): array {
				$row['entity_count'] = (int) ( $row['entity_count'] ?? 0 );
				$row['field_count']  = (int) ( $row['field_count'] ?? 0 );
				$row['batch_time']   = isset( $row['batch_time'] ) ? (string) $row['batch_time'] : '';

				return $row;
			},
			$rows
		);
	}

	/**
	 * Normalize values for persistence.
	 *
	 * @param mixed $value Raw value.
	 */
	private function serialize_value( $value ): string {
		if ( is_scalar( $value ) || null === $value ) {
			return wp_json_encode( $value );
		}

		return wp_json_encode( $value );
	}

	/**
	 * Decode value from storage.
	 *
	 * @param mixed $value Stored value.
	 * @return mixed
	 */
	private function deserialize_value( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		$decoded = json_decode( $value, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return $value;
		}

		return $decoded;
	}

	/**
	 * Decode stored request context payload.
	 *
	 * @param mixed $value Stored context value.
	 * @return array<string, mixed>
	 */
	private function deserialize_context( $value ): array {
		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}

		$decoded = json_decode( $value, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return array();
		}

		return $decoded;
	}
}
