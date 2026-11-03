<?php
/**
 * Блок «Сессия и выход».
 *
 * Доступные переменные: $user, $tabs, $block_title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="wppa-card">
	<h3 class="wppa-subtitle"><?php esc_html_e( 'Сессия', 'wp-personal-account' ); ?></h3>
	<p class="wppa-hint"><?php esc_html_e( 'Выход завершит текущую сессию на этом устройстве.', 'wp-personal-account' ); ?></p>
	<a class="wppa-button wppa-button--ghost" data-wppa-confirm-logout href="<?php echo esc_url( wp_logout_url( WPPA_Plugin::account_url() ) ); ?>">
		<?php esc_html_e( 'Выйти из аккаунта', 'wp-personal-account' ); ?>
	</a>
</section>
