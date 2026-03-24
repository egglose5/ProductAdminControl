<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_rows = ! empty( $rows );
?>
<?php if ( $has_rows ) : ?>
	<?php foreach ( $rows as $child ) : ?>
		<?php
		$child_id      = isset( $child['id'] ) ? (int) $child['id'] : 0;
		$child_stock   = isset( $child['stock'] ) ? (string) $child['stock'] : '';
		$child_summary = isset( $child['attribute_summary'] ) ? (string) $child['attribute_summary'] : '';
		$child_label   = '' !== $child_summary ? $child_summary : ( isset( $child['title'] ) ? (string) $child['title'] : __( 'Variation', 'product-admin-tool' ) );
		?>
		<tr class="pat-row pat-child-row is-child-row is-hidden" data-pat-parent="<?php echo esc_attr( $parent_dom_id ); ?>" data-pat-row-id="<?php echo esc_attr( $child_id ); ?>" data-pat-row-type="variation" tabindex="0" aria-selected="false" hidden>
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
			<td><?php esc_html_e( 'variation', 'product-admin-tool' ); ?></td>
			<td><?php echo esc_html( isset( $child['sku'] ) ? (string) $child['sku'] : '' ); ?></td>
			<td><?php echo esc_html( isset( $child['price'] ) ? (string) $child['price'] : '' ); ?></td>
			<td><?php echo esc_html( $child_stock ); ?></td>
			<td><?php echo esc_html( isset( $child['status'] ) ? (string) $child['status'] : '' ); ?></td>
			<td><?php echo esc_html( $child_summary ); ?></td>
			<td class="pat-row-state">
				<span class="pat-row-state-message" data-pat-row-state-message></span>
			</td>
		</tr>
	<?php endforeach; ?>
<?php elseif ( '' !== $empty_message ) : ?>
	<tr class="pat-empty-state">
		<td colspan="9">
			<strong><?php esc_html_e( 'No variations to display yet.', 'product-admin-tool' ); ?></strong>
			<p><?php echo esc_html( $empty_message ); ?></p>
		</td>
	</tr>
<?php endif; ?>
