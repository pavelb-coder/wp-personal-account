<?php
/**
 * Вкладка «Олимпиады» — баллы за тесты из плагина олимпиад.
 *
 * Доступные переменные: $user, $tabs.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

WPPA_Builder::render_tab(
	'olympiad',
	array(
		'user' => $user,
		'tabs' => $tabs,
	)
);
