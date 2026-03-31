<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_rows = ! empty( $rows );
$status_options = array(
	'publish' => __( 'Published', 'product-admin-tool' ),
	'draft'   => __( 'Draft', 'product-admin-tool' ),
	'pending' => __( 'Pending review', 'product-admin-tool' ),
	'private' => __( 'Private', 'product-admin-tool' ),
);
$shipping_class_options = array(
	0 => __( 'No shipping class', 'product-admin-tool' ),
);

if ( taxonomy_exists( 'product_shipping_class' ) ) {
	$shipping_class_terms = get_terms(
		array(
			'taxonomy'   => 'product_shipping_class',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	if ( ! is_wp_error( $shipping_class_terms ) && ! empty( $shipping_class_terms ) ) {
		foreach ( $shipping_class_terms as $shipping_class_term ) {
			if ( ! $shipping_class_term instanceof WP_Term ) {
				continue;
			}

			$shipping_class_options[ (int) $shipping_class_term->term_id ] = (string) $shipping_class_term->name;
		}
	}
}
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
					$row_id              = isset( $row['id'] ) ? (int) $row['id'] : 0;
					$row_dom_id          = 'pat-row-' . $row_id;
					$children            = isset( $row['child_rows'] ) && is_array( $row['child_rows'] ) ? $row['child_rows'] : array();
					$can_expand          = ! empty( $row['has_children'] );
					$product_name        = isset( $row['title'] ) ? (string) $row['title'] : '';
					$product_type        = isset( $row['type'] ) ? (string) $row['type'] : 'product';
					$categories          = isset( $row['categories'] ) && is_array( $row['categories'] ) ? implode( ', ', $row['categories'] ) : '';
					$stock_display       = '';
					$stock_input_value   = isset( $row['stock_quantity'] ) && null !== $row['stock_quantity'] ? (string) $row['stock_quantity'] : '';
					$modified_label      = '';
					$regular_price_value = isset( $row['regular_price'] ) ? (string) $row['regular_price'] : '';
					$sale_price_value    = isset( $row['sale_price'] ) ? (string) $row['sale_price'] : '';
					$sku_value           = isset( $row['sku'] ) ? (string) $row['sku'] : '';
					$status_value        = isset( $row['status'] ) ? (string) $row['status'] : '';
					$weight_value        = isset( $row['weight'] ) ? (string) $row['weight'] : '';
					$length_value        = isset( $row['length'] ) ? (string) $row['length'] : '';
					$width_value         = isset( $row['width'] ) ? (string) $row['width'] : '';
					$height_value        = isset( $row['height'] ) ? (string) $row['height'] : '';
					$shipping_class_id_value = isset( $row['shipping_class_id'] ) ? (string) absint( $row['shipping_class_id'] ) : '0';
					$package_type_value  = isset( $row['package_type'] ) ? (string) $row['package_type'] : '';
					$menu_order_value    = isset( $row['menu_order'] ) ? (string) $row['menu_order'] : '0';
					$row_state_label     = '';

					if ( isset( $row['stock_quantity'] ) && null !== $row['stock_quantity'] && '' !== (string) $row['stock_quantity'] ) {
						$stock_display = (string) $row['stock_quantity'];
					} elseif ( isset( $row['stock_status'] ) ) {
						$stock_display = (string) $row['stock_status'];
					}

					if ( isset( $row['modified'] ) && '' !== (string) $row['modified'] ) {
						$modified_label = mysql2date( get_option( 'date_format' ), (string) $row['modified'] );
					}
					?>
					<tr class="pat-row pat-parent-row" id="<?php echo esc_attr( $row_dom_id ); ?>" data-pat-row-id="<?php echo esc_attr( $row_id ); ?>" data-pat-row-type="product" data-pat-children-lazy="<?php echo ! empty( $row['children_lazy'] ) ? 'true' : 'false'; ?>" data-pat-child-count="<?php echo esc_attr( isset( $row['child_count'] ) ? (int) $row['child_count'] : count( $children ) ); ?>" tabindex="0" aria-selected="false">
						<td class="check-column">
							<input type="checkbox" data-pat-select-row="true" aria-label="<?php echo esc_attr( sprintf( __( 'Select product row %d', 'product-admin-tool' ), $row_id ) ); ?>" />
						</td>
						<td class="pat-cell-product">
							<div class="pat-row-label pat-row-label-wrap">
								<?php if ( $can_expand ) : ?>
									<button
										type="button"
										class="button-link pat-row-toggle"
										aria-expanded="false"
										data-pat-toggle-children="true"
										data-pat-parent-id="<?php echo esc_attr( $row_id ); ?>"
										data-pat-target="<?php echo esc_attr( $row_dom_id ); ?>"
									>
										<span class="screen-reader-text"><?php esc_html_e( 'Toggle variations for', 'product-admin-tool' ); ?></span>
										<span aria-hidden="true">+</span>
									</button>
								<?php else : ?>
									<span class="pat-row-toggle pat-row-toggle-spacer" aria-hidden="true"></span>
								<?php endif; ?>

								<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-title-' . $row_id ); ?>"><?php esc_html_e( 'Product title', 'product-admin-tool' ); ?></label>
								<input
									id="<?php echo esc_attr( 'pat-title-' . $row_id ); ?>"
									class="pat-inline-field pat-inline-title"
									type="text"
									value="<?php echo esc_attr( $product_name ); ?>"
									data-pat-row-id="<?php echo esc_attr( $row_id ); ?>"
									data-pat-row-type="product"
									data-pat-field="title"
									data-pat-original-value="<?php echo esc_attr( $product_name ); ?>"
								/>

								<?php if ( $can_expand ) : ?>
									<span class="pat-badge"><?php esc_html_e( 'Variations available', 'product-admin-tool' ); ?></span>
								<?php endif; ?>
								<span class="pat-row-status" data-pat-variation-status></span>
							</div>
							<div class="pat-row-meta">
								<span class="pat-row-id"><?php echo esc_html( sprintf( __( 'ID: %d', 'product-admin-tool' ), $row_id ) ); ?></span>
								<?php if ( '' !== $categories ) : ?>
									<span class="pat-row-family"><?php echo esc_html( $categories ); ?></span>
								<?php endif; ?>
							</div>
						</td>
						<td><?php echo '' !== $categories ? esc_html( $categories ) : '-'; ?></td>
						<td><?php echo esc_html( $product_type ); ?></td>
						<td>
							<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-sku-' . $row_id ); ?>"><?php esc_html_e( 'SKU', 'product-admin-tool' ); ?></label>
							<input
								id="<?php echo esc_attr( 'pat-sku-' . $row_id ); ?>"
								class="pat-inline-field pat-inline-sku"
								type="text"
								value="<?php echo esc_attr( $sku_value ); ?>"
								data-pat-row-id="<?php echo esc_attr( $row_id ); ?>"
								data-pat-row-type="product"
								data-pat-field="sku"
								data-pat-original-value="<?php echo esc_attr( $sku_value ); ?>"
							/>
						</td>
						<td class="pat-price-cell">
							<div class="pat-inline-field-group">
								<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-regular-price-' . $row_id ); ?>"><?php esc_html_e( 'Regular price', 'product-admin-tool' ); ?></label>
								<input
									id="<?php echo esc_attr( 'pat-regular-price-' . $row_id ); ?>"
									class="pat-inline-field pat-inline-price"
									type="number"
									step="0.01"
									inputmode="decimal"
									value="<?php echo esc_attr( $regular_price_value ); ?>"
									data-pat-row-id="<?php echo esc_attr( $row_id ); ?>"
									data-pat-row-type="product"
									data-pat-field="regular_price"
									data-pat-original-value="<?php echo esc_attr( $regular_price_value ); ?>"
								/>
								<span class="pat-inline-label"><?php esc_html_e( 'Regular', 'product-admin-tool' ); ?></span>
							</div>
							<div class="pat-inline-field-group">
								<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-sale-price-' . $row_id ); ?>"><?php esc_html_e( 'Sale price', 'product-admin-tool' ); ?></label>
								<input
									id="<?php echo esc_attr( 'pat-sale-price-' . $row_id ); ?>"
									class="pat-inline-field pat-inline-price"
									type="number"
									step="0.01"
									inputmode="decimal"
									value="<?php echo esc_attr( $sale_price_value ); ?>"
									data-pat-row-id="<?php echo esc_attr( $row_id ); ?>"
									data-pat-row-type="product"
									data-pat-field="sale_price"
									data-pat-original-value="<?php echo esc_attr( $sale_price_value ); ?>"
								/>
								<span class="pat-inline-label"><?php esc_html_e( 'Sale', 'product-admin-tool' ); ?></span>
							</div>
						</td>
						<td>
							<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-stock-' . $row_id ); ?>"><?php esc_html_e( 'Stock quantity', 'product-admin-tool' ); ?></label>
							<input
								id="<?php echo esc_attr( 'pat-stock-' . $row_id ); ?>"
								class="pat-inline-field pat-inline-stock"
								type="number"
								step="1"
								inputmode="numeric"
								value="<?php echo esc_attr( $stock_input_value ); ?>"
								data-pat-row-id="<?php echo esc_attr( $row_id ); ?>"
								data-pat-row-type="product"
								data-pat-field="stock_quantity"
								data-pat-original-value="<?php echo esc_attr( $stock_input_value ); ?>"
							/>
						</td>
						<td>
							<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-status-' . $row_id ); ?>"><?php esc_html_e( 'Status', 'product-admin-tool' ); ?></label>
							<select
								id="<?php echo esc_attr( 'pat-status-' . $row_id ); ?>"
								class="pat-inline-field pat-inline-status"
								data-pat-row-id="<?php echo esc_attr( $row_id ); ?>"
								data-pat-row-type="product"
								data-pat-field="status"
								data-pat-original-value="<?php echo esc_attr( $status_value ); ?>"
							>
								<?php foreach ( $status_options as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status_value, $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<div class="pat-inline-field-group">
								<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-weight-' . $row_id ); ?>"><?php esc_html_e( 'Weight', 'product-admin-tool' ); ?></label>
								<input
									id="<?php echo esc_attr( 'pat-weight-' . $row_id ); ?>"
									class="pat-inline-field pat-inline-price"
									type="number"
									step="0.01"
									inputmode="decimal"
									value="<?php echo esc_attr( $weight_value ); ?>"
									data-pat-row-id="<?php echo esc_attr( $row_id ); ?>"
									data-pat-row-type="product"
									data-pat-field="weight"
									data-pat-original-value="<?php echo esc_attr( $weight_value ); ?>"
								/>
								<span class="pat-inline-label"><?php esc_html_e( 'Weight', 'product-admin-tool' ); ?></span>
							</div>
							<div class="pat-inline-field-group">
								<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-length-' . $row_id ); ?>"><?php esc_html_e( 'Length', 'product-admin-tool' ); ?></label>
								<input
									id="<?php echo esc_attr( 'pat-length-' . $row_id ); ?>"
									class="pat-inline-field pat-inline-price"
									type="number"
									step="0.01"
									inputmode="decimal"
									value="<?php echo esc_attr( $length_value ); ?>"
									data-pat-row-id="<?php echo esc_attr( $row_id ); ?>"
									data-pat-row-type="product"
									data-pat-field="length"
									data-pat-original-value="<?php echo esc_attr( $length_value ); ?>"
								/>
								<span class="pat-inline-label"><?php esc_html_e( 'L', 'product-admin-tool' ); ?></span>
							</div>
							<div class="pat-inline-field-group">
								<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-width-' . $row_id ); ?>"><?php esc_html_e( 'Width', 'product-admin-tool' ); ?></label>
								<input
									id="<?php echo esc_attr( 'pat-width-' . $row_id ); ?>"
									class="pat-inline-field pat-inline-price"
									type="number"
									step="0.01"
									inputmode="decimal"
									value="<?php echo esc_attr( $width_value ); ?>"
									data-pat-row-id="<?php echo esc_attr( $row_id ); ?>"
									data-pat-row-type="product"
									data-pat-field="width"
									data-pat-original-value="<?php echo esc_attr( $width_value ); ?>"
								/>
								<span class="pat-inline-label"><?php esc_html_e( 'W', 'product-admin-tool' ); ?></span>
							</div>
							<div class="pat-inline-field-group">
								<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-height-' . $row_id ); ?>"><?php esc_html_e( 'Height', 'product-admin-tool' ); ?></label>
								<input
									id="<?php echo esc_attr( 'pat-height-' . $row_id ); ?>"
									class="pat-inline-field pat-inline-price"
									type="number"
									step="0.01"
									inputmode="decimal"
									value="<?php echo esc_attr( $height_value ); ?>"
									data-pat-row-id="<?php echo esc_attr( $row_id ); ?>"
									data-pat-row-type="product"
									data-pat-field="height"
									data-pat-original-value="<?php echo esc_attr( $height_value ); ?>"
								/>
								<span class="pat-inline-label"><?php esc_html_e( 'H', 'product-admin-tool' ); ?></span>
							</div>
							<div class="pat-inline-field-group">
								<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-package-type-' . $row_id ); ?>"><?php esc_html_e( 'Package type', 'product-admin-tool' ); ?></label>
								<input
									id="<?php echo esc_attr( 'pat-package-type-' . $row_id ); ?>"
									class="pat-inline-field"
									type="text"
									value="<?php echo esc_attr( $package_type_value ); ?>"
									data-pat-row-id="<?php echo esc_attr( $row_id ); ?>"
									data-pat-row-type="product"
									data-pat-field="package_type"
									data-pat-original-value="<?php echo esc_attr( $package_type_value ); ?>"
								/>
								<span class="pat-inline-label"><?php esc_html_e( 'Package', 'product-admin-tool' ); ?></span>
							</div>
							<div class="pat-inline-field-group">
								<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-shipping-class-' . $row_id ); ?>"><?php esc_html_e( 'Shipping class', 'product-admin-tool' ); ?></label>
								<select
									id="<?php echo esc_attr( 'pat-shipping-class-' . $row_id ); ?>"
									class="pat-inline-field pat-inline-status"
									data-pat-row-id="<?php echo esc_attr( $row_id ); ?>"
									data-pat-row-type="product"
									data-pat-field="shipping_class_id"
									data-pat-original-value="<?php echo esc_attr( $shipping_class_id_value ); ?>"
								>
									<?php foreach ( $shipping_class_options as $shipping_class_id => $shipping_class_label ) : ?>
										<option value="<?php echo esc_attr( (string) $shipping_class_id ); ?>" <?php selected( $shipping_class_id_value, (string) $shipping_class_id ); ?>>
											<?php echo esc_html( $shipping_class_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<span class="pat-inline-label"><?php esc_html_e( 'Class', 'product-admin-tool' ); ?></span>
							</div>
						</td>
						<td>
							<div class="pat-inline-field-group">
								<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-menu-order-' . $row_id ); ?>"><?php esc_html_e( 'Menu order', 'product-admin-tool' ); ?></label>
								<input
									id="<?php echo esc_attr( 'pat-menu-order-' . $row_id ); ?>"
									class="pat-inline-field pat-inline-menu-order"
									type="number"
									step="1"
									inputmode="numeric"
									value="<?php echo esc_attr( $menu_order_value ); ?>"
									data-pat-row-id="<?php echo esc_attr( $row_id ); ?>"
									data-pat-row-type="product"
									data-pat-field="menu_order"
									data-pat-original-value="<?php echo esc_attr( $menu_order_value ); ?>"
								/>
								<span class="pat-inline-label"><?php esc_html_e( 'Menu Order', 'product-admin-tool' ); ?></span>
							</div>
							<?php if ( '' !== $modified_label ) : ?>
								<div class="pat-row-meta">
									<span class="pat-row-updated"><?php echo esc_html( $modified_label ); ?></span>
								</div>
							<?php endif; ?>
						</td>
						<td class="pat-row-state" data-pat-row-state>
							<span class="pat-row-state-message" data-pat-row-state-message><?php echo esc_html( $row_state_label ); ?></span>
						</td>
					</tr>

					<?php foreach ( $children as $child ) : ?>
						<?php
						$child_id      = isset( $child['id'] ) ? (int) $child['id'] : 0;
						$child_stock   = isset( $child['stock'] ) ? (string) $child['stock'] : '';
						$child_summary = isset( $child['attribute_summary'] ) ? (string) $child['attribute_summary'] : '';
						$child_label   = '' !== $child_summary ? $child_summary : ( isset( $child['title'] ) ? (string) $child['title'] : __( 'Variation', 'product-admin-tool' ) );
						?>
						<tr class="pat-row pat-child-row is-child-row is-hidden" data-pat-parent="<?php echo esc_attr( $row_dom_id ); ?>" data-pat-row-id="<?php echo esc_attr( $child_id ); ?>" data-pat-row-type="variation" tabindex="0" aria-selected="false" hidden>
							<td class="check-column">
								<input type="checkbox" data-pat-select-row="true" aria-label="<?php echo esc_attr( sprintf( __( 'Select variation row %d', 'product-admin-tool' ), $child_id ) ); ?>" />
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
							<td>&mdash;</td>
							<td><?php esc_html_e( 'variation', 'product-admin-tool' ); ?></td>
							<td><?php echo esc_html( isset( $child['sku'] ) ? (string) $child['sku'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $child['price'] ) ? (string) $child['price'] : '' ); ?></td>
							<td><?php echo esc_html( $child_stock ); ?></td>
							<td><?php echo esc_html( isset( $child['status'] ) ? (string) $child['status'] : '' ); ?></td>
							<td>&mdash;</td>
							<td><?php echo esc_html( $child_summary ); ?></td>
							<td class="pat-row-state">
								<span class="pat-row-state-message" data-pat-row-state-message></span>
							</td>
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
		<?php esc_html_e( 'Product grid with inline parent editing and lazy-loaded variation rows.', 'product-admin-tool' ); ?>
	</p>
</div>
