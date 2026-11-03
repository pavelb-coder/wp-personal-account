<?php
/**
 * Форма «Забыли пароль?»: заявка на сброс пароля по логину или email.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wppa-form-wrap">
	<h2 class="wppa-title"><?php esc_html_e( 'Восстановление пароля', 'wp-personal-account' ); ?></h2>

	<p class="wppa-hint wppa-hint--block">
		<?php esc_html_e( 'Укажите логин или email — пришлём ссылку для установки нового пароля. Ссылка действует 24 часа.', 'wp-personal-account' ); ?>
	</p>

	<form class="wppa-form" method="post" action="<?php echo esc_url( WPPA_Plugin::account_url() ); ?>">
		<?php wp_nonce_field( 'wppa_lostpassword', 'wppa_nonce' ); ?>
		<input type="hidden" name="wppa_action" value="lostpassword" />

		<p class="wppa-field">
			<label for="wppa-lost-login"><?php esc_html_e( 'Логин или email', 'wp-personal-account' ); ?><?php echo WPPA_Fields::req_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input type="text" id="wppa-lost-login" name="user_login" autocomplete="username" required />
		</p>

		<button type="submit" class="wppa-button wppa-button--primary">
			<?php esc_html_e( 'Отправить ссылку', 'wp-personal-account' ); ?>
		</button>
	</form>

	<div class="wppa-switch wppa-switch--button">
		<span class="wppa-switch__text"><?php esc_html_e( 'Вспомнили пароль?', 'wp-personal-account' ); ?></span>
		<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( WPPA_Plugin::account_url( array( 'wppa_view' => 'login' ) ) ); ?>">
			<?php esc_html_e( 'Войти', 'wp-personal-account' ); ?>
		</a>
	</div>
</div>
