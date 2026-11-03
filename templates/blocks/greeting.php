<?php
/**
 * Блок «Приветствие».
 *
 * Доступные переменные: $user, $tabs, $block_title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_heading = isset( $block_title ) && '' !== $block_title ? $block_title : __( 'Здравствуйте, %s!', 'wp-personal-account' );
?>
<h2 class="wppa-title">
	<?php
	printf(
		/* translators: %s: имя пользователя */
		esc_html( $wppa_heading ),
		esc_html( $user->display_name )
	);
	?>
</h2>
