<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAT_Variation_Generator_Service {
	const ROW_TYPE       = 'variation';
	const PREVIEW_STATUS = 'preview';

	/**
	 * Preview missing variation combinations for a variable parent product.
	 *
	 * This method only calculates preview rows. It does not create or save
	 * WooCommerce variations.
	 *
	 * @param int   $parent_id Variable parent product ID.
	 * @param array $args      Optional preview defaults.
	 * @return array<string, mixed>
	 */
	public function preview_missing_combinations( int $parent_id, array $args = array() ): array {
		$parent_id = absint( $parent_id );

		if ( $parent_id <= 0 ) {
			return $this->error_payload(
				$parent_id,
				array(
					'parent_id' => __( 'Parent product ID is required.', 'product-admin-tool' ),
				),
				__( 'Invalid parent product ID.', 'product-admin-tool' )
			);
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return $this->error_payload(
				$parent_id,
				array(
					'woocommerce' => __( 'WooCommerce is not available.', 'product-admin-tool' ),
				),
				__( 'WooCommerce is not available.', 'product-admin-tool' )
			);
		}

		$product = wc_get_product( $parent_id );

		if ( ! $this->is_variable_parent_product( $product ) ) {
			return $this->error_payload(
				$parent_id,
				array(
					'product' => __( 'Variable parent product could not be loaded.', 'product-admin-tool' ),
				),
				__( 'Variable parent product could not be loaded.', 'product-admin-tool' )
			);
		}

		$preview_args       = $this->normalize_preview_args( $args );
		$variation_options  = $this->get_parent_variation_options( $product );
		$existing_signatures = $this->get_existing_variation_signatures( $product, array_keys( $variation_options ) );
		$combinations       = $this->build_combinations( $variation_options );
		$generated_rows     = array();
		$seen_signatures    = array();

		foreach ( $combinations as $combination ) {
			$signature = $this->build_signature( $combination );

			if ( '' === $signature ) {
				continue;
			}

			if ( isset( $existing_signatures[ $signature ] ) || isset( $seen_signatures[ $signature ] ) ) {
				continue;
			}

			$seen_signatures[ $signature ] = true;
			$generated_rows[]              = $this->build_preview_row( $product, $combination, $signature, $preview_args, count( $generated_rows ) + 1 );
		}

		$possible_combinations = $this->count_combinations( $variation_options );
		$missing_count         = count( $generated_rows );

		return array(
			'success'    => true,
			'status'     => self::PREVIEW_STATUS,
			'message'    => $this->build_success_message( $missing_count ),
			'parent_id'  => $parent_id,
			'parent_type'=> method_exists( $product, 'get_type' ) ? (string) $product->get_type() : 'variable',
			'parent_title'=> method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '',
			'row_type'   => self::ROW_TYPE,
			'generated_rows' => $generated_rows,
			'summary'    => array(
				'attribute_count'       => count( $variation_options ),
				'attribute_keys'        => array_values( array_keys( $variation_options ) ),
				'existing_variations'   => count( $existing_signatures ),
				'possible_combinations' => $possible_combinations,
				'missing_combinations'  => $missing_count,
			),
			'errors'     => array(),
		);
	}

	/**
	 * Normalize preview defaults.
	 *
	 * @param array $args Raw preview args.
	 * @return array<string, mixed>
	 */
	private function normalize_preview_args( array $args ): array {
		$defaults = array(
			'default_status'         => 'draft',
			'default_regular_price'   => '',
			'default_sale_price'      => '',
			'default_stock_quantity'  => '',
			'default_menu_order'      => 0,
		);

		$args = array_merge( $defaults, $args );
		$args['default_status'] = $this->sanitize_status( $args['default_status'] );

		if ( '' === $args['default_status'] ) {
			$args['default_status'] = 'draft';
		}

		$args['default_menu_order'] = (int) $args['default_menu_order'];

		return $args;
	}

	/**
	 * Detect whether the loaded product is a variable parent.
	 *
	 * @param object|null $product Product object.
	 * @return bool
	 */
	private function is_variable_parent_product( $product ): bool {
		if ( ! is_object( $product ) ) {
			return false;
		}

		if ( ! method_exists( $product, 'is_type' ) ) {
			return false;
		}

		return (bool) $product->is_type( 'variable' );
	}

	/**
	 * Read and normalize the parent product's variation attributes.
	 *
	 * Returned structure preserves the parent attribute order:
	 *   array(
	 *     'pa_color' => array( 'black', 'brown' ),
	 *     'pa_size'  => array( 'a6', 'a5' ),
	 *   )
	 *
	 * If any attribute is missing usable options, the product cannot form
	 * complete variation combinations and an empty array is returned.
	 *
	 * @param object $product Variable product object.
	 * @return array<string, array<int, string>>
	 */
	private function get_parent_variation_options( $product ): array {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_variation_attributes' ) ) {
			return array();
		}

		$raw_attributes    = (array) $product->get_variation_attributes();
		$variation_options  = array();

		foreach ( $raw_attributes as $attribute_name => $options ) {
			$normalized_name = $this->normalize_attribute_name( $attribute_name );

			if ( '' === $normalized_name ) {
				continue;
			}

			$normalized_options = $this->normalize_attribute_options( $options );

			if ( empty( $normalized_options ) ) {
				return array();
			}

			$variation_options[ $normalized_name ] = $normalized_options;
		}

		return $variation_options;
	}

	/**
	 * Normalize raw attribute option values while preserving display values.
	 *
	 * @param mixed $options Raw option list.
	 * @return array<int, string>
	 */
	private function normalize_attribute_options( $options ): array {
		if ( ! is_array( $options ) ) {
			$options = array( $options );
		}

		$normalized = array();
		$seen       = array();

		foreach ( $options as $option ) {
			if ( is_array( $option ) || is_object( $option ) ) {
				continue;
			}

			$option = trim( (string) $option );

			if ( '' === $option ) {
				continue;
			}

			$signature = $this->normalize_signature_value( $option );

			if ( '' === $signature || isset( $seen[ $signature ] ) ) {
				continue;
			}

			$seen[ $signature ] = true;
			$normalized[]       = $option;
		}

		return $normalized;
	}

	/**
	 * Build a list of normalized signatures for existing child variations.
	 *
	 * @param object               $product Variable product object.
	 * @param array<int, string>    $attribute_names Parent attribute names.
	 * @return array<string, bool>
	 */
	private function get_existing_variation_signatures( $product, array $attribute_names ): array {
		$signatures = array();

		if ( ! is_object( $product ) || ! method_exists( $product, 'get_children' ) ) {
			return $signatures;
		}

		$attribute_names = array_values(
			array_filter(
				array_map(
					array( $this, 'normalize_attribute_name' ),
					$attribute_names
				)
			)
		);

		foreach ( (array) $product->get_children() as $child_id ) {
			$child_id  = absint( $child_id );
			$variation = function_exists( 'wc_get_product' ) ? wc_get_product( $child_id ) : null;

			if ( ! is_object( $variation ) || ! method_exists( $variation, 'get_variation_attributes' ) ) {
				continue;
			}

			$normalized = $this->normalize_variation_attributes_for_compare(
				(array) $variation->get_variation_attributes(),
				$attribute_names
			);

			if ( empty( $normalized ) || count( $normalized ) !== count( $attribute_names ) ) {
				continue;
			}

			$signature = $this->build_signature( $normalized );

			if ( '' !== $signature ) {
				$signatures[ $signature ] = true;
			}
		}

		return $signatures;
	}

	/**
	 * Normalize a variation attribute map for signature comparison.
	 *
	 * @param array<string, mixed> $attributes Variation attribute data.
	 * @param array<int, string>   $attribute_names Ordered parent attribute names.
	 * @return array<string, string>
	 */
	private function normalize_variation_attributes_for_compare( array $attributes, array $attribute_names ): array {
		$normalized = array();

		foreach ( $attribute_names as $attribute_name ) {
			$attribute_name = $this->normalize_attribute_name( $attribute_name );

			if ( '' === $attribute_name ) {
				continue;
			}

			$found_value = null;

			foreach ( $attributes as $raw_key => $raw_value ) {
				if ( $attribute_name !== $this->normalize_attribute_name( $raw_key ) ) {
					continue;
				}

				$found_value = $this->normalize_signature_value( $raw_value );
				break;
			}

			if ( null === $found_value || '' === $found_value ) {
				return array();
			}

			$normalized[ $attribute_name ] = $found_value;
		}

		return $normalized;
	}

	/**
	 * Generate every possible attribute combination from the parent options.
	 *
	 * @param array<string, array<int, string>> $variation_options Normalized variation options.
	 * @return array<int, array<string, string>>
	 */
	private function build_combinations( array $variation_options ): array {
		if ( empty( $variation_options ) ) {
			return array();
		}

		$combinations = array(
			array(),
		);

		foreach ( $variation_options as $attribute_name => $options ) {
			$next_combinations = array();

			foreach ( $combinations as $combination ) {
				foreach ( $options as $option ) {
					$updated_combination                     = $combination;
					$updated_combination[ $attribute_name ] = $option;
					$next_combinations[]                    = $updated_combination;
				}
			}

			$combinations = $next_combinations;
		}

		return $combinations;
	}

	/**
	 * Count the total possible combinations for summary output.
	 *
	 * @param array<string, array<int, string>> $variation_options Normalized variation options.
	 * @return int
	 */
	private function count_combinations( array $variation_options ): int {
		if ( empty( $variation_options ) ) {
			return 0;
		}

		$total = 1;

		foreach ( $variation_options as $options ) {
			$count = count( $options );

			if ( $count <= 0 ) {
				return 0;
			}

			$total *= $count;
		}

		return $total;
	}

	/**
	 * Build a preview row for a missing variation combination.
	 *
	 * @param object                           $product Variable product object.
	 * @param array<string, string>            $combination Combination map.
	 * @param string                           $signature Normalized combination signature.
	 * @param array<string, mixed>              $preview_args Preview defaults.
	 * @param int                              $index Preview index.
	 * @return array<string, mixed>
	 */
	private function build_preview_row( $product, array $combination, string $signature, array $preview_args, int $index ): array {
		$parent_id = method_exists( $product, 'get_id' ) ? (int) $product->get_id() : 0;
		$temp_id   = $this->build_temp_id( $parent_id, $signature );

		return array(
			'temp_id'           => $temp_id,
			'id'                => 0,
			'parent_id'         => $parent_id,
			'row_type'          => self::ROW_TYPE,
			'status'            => (string) $preview_args['default_status'],
			'is_generated'      => true,
			'is_preview'        => true,
			'preview_index'     => $index,
			'source_signature'   => $signature,
			'attribute_summary'  => $this->build_attribute_summary( $product, $combination ),
			'raw_attributes'     => $combination,
			'attributes'         => $this->build_variation_attribute_payload( $combination ),
			'sku'               => '',
			'regular_price'     => (string) $preview_args['default_regular_price'],
			'sale_price'        => (string) $preview_args['default_sale_price'],
			'stock_quantity'    => $preview_args['default_stock_quantity'],
			'menu_order'        => (int) $preview_args['default_menu_order'],
			'editable_fields'   => array(
				'sku'            => '',
				'status'         => (string) $preview_args['default_status'],
				'regular_price'  => (string) $preview_args['default_regular_price'],
				'sale_price'     => (string) $preview_args['default_sale_price'],
				'stock_quantity' => $preview_args['default_stock_quantity'],
				'menu_order'     => (int) $preview_args['default_menu_order'],
			),
		);
	}

	/**
	 * Convert a raw combination into Woo variation attribute keys.
	 *
	 * @param array<string, string> $combination Raw combination map.
	 * @return array<string, string>
	 */
	private function build_variation_attribute_payload( array $combination ): array {
		$payload = array();

		foreach ( $combination as $attribute_name => $value ) {
			$payload[ $this->build_variation_attribute_key( $attribute_name ) ] = (string) $value;
		}

		return $payload;
	}

	/**
	 * Build a human-readable summary for a combination.
	 *
	 * @param object                $product Variable product object.
	 * @param array<string, string>  $combination Combination map.
	 * @return string
	 */
	private function build_attribute_summary( $product, array $combination ): string {
		$parts = array();

		foreach ( $combination as $attribute_name => $value ) {
			$label = $this->get_attribute_label( $product, $attribute_name );
			$parts[] = trim( $label . ': ' . $this->get_attribute_display_value( $attribute_name, $value ) );
		}

		return implode( ' | ', $parts );
	}

	/**
	 * Get a friendly attribute label.
	 *
	 * @param object $product Variable product object.
	 * @param string $attribute_name Attribute name.
	 * @return string
	 */
	private function get_attribute_label( $product, string $attribute_name ): string {
		$attribute_name = $this->normalize_attribute_name( $attribute_name );

		if ( '' === $attribute_name ) {
			return '';
		}

		if ( function_exists( 'wc_attribute_label' ) ) {
			$label = wc_attribute_label( $attribute_name, $product );

			if ( is_string( $label ) && '' !== trim( $label ) ) {
				return trim( $label );
			}
		}

		$label = str_replace( array( 'pa_', '-', '_' ), array( '', ' ', ' ' ), $attribute_name );

		return trim( ucwords( $label ) );
	}

	/**
	 * Get a readable display value for an attribute option.
	 *
	 * @param string $attribute_name Attribute name.
	 * @param string $value Raw option value.
	 * @return string
	 */
	private function get_attribute_display_value( string $attribute_name, string $value ): string {
		$attribute_name = $this->normalize_attribute_name( $attribute_name );
		$value          = trim( $value );

		if ( '' === $attribute_name || '' === $value ) {
			return $value;
		}

		if ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( $attribute_name ) && function_exists( 'get_term_by' ) ) {
			$term = get_term_by( 'slug', $value, $attribute_name );

			if ( $term && ! is_wp_error( $term ) && isset( $term->name ) ) {
				return (string) $term->name;
			}
		}

		$display = str_replace( array( '-', '_' ), ' ', $value );
		$display = trim( $display );

		return '' !== $display ? ucwords( $display ) : $value;
	}

	/**
	 * Build the normalized variation attribute key used by WooCommerce.
	 *
	 * @param string $attribute_name Attribute name.
	 * @return string
	 */
	private function build_variation_attribute_key( string $attribute_name ): string {
		$attribute_name = $this->normalize_attribute_name( $attribute_name );

		if ( '' === $attribute_name ) {
			return '';
		}

		if ( function_exists( 'wc_variation_attribute_name' ) ) {
			return wc_variation_attribute_name( $attribute_name );
		}

		return 'attribute_' . $attribute_name;
	}

	/**
	 * Normalize an attribute name into a stable key.
	 *
	 * @param mixed $attribute_name Raw attribute name.
	 * @return string
	 */
	private function normalize_attribute_name( $attribute_name ): string {
		$attribute_name = sanitize_key( (string) $attribute_name );
		$attribute_name = preg_replace( '/^attribute_/', '', $attribute_name );

		return is_string( $attribute_name ) ? trim( $attribute_name ) : '';
	}

	/**
	 * Normalize a value for signature comparison.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function normalize_signature_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = trim( wp_strip_all_tags( (string) $value ) );

		if ( '' === $value ) {
			return '';
		}

		$value = preg_replace( '/\s+/', ' ', $value );

		if ( function_exists( 'mb_strtolower' ) ) {
			$value = mb_strtolower( $value, 'UTF-8' );
		} else {
			$value = strtolower( $value );
		}

		return trim( $value );
	}

	/**
	 * Build a deterministic signature from an attribute combination.
	 *
	 * @param array<string, string> $combination Combination map.
	 * @return string
	 */
	private function build_signature( array $combination ): string {
		if ( empty( $combination ) ) {
			return '';
		}

		ksort( $combination );

		$parts = array();

		foreach ( $combination as $attribute_name => $value ) {
			$attribute_name = $this->normalize_attribute_name( $attribute_name );
			$value          = $this->normalize_signature_value( $value );

			if ( '' === $attribute_name || '' === $value ) {
				return '';
			}

			$parts[] = $attribute_name . '=' . $value;
		}

		return implode( '|', $parts );
	}

	/**
	 * Build a stable temporary row ID from a signature.
	 *
	 * @param int    $parent_id Parent product ID.
	 * @param string $signature Normalized signature.
	 * @return string
	 */
	private function build_temp_id( int $parent_id, string $signature ): string {
		$hash = substr( md5( $signature ), 0, 12 );

		return 'pat-gen-' . $parent_id . '-' . $hash;
	}

	/**
	 * Build a user-facing message for the preview payload.
	 *
	 * @param int $missing_count Missing combinations count.
	 * @return string
	 */
	private function build_success_message( int $missing_count ): string {
		if ( $missing_count <= 0 ) {
			return __( 'No missing variation combinations were found.', 'product-admin-tool' );
		}

		return sprintf(
			/* translators: %d: number of generated rows. */
			__( 'Generated %d missing variation combination(s).', 'product-admin-tool' ),
			$missing_count
		);
	}

	/**
	 * Build a normalized error payload.
	 *
	 * @param int    $parent_id Parent product ID.
	 * @param array  $errors    Error map.
	 * @param string $message   Error message.
	 * @return array<string, mixed>
	 */
	private function error_payload( int $parent_id, array $errors, string $message ): array {
		return array(
			'success'        => false,
			'status'         => 'error',
			'message'        => $message,
			'parent_id'      => $parent_id,
			'row_type'       => self::ROW_TYPE,
			'generated_rows' => array(),
			'summary'        => array(
				'attribute_count'       => 0,
				'attribute_keys'        => array(),
				'existing_variations'   => 0,
				'possible_combinations' => 0,
				'missing_combinations'  => 0,
			),
			'errors'         => $errors,
		);
	}
}
