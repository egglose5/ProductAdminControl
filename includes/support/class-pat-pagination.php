<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Value object for pagination state.
 */
class PAT_Pagination {
	private $page;
	private $per_page;
	private $total_items;

	public function __construct( int $page, int $per_page, int $total_items = 0 ) {
		$this->page        = max( 1, $page );
		$this->per_page    = max( 1, $per_page );
		$this->total_items = max( 0, $total_items );
	}

	/**
	 * Create an instance from a plain array payload.
	 *
	 * @param array $data Raw pagination data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self(
			isset( $data['page'] ) ? absint( $data['page'] ) : 1,
			isset( $data['per_page'] ) ? absint( $data['per_page'] ) : 20,
			isset( $data['total_items'] ) ? absint( $data['total_items'] ) : 0
		);
	}

	/**
	 * Return the current page number.
	 *
	 * @return int
	 */
	public function get_page(): int {
		return $this->page;
	}

	/**
	 * Return the page size.
	 *
	 * @return int
	 */
	public function get_per_page(): int {
		return $this->per_page;
	}

	/**
	 * Return the total item count.
	 *
	 * @return int
	 */
	public function get_total_items(): int {
		return $this->total_items;
	}

	/**
	 * Return the total number of pages.
	 *
	 * @return int
	 */
	public function get_total_pages(): int {
		if ( 0 === $this->total_items ) {
			return 0;
		}

		return (int) ceil( $this->total_items / $this->per_page );
	}

	/**
	 * Return the first item index on the current page.
	 *
	 * @return int
	 */
	public function get_range_start(): int {
		if ( 0 === $this->total_items ) {
			return 0;
		}

		return ( ( $this->page - 1 ) * $this->per_page ) + 1;
	}

	/**
	 * Return the last item index on the current page.
	 *
	 * @return int
	 */
	public function get_range_end(): int {
		if ( 0 === $this->total_items ) {
			return 0;
		}

		return min( $this->page * $this->per_page, $this->total_items );
	}

	/**
	 * Determine whether there is a next page.
	 *
	 * @return bool
	 */
	public function has_next_page(): bool {
		return $this->page < $this->get_total_pages();
	}

	/**
	 * Determine whether there is a previous page.
	 *
	 * @return bool
	 */
	public function has_previous_page(): bool {
		return $this->page > 1;
	}

	/**
	 * Export the pagination data as a plain array.
	 *
	 * @return array<string, int|bool>
	 */
	public function to_array(): array {
		return array(
			'page'             => $this->get_page(),
			'per_page'         => $this->get_per_page(),
			'total_items'      => $this->get_total_items(),
			'total_pages'      => $this->get_total_pages(),
			'range_start'      => $this->get_range_start(),
			'range_end'        => $this->get_range_end(),
			'has_next_page'     => $this->has_next_page(),
			'has_previous_page' => $this->has_previous_page(),
		);
	}
}
