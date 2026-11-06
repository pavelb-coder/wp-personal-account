<?php
/**
 * Вкладка «Участники» — список участников и их баллы (только куратор).
 *
 * Вывод блоков конструктора: по умолчанию здесь стоит блок
 * «Участники и баллы» (participants-widget).
 *
 * Доступные переменные: $user, $tabs.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

WPPA_Builder::render_tab(
	'participants',
	array(
		'user' => $user,
		'tabs' => $tabs,
	)
);
