<?php
/**
 * Вкладка «Сертификаты» — сертификаты участника.
 *
 * Доступные переменные: $user, $tabs.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_cert_admin = WPPA_Certificates::curator_admin_link();

if ( '' !== $wppa_cert_admin ) :
	?>
	<section class="wppa-card wppa-certificates__plugin">
		<h2 class="wppa-title"><?php esc_html_e( 'Сертификаты — в плагине WP Diplom', 'wp-personal-account' ); ?></h2>
		<p class="wppa-hint">
			<?php esc_html_e( 'Выдача, отзыв и правка сертификатов живут в WP Diplom: вкладка «Сертификаты» в навигации ведёт прямо туда. Ниже — быстрый список выданных сертификатов участников.', 'wp-personal-account' ); ?>
		</p>
		<a class="wppa-button wppa-button--primary" href="<?php echo esc_url( $wppa_cert_admin ); ?>">
			<?php esc_html_e( 'Открыть WP Diplom', 'wp-personal-account' ); ?>
		</a>
	</section>
	<?php
endif;

WPPA_Builder::render_tab(
	'certificates',
	array(
		'user' => $user,
		'tabs' => $tabs,
	)
);
