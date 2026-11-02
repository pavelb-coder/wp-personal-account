<?php
/**
 * Вкладка «Безопасность»: пароль, email, выход.
 *
 * Доступные переменные: $user, $tabs.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_pending = WPPA_Profile::pending_email( $user->ID );
?>
<h2 class="wppa-title"><?php esc_html_e( 'Безопасность', 'wp-personal-account' ); ?></h2>

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

<section class="wppa-card">
	<h3 class="wppa-subtitle"><?php esc_html_e( 'Смена email', 'wp-personal-account' ); ?></h3>
	<p class="wppa-hint">
		<?php
		printf(
			/* translators: %s: текущий email */
			esc_html__( 'Текущий email: %s', 'wp-personal-account' ),
			esc_html( $user->user_email )
		);
		?>
	</p>

	<?php if ( $wppa_pending ) : ?>
		<div class="wppa-notice wppa-notice--info">
			<?php
			printf(
				/* translators: %s: новый email, ожидающий подтверждения */
				esc_html__( 'Ожидает подтверждения адрес %s. Проверьте почту.', 'wp-personal-account' ),
				esc_html( $wppa_pending )
			);
			?>
		</div>
	<?php endif; ?>

	<form class="wppa-form" method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'security' ) ); ?>">
		<?php wp_nonce_field( 'wppa_email', 'wppa_nonce' ); ?>
		<input type="hidden" name="wppa_action" value="email" />

		<p class="wppa-field">
			<label for="wppa-new-email"><?php esc_html_e( 'Новый email', 'wp-personal-account' ); ?></label>
			<input type="email" id="wppa-new-email" name="new_email" autocomplete="email" required />
			<span class="wppa-hint"><?php esc_html_e( 'На новый адрес придёт письмо со ссылкой подтверждения.', 'wp-personal-account' ); ?></span>
		</p>

		<button type="submit" class="wppa-button wppa-button--primary"><?php esc_html_e( 'Отправить подтверждение', 'wp-personal-account' ); ?></button>
	</form>
</section>

<section class="wppa-card">
	<h3 class="wppa-subtitle"><?php esc_html_e( 'Сессия', 'wp-personal-account' ); ?></h3>
	<p class="wppa-hint"><?php esc_html_e( 'Выход завершит текущую сессию на этом устройстве.', 'wp-personal-account' ); ?></p>
	<a class="wppa-button wppa-button--ghost" data-wppa-confirm-logout href="<?php echo esc_url( wp_logout_url( WPPA_Plugin::account_url() ) ); ?>">
		<?php esc_html_e( 'Выйти из аккаунта', 'wp-personal-account' ); ?>
	</a>
</section>
