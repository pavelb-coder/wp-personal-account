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
			<label for="wppa-current-password"><?php esc_html_e( 'Текущий пароль', 'wp-personal-account' ); ?><?php echo WPPA_Fields::req_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input type="password" id="wppa-current-password" name="current_password" autocomplete="current-password" required />
		</p>

		<div class="wppa-row">
			<p class="wppa-field">
				<label for="wppa-new-password"><?php esc_html_e( 'Новый пароль', 'wp-personal-account' ); ?><?php echo WPPA_Fields::req_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
				<span class="wppa-password">
					<input type="password" id="wppa-new-password" name="new_password" minlength="<?php echo esc_attr( (string) WPPA_Plugin::min_password_length() ); ?>" autocomplete="new-password" required data-wppa-password />
					<button type="button" class="wppa-password__toggle" data-wppa-toggle="wppa-new-password" aria-label="<?php esc_attr_e( 'Показать пароль', 'wp-personal-account' ); ?>">👁</button>
				</span>
				<?php WPPA_Password::render_requirements( 'wppa-new-password' ); ?>
			</p>

			<p class="wppa-field">
				<label for="wppa-repeat-password"><?php esc_html_e( 'Повторите пароль', 'wp-personal-account' ); ?><?php echo WPPA_Fields::req_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
				<input type="password" id="wppa-repeat-password" name="repeat_password" minlength="<?php echo esc_attr( (string) WPPA_Plugin::min_password_length() ); ?>" autocomplete="new-password" required data-wppa-password />
			</p>
		</div>

		<button type="submit" class="wppa-button wppa-button--primary"><?php esc_html_e( 'Изменить пароль', 'wp-personal-account' ); ?></button>
	</form>
</section>

