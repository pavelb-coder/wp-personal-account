<?php
/**
 * Форма регистрации.
 *
 * Доступные переменные: $title (может отсутствовать).
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_title = isset( $title ) && '' !== $title ? $title : __( 'Регистрация', 'wp-personal-account' );
?>
<div class="wppa-form-wrap">
	<h2 class="wppa-title"><?php echo esc_html( $wppa_title ); ?></h2>

	<form class="wppa-form" method="post" action="<?php echo esc_url( WPPA_Plugin::account_url() ); ?>">
		<?php wp_nonce_field( 'wppa_register', 'wppa_nonce' ); ?>
		<input type="hidden" name="wppa_action" value="register" />

		<?php WPPA_Fields::render( 'register' ); ?>

		<p class="wppa-field">
			<label for="wppa-email"><?php esc_html_e( 'Email', 'wp-personal-account' ); ?></label>
			<input type="email" id="wppa-email" name="email" autocomplete="email" required />
		</p>

		<p class="wppa-field">
			<label for="wppa-reg-password"><?php esc_html_e( 'Пароль', 'wp-personal-account' ); ?></label>
			<span class="wppa-password">
				<input type="password" id="wppa-reg-password" name="password" minlength="8" autocomplete="new-password" required data-wppa-password />
				<button type="button" class="wppa-password__toggle" data-wppa-toggle="wppa-reg-password" aria-label="<?php esc_attr_e( 'Показать пароль', 'wp-personal-account' ); ?>">👁</button>
			</span>
			<span class="wppa-hint"><?php esc_html_e( 'Минимум 8 символов.', 'wp-personal-account' ); ?></span>
		</p>

		<p class="wppa-field">
			<label for="wppa-reg-password2"><?php esc_html_e( 'Пароль ещё раз', 'wp-personal-account' ); ?></label>
			<input type="password" id="wppa-reg-password2" name="password_confirm" minlength="8" autocomplete="new-password" required data-wppa-password />
		</p>

		<p class="wppa-field wppa-field--check">
			<label>
				<input type="checkbox" name="consent" value="1" required />
				<?php esc_html_e( 'Согласен на обработку персональных данных', 'wp-personal-account' ); ?>
			</label>
		</p>

		<p class="wppa-hint wppa-hint--block">
			<?php esc_html_e( 'Поля со звёздочкой обязательны. Данные видны только вам, капитану команды и администратору сайта.', 'wp-personal-account' ); ?>
		</p>

		<button type="submit" class="wppa-button wppa-button--primary"><?php esc_html_e( 'Создать аккаунт', 'wp-personal-account' ); ?></button>
	</form>
</div>
