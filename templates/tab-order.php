<?php
/**
 * Детали заказа.
 *
 * Доступные переменные: $order.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $order ) || ! $order instanceof WC_Order ) {
	return;
}
?>
<p>
	<a class="wppa-link" href="<?php echo esc_url( WPPA_Plugin::tab_url( 'orders' ) ); ?>">← <?php esc_html_e( 'Ко всем заказам', 'wp-personal-account' ); ?></a>
</p>

<h2 class="wppa-title">
	<?php
	printf(
		/* translators: %s: номер заказа */
		esc_html__( 'Заказ №%s', 'wp-personal-account' ),
		esc_html( $order->get_order_number() )
	);
	?>
</h2>

<div class="wppa-order-info">
	<div class="wppa-card">
		<span class="wppa-card__label"><?php esc_html_e( 'Дата', 'wp-personal-account' ); ?></span>
		<span class="wppa-card__value wppa-card__value--small"><?php echo esc_html( WPPA_Orders::date( $order ) ); ?></span>
	</div>
	<div class="wppa-card">
		<span class="wppa-card__label"><?php esc_html_e( 'Статус', 'wp-personal-account' ); ?></span>
		<span class="wppa-card__value wppa-card__value--small"><?php echo esc_html( WPPA_Orders::status_label( $order ) ); ?></span>
	</div>
	<div class="wppa-card">
		<span class="wppa-card__label"><?php esc_html_e( 'Итого', 'wp-personal-account' ); ?></span>
		<span class="wppa-card__value wppa-card__value--small"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
	</div>
	<div class="wppa-card">
		<span class="wppa-card__label"><?php esc_html_e( 'Оплата', 'wp-personal-account' ); ?></span>
		<span class="wppa-card__value wppa-card__value--small"><?php echo esc_html( $order->get_payment_method_title() ? $order->get_payment_method_title() : '—' ); ?></span>
	</div>
</div>

<?php if ( $order->needs_payment() ) : ?>
	<p>
		<a class="wppa-button wppa-button--primary" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>">
			<?php esc_html_e( 'Оплатить заказ', 'wp-personal-account' ); ?>
		</a>
	</p>
<?php endif; ?>

<div class="wppa-table-wrap">
	<table class="wppa-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Товар', 'wp-personal-account' ); ?></th>
				<th><?php esc_html_e( 'Кол-во', 'wp-personal-account' ); ?></th>
				<th><?php esc_html_e( 'Сумма', 'wp-personal-account' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $order->get_items() as $wppa_item ) : ?>
				<?php
				$wppa_product = $wppa_item->get_product();
				$wppa_name    = $wppa_item->get_name();
				?>
				<tr>
					<td data-label="<?php esc_attr_e( 'Товар', 'wp-personal-account' ); ?>">
						<?php echo esc_html( $wppa_name ); ?>
						<?php
						$wppa_meta = $wppa_item->get_formatted_meta_data( ' ' );
						if ( $wppa_meta ) {
							echo '<span class="wppa-hint">';
							foreach ( $wppa_meta as $wppa_meta_item ) {
								echo esc_html( $wppa_meta_item->display_key . ': ' . wp_strip_all_tags( $wppa_meta_item->display_value ) ) . '; ';
							}
							echo '</span>';
						}
						?>
					</td>
					<td data-label="<?php esc_attr_e( 'Кол-во', 'wp-personal-account' ); ?>"><?php echo esc_html( (string) $wppa_item->get_quantity() ); ?></td>
					<td data-label="<?php esc_attr_e( 'Сумма', 'wp-personal-account' ); ?>"><?php echo wp_kses_post( wc_price( $wppa_item->get_total(), array( 'currency' => $order->get_currency() ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
		<tfoot>
			<?php foreach ( $order->get_order_item_totals() as $wppa_total ) : ?>
				<tr>
					<th colspan="2"><?php echo wp_kses_post( $wppa_total['label'] ); ?></th>
					<td><?php echo wp_kses_post( $wppa_total['value'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tfoot>
	</table>
</div>

<?php if ( $order->get_formatted_billing_address() ) : ?>
	<h3 class="wppa-subtitle"><?php esc_html_e( 'Адрес плательщика', 'wp-personal-account' ); ?></h3>
	<address class="wppa-address"><?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?></address>
<?php endif; ?>

<?php
$wppa_shipping_address = $order->get_formatted_shipping_address();
if ( $wppa_shipping_address ) :
	?>
	<h3 class="wppa-subtitle"><?php esc_html_e( 'Адрес доставки', 'wp-personal-account' ); ?></h3>
	<address class="wppa-address"><?php echo wp_kses_post( $wppa_shipping_address ); ?></address>
<?php endif; ?>
