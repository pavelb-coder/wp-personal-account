<?php
/**
 * Форма регистрации.
 *
 * Доступные переменные: $title (может отсутствовать).
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_title  = isset( $title ) && '' !== $title ? $title : __( 'Регистрация', 'wp-personal-account' );
$wppa_window = WPPA_Plugin::registration_window();

// Вне окна доступности вместо формы показываем заглушку с таймером.
if ( 'open' !== $wppa_window['state'] ) {
	WPPA_Template::render(
		'registration-locked',
		array(
			'window' => $wppa_window,
			'title'  => $wppa_title,
		)
	);

	return;
}
?>
<div class="wppa-form-wrap">
	<h2 class="wppa-title"><?php echo esc_html( $wppa_title ); ?></h2>

	<form class="wppa-form" method="post" action="<?php echo esc_url( WPPA_Plugin::account_url() ); ?>">
		<?php wp_nonce_field( 'wppa_register', 'wppa_nonce' ); ?>
		<input type="hidden" name="wppa_action" value="register" />
		<?php $wppa_redirect = WPPA_Plugin::requested_auth_redirect(); ?>
		<?php if ( $wppa_redirect ) : ?>
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( $wppa_redirect ); ?>" />
		<?php endif; ?>
		<?php $wppa_form_url = WPPA_Plugin::requested_auth_form(); ?>
		<?php if ( $wppa_form_url ) : ?>
			<input type="hidden" name="wppa_form_url" value="<?php echo esc_url( $wppa_form_url ); ?>" />
		<?php endif; ?>

		<?php WPPA_Fields::render( 'register' ); ?>

		<p class="wppa-field">
			<label for="wppa-email"><?php esc_html_e( 'Email', 'wp-personal-account' ); ?><?php echo WPPA_Fields::req_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input type="email" id="wppa-email" name="email" autocomplete="email" required />
		</p>

		<p class="wppa-field">
			<label for="wppa-reg-password"><?php esc_html_e( 'Пароль', 'wp-personal-account' ); ?><?php echo WPPA_Fields::req_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<span class="wppa-password">
				<input type="password" id="wppa-reg-password" name="password" minlength="<?php echo esc_attr( (string) WPPA_Plugin::min_password_length() ); ?>" autocomplete="new-password" required data-wppa-password />
				<button type="button" class="wppa-password__toggle" data-wppa-toggle="wppa-reg-password" aria-label="<?php esc_attr_e( 'Показать пароль', 'wp-personal-account' ); ?>">👁</button>
			</span>
			<?php WPPA_Password::render_requirements( 'wppa-reg-password' ); ?>
		</p>

		<p class="wppa-field">
			<label for="wppa-reg-password2"><?php esc_html_e( 'Пароль ещё раз', 'wp-personal-account' ); ?><?php echo WPPA_Fields::req_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input type="password" id="wppa-reg-password2" name="password_confirm" minlength="<?php echo esc_attr( (string) WPPA_Plugin::min_password_length() ); ?>" autocomplete="new-password" required data-wppa-password />
		</p>

		<p class="wppa-field wppa-field--check">
			<label>
				<input type="checkbox" name="consent" value="1" required />
				<?php esc_html_e( 'Согласен на обработку персональных данных', 'wp-personal-account' ); ?><?php echo WPPA_Fields::req_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</label>
		</p>

		<p class="wppa-hint wppa-hint--block">
			<?php esc_html_e( 'Поля со звёздочкой (*) обязательны — без email и пароля аккаунт не создать. Данные видны только вам, капитану команды и администратору сайта.', 'wp-personal-account' ); ?>
		</p>

		<button type="submit" class="wppa-button wppa-button--primary"><?php esc_html_e( 'Создать аккаунт', 'wp-personal-account' ); ?></button>
	</form>

	<?php if ( ! isset( $hide_login ) || ! $hide_login ) : ?>
		<div class="wppa-switch wppa-switch--button">
			<span class="wppa-switch__text"><?php esc_html_e( 'Уже есть аккаунт?', 'wp-personal-account' ); ?></span>
			<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( WPPA_Plugin::login_url() ); ?>">
				<?php esc_html_e( 'Войти', 'wp-personal-account' ); ?>
			</a>
		</div>
	<?php endif; ?>
</div>
