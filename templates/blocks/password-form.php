<?php
/**
 * Блок «Смена пароля».
 *
 * Доступные переменные: $user, $tabs, $block_title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="wppa-card">
	<h3 class="wppa-subtitle"><?php esc_html_e( 'Смена пароля', 'wp-personal-account' ); ?></h3>

	<form class="wppa-form" method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'security' ) ); ?>">
		<?php wp_nonce_field( 'wppa_password', 'wppa_nonce' ); ?>
		<input type="hidden" name="wppa_action" value="password" />

		<p class="wppa-field">
			<label for="wppa-current-password"><?php esc_html_e( 'Текущий пароль', 'wp-personal-account' ); ?></label>
			<input type="password" id="wppa-current-password" name="current_password" autocomplete="current-password" required />
		</p>

		<div class="wppa-row">
			<p class="wppa-field">
				<label for="wppa-new-password"><?php esc_html_e( 'Новый пароль', 'wp-personal-account' ); ?></label>
				<input type="password" id="wppa-new-password" name="new_password" minlength="8" autocomplete="new-password" required data-wppa-password />
			</p>

			<p class="wppa-field">
				<label for="wppa-repeat-password"><?php esc_html_e( 'Повторите пароль', 'wp-personal-account' ); ?></label>
				<input type="password" id="wppa-repeat-password" name="repeat_password" minlength="8" autocomplete="new-password" required data-wppa-password />
			</p>
		</div>

		<button type="submit" class="wppa-button wppa-button--primary"><?php esc_html_e( 'Изменить пароль', 'wp-personal-account' ); ?></button>
	</form>
</section>

