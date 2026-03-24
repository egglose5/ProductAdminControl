<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_rows = ! empty( $rows );
?>
<div class="pat-grid-shell">
	<table class="widefat fixed striped pat-grid" aria-describedby="pat-grid-caption">
		<thead>
			<tr>
				<?php foreach ( $columns as $column_key => $column_label ) : ?>
					<th class="<?php echo esc_attr( 'pat-col-' . $column_key ); ?>">
						<?php echo esc_html( $column_label ); ?>
					</th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php if ( $has_rows ) : ?>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$row_id         = isset( $row['id'] ) ? (int) $row['id'] : 0;
					$row_dom_id     = 'pat-row-' . $row_id;
					$children       = isset( $row['child_rows'] ) && is_array( $row['child_rows'] ) ? $row['child_rows'] : array();
					$can_expand     = ! empty( $row['has_children'] );
					$product_name   = isset( $row['title'] ) ? (string) $row['title'] : '';
					$product_type   = isset( $row['type'] ) ? (string) $row['type'] : 'product';
					$product_link   = isset( $row['permalink'] ) ? (string) $row['permalink'] : '';
					$categories     = isset( $row['categories'] ) && is_array( $row['categories'] ) ? implode( ', ', $row['categories'] ) : '';
					$stock_display  = '';
					$modified_label = '';

					if ( isset( $row['stock_quantity'] ) && null !== $row['stock_quantity'] && '' !== (string) $row['stock_quantity'] ) {
						$stock_display = (string) $row['stock_quantity'];
					} elseif ( isset( $row['stock_status'] ) ) {
						$stock_display = (string) $row['stock_status'];
					}

					if ( isset( $row['modified'] ) && '' !== (string) $row['modified'] ) {
						$modified_label = mysql2date( get_option( 'date_format' ), (string) $row['modified'] );
					}
					?>
					<tr class="pat-row pat-parent-row" id="<?php echo esc_attr( $row_dom_id ); ?>" data-pat-row-id="<?php echo esc_attr( $row_id ); ?>">
						<td class="check-column">
							<input type="checkbox" disabled="disabled" />
						</td>
						<td class="pat-cell-product">
							<div class="pat-row-label pat-row-label-wrap">
								<?php if ( $can_expand ) : ?>
									<button
										type="button"
										class="button-link pat-row-toggle"
										aria-expanded="false"
										aria-disabled="true"
										data-pat-toggle-children="true"
										data-pat-target="<?php echo esc_attr( $row_dom_id ); ?>"
									>
										<span class="screen-reader-text"><?php esc_html_e( 'Toggle variations for', 'product-admin-tool' ); ?></span>
										<span aria-hidden="true">+</span>
									</button>
								<?php else : ?>
									<span class="pat-row-toggle pat-row-toggle-spacer" aria-hidden="true"></span>
								<?php endif; ?>

								<?php if ( '' !== $product_link ) : ?>
									<a class="pat-product-name pat-row-name" href="<?php echo esc_url( $product_link ); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html( $product_name ); ?></a>
								<?php else : ?>
									<strong class="pat-product-name pat-row-name"><?php echo esc_html( $product_name ); ?></strong>
								<?php endif; ?>

								<?php if ( $can_expand ) : ?>
									<span class="pat-badge"><?php esc_html_e( 'Variations available', 'product-admin-tool' ); ?></span>
								<?php endif; ?>
							</div>
							<div class="pat-row-meta">
								<span class="pat-row-id"><?php echo esc_html( sprintf( __( 'ID: %d', 'product-admin-tool' ), $row_id ) ); ?></span>
								<?php if ( '' !== $categories ) : ?>
									<span class="pat-row-family"><?php echo esc_html( $categories ); ?></span>
								<?php endif; ?>
							</div>
						</td>
						<td><?php echo esc_html( $product_type ); ?></td>
						<td><?php echo esc_html( isset( $row['sku'] ) ? (string) $row['sku'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['price'] ) ? (string) $row['price'] : '' ); ?></td>
						<td><?php echo esc_html( $stock_display ); ?></td>
						<td><?php echo esc_html( isset( $row['status'] ) ? (string) $row['status'] : '' ); ?></td>
						<td><?php echo esc_html( $modified_label ); ?></td>
					</tr>

					<?php foreach ( $children as $child ) : ?>
						<?php
						$child_id      = isset( $child['id'] ) ? (int) $child['id'] : 0;
						$child_stock   = isset( $child['stock'] ) ? (string) $child['stock'] : '';
						$child_summary = isset( $child['attribute_summary'] ) ? (string) $child['attribute_summary'] : '';
						$child_label   = '' !== $child_summary ? $child_summary : ( isset( $child['title'] ) ? (string) $child['title'] : __( 'Variation', 'product-admin-tool' ) );
						?>
						<tr class="pat-row pat-child-row is-child-row is-hidden" data-pat-parent="<?php echo esc_attr( $row_dom_id ); ?>" hidden>
							<td class="check-column">
								<input type="checkbox" disabled="disabled" />
							</td>
							<td class="pat-cell-product">
								<div class="pat-row-label pat-row-label-wrap pat-child-label-wrap">
									<span class="pat-child-indicator" aria-hidden="true">-</span>
									<strong class="pat-product-name pat-row-name"><?php echo esc_html( $child_label ); ?></strong>
								</div>
								<div class="pat-row-meta">
									<span class="pat-row-id"><?php echo esc_html( sprintf( __( 'ID: %d', 'product-admin-tool' ), $child_id ) ); ?></span>
									<?php if ( '' !== $child_summary ) : ?>
										<span class="pat-row-attributes"><?php echo esc_html( $child_summary ); ?></span>
									<?php endif; ?>
								</div>
							</td>
							<td><?php esc_html_e( 'variation', 'product-admin-tool' ); ?></td>
							<td><?php echo esc_html( isset( $child['sku'] ) ? (string) $child['sku'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $child['price'] ) ? (string) $child['price'] : '' ); ?></td>
							<td><?php echo esc_html( $child_stock ); ?></td>
							<td><?php echo esc_html( isset( $child['status'] ) ? (string) $child['status'] : '' ); ?></td>
							<td><?php echo esc_html( $child_summary ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
			<?php else : ?>
				<tr class="pat-empty-state">
					<td colspan="<?php echo esc_attr( count( $columns ) ); ?>">
						<strong><?php esc_html_e( 'No products to display yet.', 'product-admin-tool' ); ?></strong>
						<p><?php echo esc_html( $empty_message ); ?></p>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
	<p id="pat-grid-caption" class="screen-reader-text">
		<?php esc_html_e( 'Read-only product and variation grid for future spreadsheet editing.', 'product-admin-tool' ); ?>
	</p>
</div>
