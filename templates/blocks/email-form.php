<?php
/**
 * Блок «Смена email».
 *
 * Доступные переменные: $user, $tabs, $block_title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_pending = WPPA_Profile::pending_email( $user->ID );
?>
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

