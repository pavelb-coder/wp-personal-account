<?php
/**
 * Экран для неавторизованных: вход или регистрация.
 *
 * Доступные переменные: $view (login|register|lostpassword|resetpass), $registration, $key, $login.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wppa wppa--guest">

	<?php WPPA_Notices::render(); ?>

	<div class="wppa-card">
		<?php
		if ( 'lostpassword' === $view ) {
			WPPA_Template::render( 'lost-password', array() );
		} elseif ( 'resetpass' === $view ) {
			WPPA_Template::render(
				'reset-password',
				array(
					'key'   => isset( $key ) ? $key : '',
					'login' => isset( $login ) ? $login : '',
				)
			);
		} elseif ( 'register' === $view && $registration ) {
			WPPA_Template::render( 'register', array( 'title' => '' ) );
		} else {
			WPPA_Template::render( 'login', array( 'redirect' => '', 'title' => '' ) );
		}
		?>
	</div>
</div>
