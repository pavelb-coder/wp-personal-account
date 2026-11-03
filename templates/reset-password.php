<?php
/**
 * Установка нового пароля по ссылке из письма.
 *
 * Доступные переменные: $key, $login.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- ключ и логин приходят из ссылки в письме.
$wppa_key   = isset( $key ) ? $key : ( isset( $_GET['wppa_key'] ) ? sanitize_text_field( wp_unslash( $_GET['wppa_key'] ) ) : '' );
$wppa_login = isset( $login ) ? $login : ( isset( $_GET['wppa_login'] ) ? sanitize_text_field( wp_unslash( $_GET['wppa_login'] ) ) : '' );
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$wppa_user     = WPPA_Reset::validate( $wppa_key, $wppa_login );
$wppa_min      = (int) WPPA_Plugin::min_password_length();
$wppa_headline = $wppa_user ? __( 'Новый пароль', 'wp-personal-account' ) : __( 'Ссылка недействительна', 'wp-personal-account' );
?>
<div class="wppa-form-wrap">
	<h2 class="wppa-title"><?php echo esc_html( $wppa_headline ); ?></h2>

	<?php if ( ! $wppa_user ) : ?>

		<div class="wppa-notice wppa-notice--error">
			<?php esc_html_e( 'Ссылка для сброса пароля устарела или уже использована. Запросите новую — это займёт полминуты.', 'wp-personal-account' ); ?>
		</div>

		<div class="wppa-switch wppa-switch--button">
			<span class="wppa-switch__text"><?php esc_html_e( 'Сбросить пароль заново?', 'wp-personal-account' ); ?></span>
			<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( WPPA_Plugin::lost_password_url() ); ?>">
				<?php esc_html_e( 'Забыли пароль?', 'wp-personal-account' ); ?>
			</a>
		</div>

	<?php else : ?>

		<p class="wppa-hint wppa-hint--block">
			<?php
			printf(
				/* translators: %s: логин */
				esc_html__( 'Новый пароль для аккаунта «%s».', 'wp-personal-account' ),
				esc_html( $wppa_user->user_login )
			);
			?>
		</p>

		<form class="wppa-form" method="post" action="<?php echo esc_url( WPPA_Plugin::account_url() ); ?>">
			<?php wp_nonce_field( 'wppa_resetpass', 'wppa_nonce' ); ?>
			<input type="hidden" name="wppa_action" value="resetpass" />
			<input type="hidden" name="wppa_key" value="<?php echo esc_attr( $wppa_key ); ?>" />
			<input type="hidden" name="wppa_login" value="<?php echo esc_attr( $wppa_user->user_login ); ?>" />

			<p class="wppa-field">
				<label for="wppa-new-password"><?php esc_html_e( 'Новый пароль', 'wp-personal-account' ); ?><?php echo WPPA_Fields::req_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
				<span class="wppa-password">
					<input
						type="password"
						id="wppa-new-password"
						name="password"
						autocomplete="new-password"
						minlength="<?php echo esc_attr( $wppa_min ); ?>"
						required
					/>
					<button type="button" class="wppa-password__toggle" data-wppa-toggle="wppa-new-password" aria-label="<?php esc_attr_e( 'Показать пароль', 'wp-personal-account' ); ?>">👁</button>
				</span>
			</p>

			<?php WPPA_Password::render_requirements( 'wppa-new-password' ); ?>

			<p class="wppa-field">
				<label for="wppa-new-password-confirm"><?php esc_html_e( 'Повторите пароль', 'wp-personal-account' ); ?><?php echo WPPA_Fields::req_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
				<input
					type="password"
					id="wppa-new-password-confirm"
					name="password_confirm"
					autocomplete="new-password"
					minlength="<?php echo esc_attr( $wppa_min ); ?>"
					required
				/>
			</p>

			<button type="submit" class="wppa-button wppa-button--primary">
				<?php esc_html_e( 'Сохранить пароль', 'wp-personal-account' ); ?>
			</button>
		</form>

	<?php endif; ?>
</div>
