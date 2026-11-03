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
		} else {
			WPPA_Template::render( 'login', array( 'redirect' => '', 'title' => '' ) );
		}
		?>
	</div>
</div>
