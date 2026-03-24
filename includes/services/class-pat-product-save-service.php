<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Product_Save_Service {
	const ROW_TYPE = 'product';

	/**
	 * @var string[]
	 */
	private $allowed_fields = array(
		'title',
		'sku',
		'status',
		'regular_price',
		'sale_price',
		'stock_quantity',
		'menu_order',
	);

	/**
	 * Save a single parent product row.
	 *
	 * @param array $row Row payload.
	 * @return array<string, mixed>
	 */
	public function save_row( array $row ): array {
		$validation = $this->validate_row_payload( $row );

		if ( ! $validation['valid'] ) {
			return $this->error_result(
				$validation['id'],
				$validation['errors'],
				$validation['message'],
				$validation['changes']
			);
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return $this->error_result(
				$validation['id'],
				array(
					'product' => __( 'WooCommerce is not available.', 'product-admin-tool' ),
				),
				__( 'WooCommerce is not available.', 'product-admin-tool' ),
				$validation['changes']
			);
		}

		$product = wc_get_product( $validation['id'] );

		if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'save' ) ) {
			return $this->error_result(
				$validation['id'],
				array(
					'product' => __( 'Product could not be loaded.', 'product-admin-tool' ),
				),
				__( 'Product could not be loaded.', 'product-admin-tool' ),
				$validation['changes']
			);
		}

		if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) ) {
			return $this->error_result(
				$validation['id'],
				array(
					'row_type' => __( 'Variation rows are not handled by the parent product save service.', 'product-admin-tool' ),
				),
				__( 'Variation rows are not handled by the parent product save service.', 'product-admin-tool' ),
				$validation['changes']
			);
		}

		$apply_result = $this->apply_changes( $product, $validation['changes'] );

		if ( ! $apply_result['valid'] ) {
			return $this->error_result(
				$validation['id'],
				$apply_result['errors'],
				$apply_result['message'],
				$validation['changes']
			);
		}

		try {
			$product->save();
		} catch ( Exception $e ) {
			return $this->error_result(
				$validation['id'],
				array(
					'save' => $e->getMessage(),
				),
				__( 'Product save failed.', 'product-admin-tool' ),
				$validation['changes']
			);
		}

		return $this->success_result(
			$validation['id'],
			$this->normalize_product_data( $product ),
			__( 'Saved successfully.', 'product-admin-tool' )
		);
	}

	/**
	 * Validate the incoming row payload.
	 *
	 * @param array $row Raw payload.
	 * @return array<string, mixed>
	 */
	private function validate_row_payload( array $row ): array {
		$id      = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		$row_type = isset( $row['row_type'] ) ? sanitize_key( (string) $row['row_type'] ) : self::ROW_TYPE;
		$changes = isset( $row['changes'] ) && is_array( $row['changes'] ) ? $row['changes'] : array();

		$errors = array();

		if ( $id <= 0 ) {
			$errors['id'] = __( 'Row ID is required.', 'product-admin-tool' );
		}

		if ( self::ROW_TYPE !== $row_type ) {
			$errors['row_type'] = __( 'Only parent product rows can be saved here.', 'product-admin-tool' );
		}

		$sanitized_result = $this->sanitize_changes( $changes );
		$sanitized_changes = isset( $sanitized_result['changes'] ) && is_array( $sanitized_result['changes'] ) ? $sanitized_result['changes'] : array();
		$errors = array_merge( $errors, isset( $sanitized_result['errors'] ) && is_array( $sanitized_result['errors'] ) ? $sanitized_result['errors'] : array() );

		if ( empty( $sanitized_changes ) ) {
			$errors['changes'] = __( 'No supported changes were provided.', 'product-admin-tool' );
		}

		return array(
			'valid'   => empty( $errors ),
			'id'      => $id,
			'errors'  => $errors,
			'message' => empty( $errors ) ? '' : __( 'Invalid row payload.', 'product-admin-tool' ),
			'changes' => $sanitized_changes,
		);
	}

	/**
	 * Apply whitelisted changes to the product object.
	 *
	 * @param WC_Product $product Product object.
	 * @param array      $changes Sanitized changes.
	 * @return array<string, mixed>
	 */
	private function apply_changes( $product, array $changes ): array {
		$errors = array();

		foreach ( $changes as $field => $value ) {
			switch ( $field ) {
				case 'title':
					if ( method_exists( $product, 'set_name' ) ) {
						$product->set_name( $value );
					} else {
						$product->set_props( array( 'name' => $value ) );
					}
					break;

				case 'sku':
					if ( method_exists( $product, 'set_sku' ) ) {
						$product->set_sku( $value );
					}
					break;

				case 'status':
					if ( method_exists( $product, 'set_status' ) ) {
						$product->set_status( $value );
					}
					break;

				case 'regular_price':
					if ( method_exists( $product, 'set_regular_price' ) ) {
						$product->set_regular_price( '' === $value ? '' : (string) $value );
					}
					break;

				case 'sale_price':
					if ( method_exists( $product, 'set_sale_price' ) ) {
						$product->set_sale_price( '' === $value ? '' : (string) $value );
					}
					break;

				case 'stock_quantity':
					if ( method_exists( $product, 'set_manage_stock' ) ) {
						$product->set_manage_stock( '' !== $value && null !== $value );
					}

					if ( method_exists( $product, 'set_stock_quantity' ) ) {
						$product->set_stock_quantity( null === $value ? null : (int) $value );
					}

					if ( method_exists( $product, 'set_stock_status' ) && '' === $value ) {
						$product->set_stock_status( 'instock' );
					}
					break;

				case 'menu_order':
					if ( method_exists( $product, 'set_menu_order' ) ) {
						$product->set_menu_order( (int) $value );
					}
					break;

				default:
					$errors[ $field ] = __( 'Unsupported field.', 'product-admin-tool' );
					break;
			}
		}

		if ( isset( $changes['sale_price'] ) && '' !== $changes['sale_price'] && isset( $changes['regular_price'] ) && '' !== $changes['regular_price'] ) {
			if ( (float) $changes['sale_price'] > (float) $changes['regular_price'] ) {
				$errors['sale_price'] = __( 'Sale price cannot exceed regular price.', 'product-admin-tool' );
			}
		}

		if ( ! empty( $errors ) ) {
			return array(
				'valid'   => false,
				'errors'  => $errors,
				'message' => __( 'Validation failed.', 'product-admin-tool' ),
			);
		}

		$sale_price_validation = $this->validate_sale_price_relationship( $product, $changes );

		if ( ! $sale_price_validation['valid'] ) {
			return $sale_price_validation;
		}

		return array(
			'valid'   => true,
			'errors'  => array(),
			'message' => '',
		);
	}

	/**
	 * Sanitize and whitelist incoming changes.
	 *
	 * @param array $changes Raw changes.
	 * @return array<string, mixed>
	 */
	private function sanitize_changes( array $changes ): array {
		$sanitized = array();
		$errors    = array();

		foreach ( $changes as $field => $value ) {
			$field = sanitize_key( (string) $field );

			if ( ! in_array( $field, $this->allowed_fields, true ) ) {
				continue;
			}

			switch ( $field ) {
				case 'title':
					$value = sanitize_text_field( (string) $value );

					if ( '' === $value ) {
						$errors['title'] = __( 'Title cannot be empty.', 'product-admin-tool' );
						continue 2;
					}
					break;

				case 'sku':
					$value = sanitize_text_field( (string) $value );
					break;

				case 'status':
					$value = $this->sanitize_status( $value );

					if ( '' === $value ) {
						$errors['status'] = __( 'Status must be publish, draft, pending, or private.', 'product-admin-tool' );
						continue 2;
					}
					break;

				case 'regular_price':
				case 'sale_price':
					$decimal = $this->sanitize_decimal( $value );

					if ( null === $decimal ) {
						$errors[ $field ] = __( 'Price must be numeric.', 'product-admin-tool' );
						continue 2;
					}

					$value = $decimal;
					break;

				case 'stock_quantity':
					$integer = $this->sanitize_integer_or_empty( $value );

					if ( null === $integer ) {
						$errors['stock_quantity'] = __( 'Stock quantity must be a non-negative integer.', 'product-admin-tool' );
						continue 2;
					}

					$value = $integer;
					break;

				case 'menu_order':
					$value = intval( $value );
					break;
			}

			$sanitized[ $field ] = $value;
		}

		return array(
			'changes' => $sanitized,
			'errors'  => $errors,
		);
	}

	/**
	 * Validate the relationship between regular and sale price.
	 *
	 * @param WC_Product $product Product object.
	 * @param array      $changes Sanitized changes.
	 * @return array<string, mixed>
	 */
	private function validate_sale_price_relationship( $product, array $changes ): array {
		if ( ! array_key_exists( 'sale_price', $changes ) ) {
			return array(
				'valid'   => true,
				'errors'  => array(),
				'message' => '',
			);
		}

		$sale_price = $changes['sale_price'];

		if ( '' === $sale_price || null === $sale_price ) {
			return array(
				'valid'   => true,
				'errors'  => array(),
				'message' => '',
			);
		}

		$regular_price = null;

		if ( array_key_exists( 'regular_price', $changes ) ) {
			$regular_price = $changes['regular_price'];
		} elseif ( method_exists( $product, 'get_regular_price' ) ) {
			$regular_price = $product->get_regular_price();
		}

		if ( '' === $regular_price || null === $regular_price ) {
			return array(
				'valid'   => true,
				'errors'  => array(),
				'message' => '',
			);
		}

		if ( (float) $sale_price > (float) $regular_price ) {
			return array(
				'valid'   => false,
				'errors'  => array(
					'sale_price' => __( 'Sale price cannot exceed regular price.', 'product-admin-tool' ),
				),
				'message' => __( 'Validation failed.', 'product-admin-tool' ),
			);
		}

		return array(
			'valid'   => true,
			'errors'  => array(),
			'message' => '',
		);
	}

	/**
	 * Sanitize a WooCommerce post status value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_status( $value ): string {
		$value = sanitize_key( (string) $value );

		$allowed = array( 'publish', 'draft', 'pending', 'private' );

		if ( in_array( $value, $allowed, true ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Sanitize a decimal value or return an empty string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_decimal( $value ) {
		if ( '' === $value || null === $value ) {
			return '';
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( ! is_numeric( $value ) ) {
			return null;
		}

		return function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $value, wc_get_price_decimals() ) : (string) floatval( $value );
	}

	/**
	 * Sanitize an integer-like value, preserving an empty string when blank.
	 *
	 * @param mixed $value Raw value.
	 * @return int|string
	 */
	private function sanitize_integer_or_empty( $value ) {
		if ( '' === $value || null === $value ) {
			return '';
		}

		$validated = filter_var(
			$value,
			FILTER_VALIDATE_INT,
			array(
				'options' => array(
					'min_range' => 0,
				),
			)
		);

		if ( false === $validated ) {
			return null;
		}

		return (int) $validated;
	}

	/**
	 * Normalize product data for AJAX responses.
	 *
	 * @param WC_Product $product Product object.
	 * @return array<string, mixed>
	 */
	private function normalize_product_data( $product ): array {
		return array(
			'id'             => method_exists( $product, 'get_id' ) ? (int) $product->get_id() : 0,
			'row_type'       => self::ROW_TYPE,
			'title'          => method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '',
			'sku'            => method_exists( $product, 'get_sku' ) ? (string) $product->get_sku() : '',
			'status'         => method_exists( $product, 'get_status' ) ? (string) $product->get_status() : '',
			'regular_price'  => method_exists( $product, 'get_regular_price' ) ? (string) $product->get_regular_price() : '',
			'sale_price'     => method_exists( $product, 'get_sale_price' ) ? (string) $product->get_sale_price() : '',
			'stock_quantity' => method_exists( $product, 'get_stock_quantity' ) ? $product->get_stock_quantity() : null,
			'menu_order'     => method_exists( $product, 'get_menu_order' ) ? (int) $product->get_menu_order() : 0,
		);
	}

	/**
	 * Build a success response.
	 *
	 * @param int    $id      Row ID.
	 * @param array  $data    Normalized row data.
	 * @param string $message Response message.
	 * @return array<string, mixed>
	 */
	private function success_result( int $id, array $data, string $message ): array {
		if ( class_exists( 'PAT_Save_Result' ) && method_exists( 'PAT_Save_Result', 'success' ) ) {
			return PAT_Save_Result::success( $id, self::ROW_TYPE, $message, $data );
		}

		return array(
			'id'      => $id,
			'row_type'=> self::ROW_TYPE,
			'status'  => 'saved',
			'message' => $message,
			'errors'  => array(),
			'data'    => $data,
		);
	}

	/**
	 * Build an error response.
	 *
	 * @param int    $id      Row ID.
	 * @param array  $errors  Error map.
	 * @param string $message Error message.
	 * @param array  $changes  Normalized changes.
	 * @return array<string, mixed>
	 */
	private function error_result( int $id, array $errors, string $message, array $changes = array() ): array {
		if ( class_exists( 'PAT_Save_Result' ) && method_exists( 'PAT_Save_Result', 'error' ) ) {
			return PAT_Save_Result::error( $id, self::ROW_TYPE, $message, $errors, $changes );
		}

		return array(
			'id'      => $id,
			'row_type'=> self::ROW_TYPE,
			'status'  => 'error',
			'message' => $message,
			'errors'  => $errors,
			'data'    => array(
				'changes' => $changes,
			),
		);
	}
}
