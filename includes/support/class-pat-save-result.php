<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Save_Result {
	const STATUS_SUCCESS = 'saved';
	const STATUS_ERROR   = 'error';

	/**
	 * Build a success result payload.
	 *
	 * @param int    $id       Row ID.
	 * @param string $row_type Row type.
	 * @param string $message  Optional message.
	 * @param array  $data     Optional response data.
	 * @return array<string, mixed>
	 */
	public static function success( int $id, string $row_type, string $message = '', array $data = array() ): array {
		return array(
			'id'       => $id,
			'row_type' => $row_type,
			'status'   => self::STATUS_SUCCESS,
			'message'  => '' !== $message ? $message : __( 'Saved successfully.', 'product-admin-tool' ),
			'data'     => $data,
			'errors'   => array(),
		);
	}

	/**
	 * Build an error result payload.
	 *
	 * @param int    $id       Row ID.
	 * @param string $row_type Row type.
	 * @param string $message  Error message.
	 * @param array  $errors   Field-level errors.
	 * @param array  $data     Optional response data.
	 * @return array<string, mixed>
	 */
	public static function error( int $id, string $row_type, string $message, array $errors = array(), array $data = array() ): array {
		return array(
			'id'       => $id,
			'row_type' => $row_type,
			'status'   => self::STATUS_ERROR,
			'message'  => $message,
			'data'     => $data,
			'errors'   => $errors,
		);
	}

	/**
	 * Build an error result payload from validation data.
	 *
	 * @param array $validation Validation payload.
	 * @param string $message    Optional message.
	 * @return array<string, mixed>
	 */
	public static function from_validation( array $validation, string $message = '' ): array {
		$id       = isset( $validation['id'] ) ? absint( $validation['id'] ) : 0;
		$row_type = isset( $validation['row_type'] ) ? (string) $validation['row_type'] : 'product';
		$errors   = isset( $validation['errors'] ) && is_array( $validation['errors'] ) ? $validation['errors'] : array();

		return self::error(
			$id,
			$row_type,
			'' !== $message ? $message : __( 'Validation failed.', 'product-admin-tool' ),
			$errors,
			array()
		);
	}
}
