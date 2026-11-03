<?php
/**
 * Блок «Быстрые действия».
 *
 * Доступные переменные: $user, $tabs, $block_title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( isset( $block_title ) && '' !== $block_title ) : ?>
	<h3 class="wppa-subtitle"><?php echo esc_html( $block_title ); ?></h3>
<?php else : ?>
	<h3 class="wppa-subtitle"><?php esc_html_e( 'Быстрые действия', 'wp-personal-account' ); ?></h3>
<?php endif; ?>

<div class="wppa-actions">
	<?php if ( isset( $tabs['profile'] ) ) : ?>
		<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( WPPA_Plugin::tab_url( 'profile' ) ); ?>"><?php esc_html_e( 'Редактировать профиль', 'wp-personal-account' ); ?></a>
	<?php endif; ?>
	<?php if ( isset( $tabs['security'] ) ) : ?>
		<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( WPPA_Plugin::tab_url( 'security' ) ); ?>"><?php esc_html_e( 'Сменить пароль', 'wp-personal-account' ); ?></a>
	<?php endif; ?>
	<?php if ( isset( $tabs['team'] ) ) : ?>
		<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>"><?php esc_html_e( 'Моя команда', 'wp-personal-account' ); ?></a>
	<?php endif; ?>
	<a class="wppa-button wppa-button--ghost" data-wppa-confirm-logout href="<?php echo esc_url( wp_logout_url( WPPA_Plugin::account_url() ) ); ?>">
		<?php esc_html_e( 'Выйти', 'wp-personal-account' ); ?>
	</a>
</div>
