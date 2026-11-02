<?php
/**
 * Вкладка «Заказы».
 *
 * Доступные переменные: $user, $tabs.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

if ( ! WPPA_Orders::is_available() ) {
	WPPA_Template::render(
		'notice',
		array(
			'type' => 'info',
			'text' => __( 'Для работы раздела заказов требуется активный WooCommerce.', 'wp-personal-account' ),
		)
	);
	return;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- просмотр, не изменяет данные.
$wppa_order_id = isset( $_GET['order'] ) ? absint( wp_unslash( $_GET['order'] ) ) : 0;

if ( $wppa_order_id ) {
	$wppa_order = WPPA_Orders::get_order( $wppa_order_id );

	if ( ! $wppa_order ) {
		WPPA_Template::render(
			'notice',
			array(
				'type' => 'error',
				'text' => __( 'Заказ не найден.', 'wp-personal-account' ),
			)
		);
	} else {
		WPPA_Template::render( 'tab-order', array( 'order' => $wppa_order ) );
	}
	return;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- пагинация.
$wppa_paged = isset( $_GET['wppa_page'] ) ? max( 1, absint( wp_unslash( $_GET['wppa_page'] ) ) ) : 1;
$wppa_list  = WPPA_Orders::get_orders( $wppa_paged );
$wppa_items = ( $wppa_list && ! empty( $wppa_list->orders ) ) ? $wppa_list->orders : array();
?>
<h2 class="wppa-title"><?php esc_html_e( 'Мои заказы', 'wp-personal-account' ); ?></h2>

<?php if ( empty( $wppa_items ) ) : ?>
	<?php
	WPPA_Template::render(
		'notice',
		array(
			'type' => 'info',
			'text' => __( 'Заказов пока нет.', 'wp-personal-account' ),
		)
	);
	?>
<?php else : ?>
	<div class="wppa-table-wrap">
		<table class="wppa-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Заказ', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Дата', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Статус', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Сумма', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Действия', 'wp-personal-account' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $wppa_items as $wppa_order ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Заказ', 'wp-personal-account' ); ?>">
							<?php
							printf(
								/* translators: %s: номер заказа */
								esc_html__( '№%s', 'wp-personal-account' ),
								esc_html( $wppa_order->get_order_number() )
							);
							?>
						</td>
						<td data-label="<?php esc_attr_e( 'Дата', 'wp-personal-account' ); ?>"><?php echo esc_html( WPPA_Orders::date( $wppa_order ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Статус', 'wp-personal-account' ); ?>">
							<span class="wppa-status wppa-status--<?php echo esc_attr( $wppa_order->get_status() ); ?>">
								<?php echo esc_html( WPPA_Orders::status_label( $wppa_order ) ); ?>
							</span>
						</td>
						<td data-label="<?php esc_attr_e( 'Сумма', 'wp-personal-account' ); ?>"><?php echo wp_kses_post( $wppa_order->get_formatted_order_total() ); ?></td>
						<td data-label="<?php esc_attr_e( 'Действия', 'wp-personal-account' ); ?>">
							<a class="wppa-link" href="<?php echo esc_url( WPPA_Plugin::tab_url( 'orders', array( 'order' => $wppa_order->get_id() ) ) ); ?>">
								<?php esc_html_e( 'Подробнее', 'wp-personal-account' ); ?>
							</a>
							<?php if ( $wppa_order->needs_payment() ) : ?>
								<a class="wppa-link" href="<?php echo esc_url( $wppa_order->get_checkout_payment_url() ); ?>">
									<?php esc_html_e( 'Оплатить', 'wp-personal-account' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php if ( $wppa_list && $wppa_list->max_num_pages > 1 ) : ?>
		<nav class="wppa-pagination" aria-label="<?php esc_attr_e( 'Страницы заказов', 'wp-personal-account' ); ?>">
			<?php
			$wppa_links = paginate_links(
				array(
					'base'      => WPPA_Plugin::tab_url( 'orders', array( 'wppa_page' => '%#%' ) ),
					'format'    => '',
					'current'   => $wppa_paged,
					'total'     => (int) $wppa_list->max_num_pages,
					'type'      => 'array',
					'prev_text' => '←',
					'next_text' => '→',
				)
			);

			if ( $wppa_links ) {
				foreach ( $wppa_links as $wppa_link ) {
					echo '<span class="wppa-pagination__item">' . wp_kses_post( $wppa_link ) . '</span>';
				}
			}
			?>
		</nav>
	<?php endif; ?>
<?php endif; ?>
