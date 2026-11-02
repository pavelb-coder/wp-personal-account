<?php
/**
 * Заглушка для форм входа/регистрации у авторизованного пользователя.
 *
 * Доступные переменные: $message.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wppa-notice wppa-notice--info">
	<?php echo esc_html( isset( $message ) ? $message : __( 'Вы уже авторизованы.', 'wp-personal-account' ) ); ?>
	<a href="<?php echo esc_url( WPPA_Plugin::tab_url( 'dashboard' ) ); ?>"><?php esc_html_e( 'Перейти в личный кабинет', 'wp-personal-account' ); ?></a>
</div>
