<?php
/**
 * Вкладка «Безопасность» — вывод блоков конструктора.
 *
 * Доступные переменные: $user, $tabs.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

WPPA_Builder::render_tab(
	'security',
	array(
		'user' => $user,
		'tabs' => $tabs,
	)
);
