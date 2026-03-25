<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Row_Validation {
	const ROW_TYPE_PRODUCT   = 'product';
	const ROW_TYPE_VARIATION = 'variation';

	/**
	 * Return the supported editable fields for a row type.
	 *
	 * @param string $row_type Row type.
	 * @return array<int, string>
	 */
	public static function get_allowed_fields( string $row_type ): array {
		$row_type = self::sanitize_row_type( $row_type );

		if ( self::ROW_TYPE_VARIATION === $row_type ) {
			return array( 'sku', 'status', 'regular_price', 'sale_price', 'stock_quantity', 'menu_order' );
		}

		return array( 'title', 'sku', 'status', 'regular_price', 'sale_price', 'stock_quantity', 'weight', 'length', 'width', 'height', 'shipping_class_id', 'menu_order' );
	}

	/**
	 * Sanitize a row type identifier.
	 *
	 * @param mixed $row_type Raw row type.
	 * @return string
	 */
	public static function sanitize_row_type( $row_type ): string {
		$row_type = sanitize_key( (string) $row_type );

		if ( self::ROW_TYPE_VARIATION === $row_type ) {
			return self::ROW_TYPE_VARIATION;
		}

		return self::ROW_TYPE_PRODUCT;
	}

	/**
	 * Validate and sanitize a row payload.
	 *
	 * @param array $row Raw row payload.
	 * @return array<string, mixed>
	 */
	public static function validate_row( array $row ): array {
		$row_id   = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		$row_type = self::sanitize_row_type( $row['row_type'] ?? self::ROW_TYPE_PRODUCT );
		$changes  = isset( $row['changes'] ) && is_array( $row['changes'] ) ? $row['changes'] : array();

		$errors = array();
		$data   = array();

		if ( $row_id <= 0 ) {
			$errors['id'] = __( 'Row ID is required.', 'product-admin-tool' );
		}

		$allowed_fields = self::get_allowed_fields( $row_type );
		$sanitized      = array();

		foreach ( $allowed_fields as $field ) {
			if ( ! array_key_exists( $field, $changes ) ) {
				continue;
			}

			$raw_value = $changes[ $field ];
			$result    = self::validate_field( $field, $raw_value );

			if ( ! empty( $result['error'] ) ) {
				$errors[ $field ] = $result['error'];
				continue;
			}

			$sanitized[ $field ] = $result['value'];
		}

		$unknown_fields = array_diff( array_keys( $changes ), $allowed_fields );

		if ( ! empty( $unknown_fields ) ) {
			$errors['changes'] = __( 'One or more fields are not editable.', 'product-admin-tool' );
		}

		return array(
			'valid'     => empty( $errors ),
			'id'        => $row_id,
			'row_type'  => $row_type,
			'changes'   => $sanitized,
			'errors'    => $errors,
			'data'      => $data,
		);
	}

	/**
	 * Validate and sanitize one field value.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Raw value.
	 * @return array<string, mixed>
	 */
	public static function validate_field( string $field, $value ): array {
		switch ( $field ) {
			case 'title':
				$value = sanitize_text_field( (string) $value );
				$value = trim( $value );

				if ( '' === $value ) {
					return array(
						'error' => __( 'Title cannot be empty.', 'product-admin-tool' ),
					);
				}

				return array( 'value' => $value );

			case 'sku':
				$value = sanitize_text_field( (string) $value );
				return array( 'value' => trim( $value ) );

			case 'status':
				$value = self::sanitize_status( $value );

				if ( '' === $value ) {
					return array(
						'error' => __( 'Status is not valid.', 'product-admin-tool' ),
					);
				}

				return array( 'value' => $value );

			case 'regular_price':
			case 'sale_price':
				$value = self::sanitize_decimal( $value );

				if ( null === $value ) {
					return array(
						'error' => __( 'Price must be a valid decimal value.', 'product-admin-tool' ),
					);
				}

				return array( 'value' => $value );

			case 'stock_quantity':
				$value = self::sanitize_integer_or_empty( $value );

				if ( null === $value ) {
					return array(
						'error' => __( 'Stock quantity must be an integer.', 'product-admin-tool' ),
					);
				}

				return array( 'value' => $value );

			case 'weight':
			case 'length':
			case 'width':
			case 'height':
				$value = self::sanitize_decimal( $value );

				if ( null === $value ) {
					return array(
						'error' => __( 'Shipping dimensions and weight must be numeric.', 'product-admin-tool' ),
					);
				}

				return array( 'value' => $value );

			case 'shipping_class_id':
				$value = self::sanitize_integer_or_empty( $value );

				if ( null === $value ) {
					return array(
						'error' => __( 'Shipping class must be a valid ID.', 'product-admin-tool' ),
					);
				}

				return array( 'value' => $value );

			case 'menu_order':
				$value = is_scalar( $value ) ? (int) $value : null;

				if ( null === $value ) {
					return array(
						'error' => __( 'Menu order must be an integer.', 'product-admin-tool' ),
					);
				}

				return array( 'value' => $value );
		}

		return array(
			'error' => __( 'Field is not editable.', 'product-admin-tool' ),
		);
	}

	/**
	 * Sanitize a product status value.
	 *
	 * @param mixed $status Raw status.
	 * @return string
	 */
	public static function sanitize_status( $status ): string {
		$status = sanitize_key( (string) $status );
		$allowed = array( 'publish', 'draft', 'pending', 'private' );

		return in_array( $status, $allowed, true ) ? $status : '';
	}

	/**
	 * Sanitize a decimal-like value.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	public static function sanitize_decimal( $value ): ?string {
		if ( '' === $value || null === $value ) {
			return '';
		}

		if ( function_exists( 'wc_format_decimal' ) ) {
			return wc_format_decimal( $value, function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2 );
		}

		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( '' === $value ) {
			return '';
		}

		if ( ! is_numeric( $value ) ) {
			return null;
		}

		return (string) (float) $value;
	}

	/**
	 * Sanitize an integer-like value or allow empty.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	public static function sanitize_integer_or_empty( $value ): ?string {
		if ( '' === $value || null === $value ) {
			return '';
		}

		if ( is_bool( $value ) ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			return (string) (int) $value;
		}

		return null;
	}
}
