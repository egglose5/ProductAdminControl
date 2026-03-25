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
?>
<?php if ( $has_rows ) : ?>
	<?php foreach ( $rows as $child ) : ?>
		<?php
		$child_id_raw  = isset( $child['id'] ) ? (string) $child['id'] : '';
		$child_temp_id = isset( $child['temp_id'] ) ? (string) $child['temp_id'] : '';
		$is_generated  = ! empty( $child['is_generated'] );
		$child_id      = '' !== $child_id_raw ? $child_id_raw : $child_temp_id;
		$child_stock   = isset( $child['stock_quantity'] ) ? (string) $child['stock_quantity'] : ( isset( $child['stock'] ) ? (string) $child['stock'] : '' );
		$child_summary = isset( $child['attribute_summary'] ) ? (string) $child['attribute_summary'] : '';
		$child_label   = '' !== $child_summary ? $child_summary : ( isset( $child['title'] ) ? (string) $child['title'] : __( 'Variation', 'product-admin-tool' ) );
		$child_status  = isset( $child['status'] ) ? (string) $child['status'] : 'draft';
		$child_regular_price = isset( $child['regular_price'] ) ? (string) $child['regular_price'] : ( isset( $child['price'] ) ? (string) $child['price'] : '' );
		$child_sale_price = isset( $child['sale_price'] ) ? (string) $child['sale_price'] : '';
		$child_sku = isset( $child['sku'] ) ? (string) $child['sku'] : '';
		$child_menu_order = isset( $child['menu_order'] ) ? (string) $child['menu_order'] : '0';
		?>
		<tr class="pat-row pat-child-row is-child-row is-hidden<?php echo $is_generated ? ' is-generated-row' : ''; ?>" data-pat-parent="<?php echo esc_attr( $parent_dom_id ); ?>" data-pat-row-id="<?php echo esc_attr( $child_id ); ?>" data-pat-row-type="variation" data-pat-generated="<?php echo $is_generated ? 'true' : 'false'; ?>" tabindex="0" aria-selected="false" hidden>
			<td class="check-column">
				<input type="checkbox" data-pat-select-row="true" aria-label="<?php echo esc_attr( sprintf( __( 'Select variation row %s', 'product-admin-tool' ), $child_id ) ); ?>" />
			</td>
			<td class="pat-cell-product">
				<div class="pat-row-label pat-row-label-wrap pat-child-label-wrap">
					<span class="pat-child-indicator" aria-hidden="true">-</span>
					<strong class="pat-product-name pat-row-name"><?php echo esc_html( $child_label ); ?></strong>
				</div>
				<div class="pat-row-meta">
					<span class="pat-row-id"><?php echo esc_html( sprintf( __( 'ID: %s', 'product-admin-tool' ), $child_id ) ); ?></span>
					<?php if ( '' !== $child_summary ) : ?>
						<span class="pat-row-attributes"><?php echo esc_html( $child_summary ); ?></span>
					<?php endif; ?>
				</div>
			</td>
			<td>&mdash;</td>
			<td><?php esc_html_e( 'variation', 'product-admin-tool' ); ?></td>
			<td>
				<?php if ( $is_generated ) : ?>
					<span class="pat-row-preview-text"><?php echo '' !== $child_sku ? esc_html( $child_sku ) : esc_html__( '(generated)', 'product-admin-tool' ); ?></span>
				<?php else : ?>
					<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-variation-sku-' . $child_id ); ?>"><?php esc_html_e( 'Variation SKU', 'product-admin-tool' ); ?></label>
					<input
						id="<?php echo esc_attr( 'pat-variation-sku-' . $child_id ); ?>"
						class="pat-inline-field pat-inline-sku"
						type="text"
						value="<?php echo esc_attr( $child_sku ); ?>"
						data-pat-row-id="<?php echo esc_attr( $child_id ); ?>"
						data-pat-row-type="variation"
						data-pat-field="sku"
						data-pat-original-value="<?php echo esc_attr( $child_sku ); ?>"
					/>
				<?php endif; ?>
			</td>
			<td class="pat-price-cell">
				<?php if ( $is_generated ) : ?>
					<div class="pat-inline-field-group">
						<span class="pat-inline-label"><?php esc_html_e( 'Regular', 'product-admin-tool' ); ?></span>
						<span class="pat-row-preview-text"><?php echo esc_html( $child_regular_price ); ?></span>
					</div>
					<div class="pat-inline-field-group">
						<span class="pat-inline-label"><?php esc_html_e( 'Sale', 'product-admin-tool' ); ?></span>
						<span class="pat-row-preview-text"><?php echo esc_html( $child_sale_price ); ?></span>
					</div>
				<?php else : ?>
				<div class="pat-inline-field-group">
					<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-variation-regular-price-' . $child_id ); ?>"><?php esc_html_e( 'Variation regular price', 'product-admin-tool' ); ?></label>
					<input
						id="<?php echo esc_attr( 'pat-variation-regular-price-' . $child_id ); ?>"
						class="pat-inline-field pat-inline-price"
						type="number"
						step="0.01"
						inputmode="decimal"
						value="<?php echo esc_attr( $child_regular_price ); ?>"
						data-pat-row-id="<?php echo esc_attr( $child_id ); ?>"
						data-pat-row-type="variation"
						data-pat-field="regular_price"
						data-pat-original-value="<?php echo esc_attr( $child_regular_price ); ?>"
					/>
					<span class="pat-inline-label"><?php esc_html_e( 'Regular', 'product-admin-tool' ); ?></span>
				</div>
				<div class="pat-inline-field-group">
					<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-variation-sale-price-' . $child_id ); ?>"><?php esc_html_e( 'Variation sale price', 'product-admin-tool' ); ?></label>
					<input
						id="<?php echo esc_attr( 'pat-variation-sale-price-' . $child_id ); ?>"
						class="pat-inline-field pat-inline-price"
						type="number"
						step="0.01"
						inputmode="decimal"
						value="<?php echo esc_attr( $child_sale_price ); ?>"
						data-pat-row-id="<?php echo esc_attr( $child_id ); ?>"
						data-pat-row-type="variation"
						data-pat-field="sale_price"
						data-pat-original-value="<?php echo esc_attr( $child_sale_price ); ?>"
					/>
					<span class="pat-inline-label"><?php esc_html_e( 'Sale', 'product-admin-tool' ); ?></span>
				</div>
				<?php endif; ?>
			</td>
			<td>
				<?php if ( $is_generated ) : ?>
					<span class="pat-row-preview-text"><?php echo esc_html( $child_stock ); ?></span>
				<?php else : ?>
					<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-variation-stock-' . $child_id ); ?>"><?php esc_html_e( 'Variation stock quantity', 'product-admin-tool' ); ?></label>
					<input
						id="<?php echo esc_attr( 'pat-variation-stock-' . $child_id ); ?>"
						class="pat-inline-field pat-inline-stock"
						type="number"
						step="1"
						inputmode="numeric"
						value="<?php echo esc_attr( $child_stock ); ?>"
						data-pat-row-id="<?php echo esc_attr( $child_id ); ?>"
						data-pat-row-type="variation"
						data-pat-field="stock_quantity"
						data-pat-original-value="<?php echo esc_attr( $child_stock ); ?>"
					/>
				<?php endif; ?>
			</td>
			<td>
				<?php if ( $is_generated ) : ?>
					<span class="pat-row-preview-text"><?php echo esc_html( $status_options[ $child_status ] ?? $child_status ); ?></span>
				<?php else : ?>
					<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-variation-status-' . $child_id ); ?>"><?php esc_html_e( 'Variation status', 'product-admin-tool' ); ?></label>
					<select
						id="<?php echo esc_attr( 'pat-variation-status-' . $child_id ); ?>"
						class="pat-inline-field pat-inline-status"
						data-pat-row-id="<?php echo esc_attr( $child_id ); ?>"
						data-pat-row-type="variation"
						data-pat-field="status"
						data-pat-original-value="<?php echo esc_attr( $child_status ); ?>"
					>
						<?php foreach ( $status_options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $child_status, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</td>
			<td>&mdash;</td>
			<td>
				<?php if ( $is_generated ) : ?>
					<div class="pat-inline-field-group">
						<span class="pat-inline-label"><?php esc_html_e( 'Menu Order', 'product-admin-tool' ); ?></span>
						<span class="pat-row-preview-text"><?php echo esc_html( $child_menu_order ); ?></span>
					</div>
				<?php else : ?>
					<div class="pat-inline-field-group">
						<label class="screen-reader-text" for="<?php echo esc_attr( 'pat-variation-menu-order-' . $child_id ); ?>"><?php esc_html_e( 'Variation menu order', 'product-admin-tool' ); ?></label>
						<input
							id="<?php echo esc_attr( 'pat-variation-menu-order-' . $child_id ); ?>"
							class="pat-inline-field pat-inline-menu-order"
							type="number"
							step="1"
							inputmode="numeric"
							value="<?php echo esc_attr( $child_menu_order ); ?>"
							data-pat-row-id="<?php echo esc_attr( $child_id ); ?>"
							data-pat-row-type="variation"
							data-pat-field="menu_order"
							data-pat-original-value="<?php echo esc_attr( $child_menu_order ); ?>"
						/>
						<span class="pat-inline-label"><?php esc_html_e( 'Menu Order', 'product-admin-tool' ); ?></span>
					</div>
				<?php endif; ?>
				<div class="pat-row-meta">
					<?php if ( $is_generated ) : ?>
						<span class="pat-badge"><?php esc_html_e( 'Generated preview', 'product-admin-tool' ); ?></span>
					<?php endif; ?>
					<span class="pat-row-attributes"><?php echo esc_html( $child_summary ); ?></span>
				</div>
			</td>
			<td class="pat-row-state">
				<span class="pat-row-state-message" data-pat-row-state-message></span>
			</td>
		</tr>
	<?php endforeach; ?>
<?php elseif ( '' !== $empty_message ) : ?>
	<tr class="pat-empty-state">
		<td colspan="11">
			<strong><?php esc_html_e( 'No variations to display yet.', 'product-admin-tool' ); ?></strong>
			<p><?php echo esc_html( $empty_message ); ?></p>
		</td>
	</tr>
<?php endif; ?>
