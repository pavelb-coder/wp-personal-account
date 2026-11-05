<?php
/**
 * Вкладка «Рассылка» — вывод блоков конструктора.
 *
 * Доступна только куратору: он управляет письмом участникам команд,
 * прошедших во второй этап.
 *
 * Доступные переменные: $user, $tabs.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

WPPA_Builder::render_tab(
	'mailing',
	array(
		'user' => $user,
		'tabs' => $tabs,
	)
);
