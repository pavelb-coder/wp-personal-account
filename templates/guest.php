<?php
/**
 * Экран для неавторизованных: вход или регистрация.
 *
 * Доступные переменные: $view, $registration.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wppa wppa--guest">

	<?php WPPA_Notices::render(); ?>

	<div class="wppa-card">
		<?php
		if ( 'register' === $view && $registration ) {
			WPPA_Template::render( 'register', array( 'title' => '' ) );
			printf(
				'<p class="wppa-switch">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'Уже есть аккаунт?', 'wp-personal-account' ),
				esc_url( WPPA_Plugin::account_url( array( 'wppa_view' => 'login' ) ) ),
				esc_html__( 'Войти', 'wp-personal-account' )
			);
		} else {
			WPPA_Template::render( 'login', array( 'redirect' => '', 'title' => '' ) );

			if ( $registration ) {
				printf(
					'<p class="wppa-switch">%1$s <a href="%2$s">%3$s</a></p>',
					esc_html__( 'Нет аккаунта?', 'wp-personal-account' ),
					esc_url( WPPA_Plugin::account_url( array( 'wppa_view' => 'register' ) ) ),
					esc_html__( 'Зарегистрироваться', 'wp-personal-account' )
				);
			}
		}
		?>
	</div>
</div>
