<?php
/**
 * Генератор статического превью кабинета (демо-данные, без WordPress).
 *
 * Запуск: php tools/build-preview.php
 *
 * @package WP_Personal_Account
 */

require __DIR__ . '/wp-stubs.php';

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

$user = new WP_User();
$tabs = WPPA_Template::all_tabs();

function demo_section( $title, $note, $html ) {
	return '<section class="demo-block"><div class="demo-block__head"><h2>' . htmlspecialchars( $title ) . '</h2>'
		. ( $note ? '<p>' . htmlspecialchars( $note ) . '</p>' : '' ) . '</div>' . $html . '</section>';
}

$sections  = demo_section(
	'Кабинет целиком (активна вкладка «Обзор»)',
	'Шапка с аватаром и кнопкой выхода, навигация по вкладкам, контент активной вкладки',
	WPPA_Template::get( 'account', array( 'user' => $user, 'tabs' => $tabs, 'current_tab' => 'dashboard' ) )
);

$sections .= demo_section(
	'Профиль',
	'Вкладка profile: имя, фамилия, отображаемое имя, «о себе»',
	WPPA_Template::get( 'tab-profile', array( 'user' => $user, 'tabs' => $tabs ) )
		. WPPA_Template::get( 'notice', array( 'type' => 'success', 'text' => 'Профиль обновлён.' ) )
);

$sections .= demo_section(
	'Безопасность',
	'Вкладка security: смена пароля, смена email с подтверждением, выход',
	WPPA_Template::get( 'tab-security', array( 'user' => $user, 'tabs' => $tabs ) )
);

$sections .= demo_section(
	'Вход и регистрация',
	'Экран для гостей: формы [wppa_login] и [wppa_register]',
	'<div class="demo-two">'
		. '<div>' . WPPA_Template::get( 'login', array( 'redirect' => '', 'title' => '' ) ) . '</div>'
		. '<div>' . WPPA_Template::get( 'register', array( 'title' => '' ) ) . '</div>'
		. '</div>'
);

$css = file_get_contents( $base . 'assets/css/account.css' );

$html = '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>WP Personal Account — превью интерфейса</title>
<style>
' . $css . '
body {
	margin: 0;
	padding: 32px 20px 64px;
	background: #eef2f7;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	color: #0f172a;
}
.demo-top { max-width: 960px; margin: 0 auto 28px; }
.demo-top h1 { margin: 0 0 8px; font-size: 1.6rem; }
.demo-top p { margin: 0; color: #64748b; }
.demo-block { max-width: 960px; margin: 0 auto 40px; }
.demo-block__head { margin-bottom: 16px; }
.demo-block__head h2 { margin: 0 0 4px; font-size: 1.1rem; text-transform: uppercase; letter-spacing: .06em; color: #475569; }
.demo-block__head p { margin: 0; font-size: .88rem; color: #7c8798; }
.demo-block > .wppa, .demo-block > .demo-two { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 22px; box-shadow: 0 10px 30px rgba(15,23,42,.06); }
.demo-two { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 28px; }
</style>
</head>
<body>
<div class="demo-top">
	<h1>Личный кабинет — превью интерфейса</h1>
	<p>Демонстрация шаблонов плагина WP Personal Account: обзор, профиль, безопасность, вход и регистрация.</p>
</div>
' . $sections . '
</body>
</html>';

file_put_contents( $base . 'preview.html', $html );
echo 'preview.html: ' . strlen( $html ) . " байт\n";
