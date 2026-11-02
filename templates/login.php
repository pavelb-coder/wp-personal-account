<?php
/**
 * Форма входа.
 *
 * Доступные переменные: $redirect (может отсутствовать), $title (может отсутствовать).
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_redirect = isset( $redirect ) ? $redirect : '';
$wppa_title    = isset( $title ) && '' !== $title ? $title : __( 'Вход в личный кабинет', 'wp-personal-account' );
$wppa_action   = WPPA_Plugin::account_url();
?>
<div class="wppa-form-wrap">
	<h2 class="wppa-title"><?php echo esc_html( $wppa_title ); ?></h2>

	<form class="wppa-form" method="post" action="<?php echo esc_url( $wppa_action ); ?>">
		<?php wp_nonce_field( 'wppa_login', 'wppa_nonce' ); ?>
		<input type="hidden" name="wppa_action" value="login" />
		<?php if ( $wppa_redirect ) : ?>
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( $wppa_redirect ); ?>" />
		<?php endif; ?>

		<p class="wppa-field">
			<label for="wppa-login"><?php esc_html_e( 'Логин или email', 'wp-personal-account' ); ?></label>
			<input type="text" id="wppa-login" name="log" autocomplete="username" required />
		</p>

		<p class="wppa-field">
			<label for="wppa-password"><?php esc_html_e( 'Пароль', 'wp-personal-account' ); ?></label>
			<span class="wppa-password">
				<input type="password" id="wppa-password" name="pwd" autocomplete="current-password" required />
				<button type="button" class="wppa-password__toggle" data-wppa-toggle="wppa-password" aria-label="<?php esc_attr_e( 'Показать пароль', 'wp-personal-account' ); ?>">👁</button>
			</span>
		</p>

		<p class="wppa-field wppa-field--inline">
			<label>
				<input type="checkbox" name="rememberme" value="1" />
				<?php esc_html_e( 'Запомнить меня', 'wp-personal-account' ); ?>
			</label>
			<a href="<?php echo esc_url( wp_lostpassword_url( $wppa_action ) ); ?>"><?php esc_html_e( 'Забыли пароль?', 'wp-personal-account' ); ?></a>
		</p>

		<button type="submit" class="wppa-button wppa-button--primary"><?php esc_html_e( 'Войти', 'wp-personal-account' ); ?></button>
	</form>
</div>
