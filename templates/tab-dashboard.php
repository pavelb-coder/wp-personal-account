<?php
/**
 * Вкладка «Обзор» — вывод блоков конструктора.
 *
 * Доступные переменные: $user, $tabs.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

WPPA_Builder::render_tab(
	'dashboard',
	array(
		'user' => $user,
		'tabs' => $tabs,
	)
);
