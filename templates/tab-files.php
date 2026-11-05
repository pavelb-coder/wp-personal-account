<?php
/**
 * Вкладка «Файлы» — вывод блоков конструктора.
 *
 * Доступные переменные: $user, $tabs.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

WPPA_Builder::render_tab(
	'files',
	array(
		'user' => $user,
		'tabs' => $tabs,
	)
);
