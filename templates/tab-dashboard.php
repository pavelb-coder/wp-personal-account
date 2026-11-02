<?php
/**
 * Вкладка «Обзор».
 *
 * Доступные переменные: $user, $tabs.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_orders_available = WPPA_Orders::is_available();
$wppa_orders           = $wppa_orders_available ? WPPA_Orders::get_orders( 1 ) : null;
$wppa_orders_total     = $wppa_orders && isset( $wppa_orders->total ) ? (int) $wppa_orders->total : 0;
$wppa_last_order       = ( $wppa_orders && ! empty( $wppa_orders->orders ) ) ? $wppa_orders->orders[0] : null;
$wppa_registered       = $user->user_registered ? mysql2date( get_option( 'date_format' ), $user->user_registered ) : '—';
?>
<h2 class="wppa-title">
	<?php
	printf(
		/* translators: %s: имя пользователя */
		esc_html__( 'Здравствуйте, %s!', 'wp-personal-account' ),
		esc_html( $user->display_name )
	);
	?>
</h2>

<div class="wppa-grid">
	<div class="wppa-card wppa-card--stat">
		<span class="wppa-card__label"><?php esc_html_e( 'Заказов всего', 'wp-personal-account' ); ?></span>
		<span class="wppa-card__value"><?php echo $wppa_orders_available ? esc_html( (string) $wppa_orders_total ) : '—'; ?></span>
	</div>

	<div class="wppa-card wppa-card--stat">
		<span class="wppa-card__label"><?php esc_html_e( 'Дата регистрации', 'wp-personal-account' ); ?></span>
		<span class="wppa-card__value wppa-card__value--small"><?php echo esc_html( $wppa_registered ); ?></span>
	</div>

	<div class="wppa-card wppa-card--stat">
		<span class="wppa-card__label"><?php esc_html_e( 'Email', 'wp-personal-account' ); ?></span>
		<span class="wppa-card__value wppa-card__value--small"><?php echo esc_html( $user->user_email ); ?></span>
	</div>
</div>

<?php if ( $wppa_last_order ) : ?>
	<h3 class="wppa-subtitle"><?php esc_html_e( 'Последний заказ', 'wp-personal-account' ); ?></h3>
	<div class="wppa-card wppa-order-card">
		<div>
			<p class="wppa-order-card__number">
				<?php
				printf(
					/* translators: %s: номер заказа */
					esc_html__( 'Заказ №%s', 'wp-personal-account' ),
					esc_html( $wppa_last_order->get_order_number() )
				);
				?>
			</p>
			<p class="wppa-order-card__meta">
				<?php echo esc_html( WPPA_Orders::date( $wppa_last_order ) ); ?> ·
				<?php echo esc_html( WPPA_Orders::status_label( $wppa_last_order ) ); ?>
			</p>
		</div>
		<div class="wppa-order-card__side">
			<span class="wppa-amount"><?php echo wp_kses_post( $wppa_last_order->get_formatted_order_total() ); ?></span>
			<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( WPPA_Plugin::tab_url( 'orders', array( 'order' => $wppa_last_order->get_id() ) ) ); ?>">
				<?php esc_html_e( 'Подробнее', 'wp-personal-account' ); ?>
			</a>
		</div>
	</div>
<?php endif; ?>

<h3 class="wppa-subtitle"><?php esc_html_e( 'Быстрые действия', 'wp-personal-account' ); ?></h3>
<div class="wppa-actions">
	<?php if ( isset( $tabs['profile'] ) ) : ?>
		<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( WPPA_Plugin::tab_url( 'profile' ) ); ?>"><?php esc_html_e( 'Редактировать профиль', 'wp-personal-account' ); ?></a>
	<?php endif; ?>
	<?php if ( isset( $tabs['security'] ) ) : ?>
		<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( WPPA_Plugin::tab_url( 'security' ) ); ?>"><?php esc_html_e( 'Сменить пароль', 'wp-personal-account' ); ?></a>
	<?php endif; ?>
	<?php if ( isset( $tabs['orders'] ) ) : ?>
		<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( WPPA_Plugin::tab_url( 'orders' ) ); ?>"><?php esc_html_e( 'Все заказы', 'wp-personal-account' ); ?></a>
	<?php endif; ?>
</div>
