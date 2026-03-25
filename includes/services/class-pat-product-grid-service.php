<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only grid service for the product editor.
 *
 * Composes paginated parent product rows and, when available, child variation rows.
 */
class PAT_Product_Grid_Service {
	const DEFAULT_PER_PAGE = 20;

	/**
	 * @var PAT_Product_Repository|null
	 */
	private $product_repository;

	/**
	 * @var PAT_Variation_Repository|null
	 */
	private $variation_repository;

	public function __construct( $product_repository = null, $variation_repository = null ) {
		$this->product_repository   = $this->resolve_product_repository( $product_repository );
		$this->variation_repository = $this->resolve_variation_repository( $variation_repository );
	}

	/**
	 * Build a page of grid data.
	 *
	 * Supported args:
	 * - page
	 * - per_page
	 * - search
	 * - status
	 * - category
	 * - include_variations
	 *
	 * @param array $args Query arguments.
	 * @return array<string, mixed>
	 */
	public function get_page( array $args = array() ): array {
		$query_args = $this->normalize_query_args( $args );

		if ( ! $this->product_repository || ! method_exists( $this->product_repository, 'get_page' ) ) {
			$pagination = new PAT_Pagination( $query_args['page'], $query_args['per_page'], 0 );

			return array(
				'rows'       => array(),
				'pagination' => $pagination->to_array(),
				'filters'    => $this->build_filters_payload( $query_args ),
			);
		}

		$page_data = (array) $this->product_repository->get_page( $query_args );
		$rows      = isset( $page_data['rows'] ) && is_array( $page_data['rows'] ) ? $page_data['rows'] : array();
		$filters   = isset( $page_data['filters'] ) && is_array( $page_data['filters'] ) ? $page_data['filters'] : $this->build_filters_payload( $query_args );
		$pagination = PAT_Pagination::from_array( isset( $page_data['pagination'] ) && is_array( $page_data['pagination'] ) ? $page_data['pagination'] : array() );

		if ( $pagination->get_total_items() <= 0 && isset( $page_data['pagination']['total_items'] ) ) {
			$pagination = new PAT_Pagination(
				$query_args['page'],
				$query_args['per_page'],
				absint( $page_data['pagination']['total_items'] )
			);
		}

		$rows = $this->enrich_parent_rows_for_lazy_variation_loading( $rows );

		return array(
			'rows'       => $rows,
			'pagination' => $pagination->to_array(),
			'filters'    => $filters,
		);
	}

	/**
	 * Normalize the query args used by the grid.
	 *
	 * @param array $args Raw request args.
	 * @return array<string, mixed>
	 */
	private function normalize_query_args( array $args ): array {
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : self::DEFAULT_PER_PAGE;
		$per_page = $per_page > 0 ? min( $per_page, 200 ) : self::DEFAULT_PER_PAGE;

		return array(
			'page'               => $page,
			'per_page'           => $per_page,
			'search'             => isset( $args['search'] ) ? sanitize_text_field( wp_unslash( $args['search'] ) ) : '',
			'status'             => $this->normalize_status_filter( $args['status'] ?? '' ),
			'category'           => isset( $args['category'] ) ? sanitize_title( wp_unslash( (string) $args['category'] ) ) : '',
			'include_variations'  => $this->normalize_boolean( $args['include_variations'] ?? true ),
		);
	}

	/**
	 * Enrich parent rows with metadata needed for later variation expansion.
	 *
	 * This keeps the first page render read-only and avoids loading every variation row
	 * up front while preserving enough context for a future expand-on-demand flow.
	 *
	 * @param array $rows Parent rows.
	 * @return array
	 */
	private function enrich_parent_rows_for_lazy_variation_loading( array $rows ): array {
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) ) {
				continue;
			}

			$row['child_rows']    = array();
			$row['child_count']   = 0;
			$row['children_lazy'] = ! empty( $row['has_children'] );
			$rows[ $index ]       = $row;
		}

		return $rows;
	}

	/**
	 * Build a normalized filters payload for the editor screen.
	 *
	 * @param array $args Query args.
	 * @return array<string, mixed>
	 */
	private function build_filters_payload( array $args ): array {
		return array(
			'search'            => isset( $args['search'] ) ? (string) $args['search'] : '',
			'status'            => isset( $args['status'] ) ? $args['status'] : '',
			'category'          => isset( $args['category'] ) ? (string) $args['category'] : '',
			'per_page'          => isset( $args['per_page'] ) ? absint( $args['per_page'] ) : self::DEFAULT_PER_PAGE,
			'include_variations' => ! empty( $args['include_variations'] ),
		);
	}

	/**
	 * Normalize a status filter value.
	 *
	 * @param mixed $status Raw status value.
	 * @return string|array<int, string>
	 */
	private function normalize_status_filter( $status ) {
		if ( is_array( $status ) ) {
			$status = array_values(
				array_filter(
					array_map( 'sanitize_key', $status )
				)
			);

			return $status ? $status : '';
		}

		$status = sanitize_key( (string) $status );

		return '' === $status || 'any' === $status ? '' : $status;
	}

	/**
	 * Normalize a boolean-like value.
	 *
	 * @param mixed $value Raw input.
	 * @return bool
	 */
	private function normalize_boolean( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return (bool) $value;
	}

	/**
	 * Resolve the product repository.
	 *
	 * @param mixed $repository Optional injected repository.
	 * @return PAT_Product_Repository|null
	 */
	private function resolve_product_repository( $repository ) {
		if ( $repository && is_object( $repository ) ) {
			return $repository;
		}

		if ( class_exists( 'PAT_Product_Repository' ) ) {
			return new PAT_Product_Repository();
		}

		return null;
	}

	/**
	 * Resolve the variation repository.
	 *
	 * @param mixed $repository Optional injected repository.
	 * @return PAT_Variation_Repository|null
	 */
	private function resolve_variation_repository( $repository ) {
		if ( $repository && is_object( $repository ) ) {
			return $repository;
		}

		if ( class_exists( 'PAT_Variation_Repository' ) ) {
			return new PAT_Variation_Repository();
		}

		return null;
	}
}
