<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Variation_Save_Service {
	const ROW_TYPE = 'variation';

	/**
	 * Save a variation row payload.
	 *
	 * Expected keys:
	 * - id
	 * - changes
	 *
	 * @param array $row Row payload.
	 * @return array<string, mixed>
	 */
	public function save_row( array $row ): array {
		$validation = $this->validate_row_payload( $row );

		if ( is_wp_error( $validation ) ) {
			return $this->error_result(
				isset( $row['id'] ) ? absint( $row['id'] ) : 0,
				$validation->get_error_message(),
				$validation->get_error_data()
			);
		}

		if ( isset( $validation['valid'] ) && ! $validation['valid'] ) {
			return $this->error_result(
				isset( $validation['id'] ) ? absint( $validation['id'] ) : 0,
				__( 'Invalid row payload.', 'product-admin-tool' ),
				isset( $validation['errors'] ) && is_array( $validation['errors'] ) ? $validation['errors'] : array()
			);
		}

		$variation = wc_get_product( absint( $row['id'] ) );

		if ( ! $variation instanceof WC_Product_Variation ) {
			return $this->error_result(
				absint( $row['id'] ),
				__( 'Variation could not be loaded.', 'product-admin-tool' )
			);
		}

		$changes = $this->normalize_changes( (array) $row['changes'] );
		$errors  = array();

		foreach ( $changes as $field => $value ) {
			$applied = $this->apply_change( $variation, $field, $value );

			if ( is_wp_error( $applied ) ) {
				$errors[ $field ] = $applied->get_error_message();
			}
		}

		if ( ! empty( $errors ) ) {
			return $this->error_result(
				$variation->get_id(),
				__( 'One or more fields could not be saved.', 'product-admin-tool' ),
				$errors
			);
		}

		try {
			$variation->save();
		} catch ( Exception $exception ) {
			return $this->error_result(
				$variation->get_id(),
				$exception->getMessage()
			);
		}

		return $this->success_result( $variation );
	}

	/**
	 * Validate the row payload before saving.
	 *
	 * @param array $row Row payload.
	 * @return true|WP_Error
	 */
	private function validate_row_payload( array $row ) {
		if ( class_exists( 'PAT_Row_Validation' ) && method_exists( 'PAT_Row_Validation', 'validate_row' ) ) {
			$validation = PAT_Row_Validation::validate_row( $row );

			if ( 'variation' !== PAT_Row_Validation::sanitize_row_type( $validation['row_type'] ?? '' ) ) {
				return array(
					'valid'   => false,
					'id'      => isset( $validation['id'] ) ? absint( $validation['id'] ) : 0,
					'errors'  => array(
						'row_type' => __( 'Row type must be variation.', 'product-admin-tool' ),
					),
					'message' => __( 'Invalid row payload.', 'product-admin-tool' ),
					'changes' => array(),
				);
			}

			return array(
				'valid'   => ! empty( $validation['valid'] ),
				'id'      => isset( $validation['id'] ) ? absint( $validation['id'] ) : 0,
				'errors'  => isset( $validation['errors'] ) && is_array( $validation['errors'] ) ? $validation['errors'] : array(),
				'message' => empty( $validation['valid'] ) ? __( 'Invalid row payload.', 'product-admin-tool' ) : '',
				'changes' => isset( $validation['changes'] ) && is_array( $validation['changes'] ) ? $validation['changes'] : array(),
			);
		}

		if ( empty( $row['id'] ) ) {
			return new WP_Error( 'pat_missing_id', __( 'Variation ID is required.', 'product-admin-tool' ) );
		}

		if ( empty( $row['changes'] ) || ! is_array( $row['changes'] ) ) {
			return new WP_Error( 'pat_missing_changes', __( 'Variation changes are required.', 'product-admin-tool' ) );
		}

		$row_type = isset( $row['row_type'] ) ? sanitize_key( (string) $row['row_type'] ) : self::ROW_TYPE;

		if ( self::ROW_TYPE !== $row_type ) {
			return new WP_Error( 'pat_invalid_row_type', __( 'Row type must be variation.', 'product-admin-tool' ) );
		}

		return true;
	}

	/**
	 * Normalize incoming change keys to the supported field set.
	 *
	 * @param array $changes Raw changes.
	 * @return array<string, mixed>
	 */
	private function normalize_changes( array $changes ): array {
		$allowed = array(
			'sku',
			'status',
			'regular_price',
			'sale_price',
			'stock_quantity',
			'menu_order',
		);

		$normalized = array();

		foreach ( $allowed as $field ) {
			if ( array_key_exists( $field, $changes ) ) {
				$normalized[ $field ] = $changes[ $field ];
			}
		}

		return $normalized;
	}

	/**
	 * Apply a single change to the variation.
	 *
	 * @param WC_Product_Variation $variation Variation object.
	 * @param string               $field     Field name.
	 * @param mixed                $value     Field value.
	 * @return true|WP_Error
	 */
	private function apply_change( WC_Product_Variation $variation, string $field, $value ) {
		if ( class_exists( 'PAT_Row_Validation' ) && method_exists( 'PAT_Row_Validation', 'sanitize_variation_field' ) ) {
			$value = PAT_Row_Validation::sanitize_variation_field( $field, $value );
		} else {
			$value = $this->sanitize_field_value( $field, $value );
		}

		switch ( $field ) {
			case 'sku':
				$variation->set_sku( (string) $value );
				return true;

			case 'status':
				$status = (string) $value;

				if ( ! in_array( $status, array( 'publish', 'private', 'draft', 'pending' ), true ) ) {
					return new WP_Error( 'pat_invalid_status', __( 'Status is not allowed for this variation.', 'product-admin-tool' ) );
				}

				$variation->set_status( $status );
				return true;

			case 'regular_price':
				$variation->set_regular_price( '' === $value ? '' : wc_format_decimal( $value, wc_get_price_decimals() ) );
				return true;

			case 'sale_price':
				$variation->set_sale_price( '' === $value ? '' : wc_format_decimal( $value, wc_get_price_decimals() ) );
				return true;

			case 'stock_quantity':
				if ( '' === $value ) {
					$variation->set_manage_stock( false );
					$variation->set_stock_quantity( null );
					return true;
				}

				$variation->set_manage_stock( true );
				$variation->set_stock_quantity( absint( $value ) );
				return true;

			case 'menu_order':
				$variation->set_menu_order( (int) $value );
				return true;
		}

		return new WP_Error( 'pat_unsupported_field', __( 'Field is not supported for variation saves.', 'product-admin-tool' ) );
	}

	/**
	 * Sanitize a field value without external helper classes.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	private function sanitize_field_value( string $field, $value ) {
		switch ( $field ) {
			case 'sku':
				return sanitize_text_field( (string) $value );

			case 'status':
				return sanitize_key( (string) $value );

			case 'regular_price':
			case 'sale_price':
				return '' === $value ? '' : wc_format_decimal( $value, wc_get_price_decimals() );

			case 'stock_quantity':
				return '' === $value ? '' : absint( $value );

			case 'menu_order':
				return (int) $value;
		}

		return $value;
	}

	/**
	 * Build a normalized success result.
	 *
	 * @param WC_Product_Variation $variation Variation object.
	 * @return array<string, mixed>
	 */
	private function success_result( WC_Product_Variation $variation ): array {
		$data = array(
			'id'               => $variation->get_id(),
			'row_type'         => self::ROW_TYPE,
			'status'           => 'saved',
			'message'          => __( 'Variation saved successfully.', 'product-admin-tool' ),
			'data'             => $this->normalize_saved_variation( $variation ),
		);

		if ( class_exists( 'PAT_Save_Result' ) && method_exists( 'PAT_Save_Result', 'success' ) ) {
			return PAT_Save_Result::success( $data['id'], $data['row_type'], $data['message'], $data['data'] );
		}

		return $data;
	}

	/**
	 * Build a normalized error result.
	 *
	 * @param int         $id      Row ID.
	 * @param string      $message Error message.
	 * @param array|null  $errors  Optional field errors.
	 * @return array<string, mixed>
	 */
	private function error_result( int $id, string $message, ?array $errors = null ): array {
		$data = array(
			'id'       => $id,
			'row_type' => self::ROW_TYPE,
			'status'   => 'error',
			'message'  => $message,
			'errors'   => $errors ? $errors : array(),
		);

		if ( class_exists( 'PAT_Save_Result' ) && method_exists( 'PAT_Save_Result', 'error' ) ) {
			return PAT_Save_Result::error( $data['id'], $data['row_type'], $data['message'], $data['errors'] );
		}

		return $data;
	}

	/**
	 * Normalize the saved variation for client consumption.
	 *
	 * @param WC_Product_Variation $variation Variation object.
	 * @return array<string, mixed>
	 */
	private function normalize_saved_variation( WC_Product_Variation $variation ): array {
		return array(
			'id'               => $variation->get_id(),
			'parent_id'        => $variation->get_parent_id(),
			'row_type'         => self::ROW_TYPE,
			'sku'              => (string) $variation->get_sku(),
			'status'           => $variation->get_status(),
			'regular_price'    => (string) $variation->get_regular_price(),
			'sale_price'       => (string) $variation->get_sale_price(),
			'stock_quantity'   => $variation->get_stock_quantity(),
			'menu_order'       => (int) $variation->get_menu_order(),
			'attribute_summary' => $this->build_attribute_summary( $variation ),
		);
	}

	/**
	 * Build a readable attribute summary for the variation.
	 *
	 * @param WC_Product_Variation $variation Variation object.
	 * @return string
	 */
	private function build_attribute_summary( WC_Product_Variation $variation ): string {
		$attributes = array();

		foreach ( (array) $variation->get_variation_attributes() as $attribute_name => $attribute_value ) {
			if ( '' === $attribute_value || null === $attribute_value ) {
				continue;
			}

			$label = str_replace( array( 'attribute_', 'pa_', '-', '_' ), array( '', '', ' ', ' ' ), (string) $attribute_name );
			$label = ucwords( trim( $label ) );
			$attributes[] = trim( $label . ': ' . (string) $attribute_value );
		}

		return implode( ' | ', $attributes );
	}
}
