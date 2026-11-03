<?php
/**
 * Сборка статического превью админ-панели (preview-admin.html).
 *
 * Запуск: php tools/build-admin-preview.php
 *
 * @package WP_Personal_Account
 */

require __DIR__ . '/wp-admin-stubs.php';

$base = dirname( __DIR__ ) . '/';

define( 'WPPA_VERSION', '2.0.0' );
define( 'WPPA_FILE', $base . 'wp-personal-account.php' );
define( 'WPPA_PATH', $base );
define( 'WPPA_URL', 'https://example.com/wp-content/plugins/wp-personal-account/' );

require $base . 'includes/class-wppa-plugin.php';
require $base . 'includes/class-wppa-template.php';
require $base . 'includes/class-wppa-notices.php';
require $base . 'includes/class-wppa-account.php';
require $base . 'includes/class-wppa-profile.php';
require $base . 'includes/class-wppa-teams.php';
require $base . 'includes/class-wppa-builder.php';
require $base . 'includes/class-wppa-admin.php';

// Демо-данные графика: разнообразные значения вместо одинаковых.
$original_stats = null;

function demo_page( $title, $note, $callback ) {
	ob_start();
	$callback();
	$html = ob_get_clean();

	return '<section class="demo-section"><div class="demo-section__head"><h2>' . htmlspecialchars( $title ) . '</h2>'
		. ( $note ? '<p>' . htmlspecialchars( $note ) . '</p>' : '' ) . '</div>'
		. '<div class="demo-frame">' . $html . '</div></section>';
}

// Демо-команды для страницы «Команды».
$GLOBALS['wppa_test']['users'] = array(
	7 => array( 'Иван Петров', 'email' => 'ivan@example.com' ),
	8 => array( 'Мария Соколова', 'email' => 'maria@example.com' ),
);

foreach ( array( 'Студия «Вектор»', 'Отдел закупок', 'Фриланс-группа' ) as $demo_name ) {
	$demo_team = WPPA_Teams::create( $demo_name, 7 );
	if ( ! is_wp_error( $demo_team ) ) {
		update_user_meta( 8, WPPA_Teams::META_TEAM, $demo_team );
		update_user_meta( 8, WPPA_Teams::META_ROLE, 'member' );
		// «Выходим» из команды, чтобы можно было создать следующую.
		update_user_meta( 7, WPPA_Teams::META_TEAM, 0 );
		update_user_meta( 7, WPPA_Teams::META_ROLE, '' );
	}
}

$sections  = demo_page( 'Обзор', 'Меню «Личный кабинет» → «Обзор»: метрики, график регистраций, состояние интеграций, последние регистрации', static function () { WPPA_Admin::page_dashboard(); } );
$sections .= demo_page( 'Аккаунты', 'Поиск, фильтр по роли, число заказов и незавершённые подтверждения email', static function () { WPPA_Admin::page_accounts(); } );
$sections .= demo_page( 'Конструктор', 'Сборка кабинета из блоков: порядок, включение, заголовки, аудитория и свои HTML-блоки', static function () { WPPA_Builder::page(); } );
$sections .= demo_page( 'Команды', 'Объединение аккаунтов: состав, капитаны, лимит участников, роспуск команды', static function () { WPPA_Admin::page_teams(); } );
$sections .= demo_page( 'Инструменты', 'Обслуживание: восстановление страницы, сброс лимитов, очистка заявок, экспорт CSV', static function () { WPPA_Admin::page_tools(); } );

$admin_css = file_get_contents( $base . 'assets/css/admin.css' );

$shims = '
/* Минимальные стили WordPress admin для автономного превью. */
body {
	margin: 0;
	padding: 24px 28px 60px;
	background: #f0f0f1;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	font-size: 13px;
	color: #1d2327;
	line-height: 1.5;
}
a { color: #2271b1; }
.wrap { margin: 0; }
h1.wp-heading-inline { font-size: 23px; font-weight: 400; margin: 0 8px 0 0; display: inline-block; }
.page-title-action {
	display: inline-block;
	margin: 0 0 4px;
	padding: 4px 10px;
	border: 1px solid #2271b1;
	border-radius: 3px;
	background: #f6f7f7;
	color: #2271b1;
	font-size: 13px;
	line-height: 1.6;
	text-decoration: none;
	vertical-align: top;
}
hr.wp-header-end { clear: both; visibility: hidden; margin: 0; }
h2 { font-size: 15px; font-weight: 600; margin: 18px 0 10px; }
.button {
	display: inline-block;
	padding: 0 10px;
	min-height: 30px;
	line-height: 28px;
	border: 1px solid #2271b1;
	border-radius: 3px;
	background: #f6f7f7;
	color: #2271b1;
	font-size: 13px;
	text-decoration: none;
	cursor: pointer;
	vertical-align: middle;
}
.button-small { min-height: 24px; line-height: 22px; font-size: 11px; padding: 0 8px; }
.notice {
	margin: 16px 0;
	padding: 1px 12px;
	border: 1px solid #c3c4c7;
	border-left: 4px solid #72aee6;
	background: #fff;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.notice-success { border-left-color: #00a32a; }
.notice-info { border-left-color: #72aee6; }
.widefat { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #c3c4c7; }
.widefat th, .widefat td { padding: 9px 10px; text-align: left; border-bottom: 1px solid #f0f0f1; vertical-align: middle; }
.widefat thead th { background: #f6f7f7; font-weight: 600; }
.striped tbody tr:nth-child(odd) { background: #f6f7f7; }
.screen-reader-text { position: absolute; left: -9999px; }
.search-box input[type="search"] {
	padding: 3px 8px;
	min-height: 30px;
	border: 1px solid #8c8f94;
	border-radius: 3px;
	font-size: 14px;
}
select { min-height: 30px; border: 1px solid #8c8f94; border-radius: 3px; }
code { background: #f6f7f7; padding: 2px 5px; border-radius: 3px; font-size: 12px; }

/* Обвязка превью. */
.demo-top { max-width: 1180px; margin: 0 auto 26px; }
.demo-top h1 { margin: 0 0 6px; font-size: 22px; font-weight: 650; }
.demo-top p { margin: 0; color: #646970; }
.demo-section { max-width: 1180px; margin: 0 auto 36px; }
.demo-section__head { margin-bottom: 12px; }
.demo-section__head h2 { margin: 0 0 2px; font-size: 12px; text-transform: uppercase; letter-spacing: .07em; color: #50575e; }
.demo-section__head p { margin: 0; font-size: 12px; color: #787c82; }
.demo-frame {
	background: #f0f0f1;
	border: 1px solid #dcdcde;
	border-radius: 10px;
	padding: 8px 20px 20px;
	overflow-x: auto;
}
';

$html = '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>WP Personal Account — панель управления (превью)</title>
<style>
' . $admin_css . $shims . '
</style>
</head>
<body>
<div class="demo-top">
	<h1>Панель управления плагином</h1>
	<p>Отдельное меню «Личный кабинет» в wp-admin: обзор со статистикой, аккаунты и инструменты. Демо-данные.</p>
</div>
' . $sections . '
</body>
</html>';

file_put_contents( $base . 'preview-admin.html', $html );
echo 'preview-admin.html: ' . strlen( $html ) . " байт\n";
