<?php
/**
 * Вкладка «Сертификаты» — сертификаты участника.
 *
 * Доступные переменные: $user, $tabs.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

WPPA_Builder::render_tab(
	'certificates',
	array(
		'user' => $user,
		'tabs' => $tabs,
	)
);
