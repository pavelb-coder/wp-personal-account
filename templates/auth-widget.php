<?php
/**
 * Компактный блок входа и регистрации (виджет и шорткод [wppa_auth]).
 *
 * Доступные переменные: $mode (both|login|register), $registration, $redirect, $active, $hide_other.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_mode   = isset( $mode ) ? $mode : 'both';
$wppa_active = isset( $active ) && 'register' === $active && $registration ? 'register' : 'login';
// В режиме «оба» кнопки перехода не нужны (есть вкладки), их же скрывает атрибут hide_other.
$wppa_hide_other = ! empty( $hide_other ) || 'both' === $wppa_mode;
?>
<div class="wppa wppa--widget">

	<?php WPPA_Notices::render(); ?>

	<?php if ( 'both' === $wppa_mode && $registration ) : ?>
		<div class="wppa-widget__tabs" role="tablist">
			<button type="button" class="wppa-widget__tab<?php echo 'login' === $wppa_active ? ' is-active' : ''; ?>" data-wppa-panel-btn="login" role="tab" aria-selected="<?php echo 'login' === $wppa_active ? 'true' : 'false'; ?>" aria-controls="wppa-panel-login">
				<?php esc_html_e( 'Вход', 'wp-personal-account' ); ?>
			</button>
			<button type="button" class="wppa-widget__tab<?php echo 'register' === $wppa_active ? ' is-active' : ''; ?>" data-wppa-panel-btn="register" role="tab" aria-selected="<?php echo 'register' === $wppa_active ? 'true' : 'false'; ?>" aria-controls="wppa-panel-register">
				<?php esc_html_e( 'Регистрация', 'wp-personal-account' ); ?>
			</button>
		</div>
	<?php endif; ?>

	<div
		class="wppa-widget__panel<?php echo 'both' === $wppa_mode && 'login' !== $wppa_active ? ' is-hidden' : ''; ?>"
		id="wppa-panel-login"
		data-wppa-panel="login"
	>
		<?php
		if ( 'register' !== $wppa_mode ) {
			WPPA_Template::render(
				'login',
				array(
					'redirect'      => isset( $redirect ) ? $redirect : '',
					'title'         => '',
					'hide_register' => $wppa_hide_other,
				)
			);
		}
		?>
	</div>

	<?php if ( $registration && 'login' !== $wppa_mode ) : ?>
		<div
			class="wppa-widget__panel<?php echo 'both' === $wppa_mode && 'register' !== $wppa_active ? ' is-hidden' : ''; ?>"
			id="wppa-panel-register"
			data-wppa-panel="register"
		>
			<?php
			if ( 'login' !== $wppa_mode ) {
				WPPA_Template::render(
					'register',
					array(
						'title'      => '',
						'hide_login' => $wppa_hide_other,
					)
				);
			}
			?>
		</div>
	<?php endif; ?>
</div>
