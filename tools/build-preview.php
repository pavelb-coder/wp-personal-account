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
require $base . 'includes/class-wppa-fields.php';
require $base . 'includes/class-wppa-template.php';
require $base . 'includes/class-wppa-notices.php';
require $base . 'includes/class-wppa-account.php';
require $base . 'includes/class-wppa-profile.php';
require $base . 'includes/class-wppa-teams.php';
require $base . 'includes/class-wppa-builder.php';
require $base . 'includes/class-wppa-shortcodes.php';
require $base . 'includes/class-wppa-widget.php';

$GLOBALS['wppa_test']['users'] = array(
	7 => array( 'Иван Петров', 'email' => 'ivan@example.com' ),
	8 => array( 'Мария Соколова', 'email' => 'maria@example.com' ),
	9 => array( 'Пётр Сидоров', 'email' => 'petr@example.com' ),
);

// Демо-персональные данные пользователя.
update_user_meta( 7, '_wppa_phone', '+7 913 555-21-08' );
update_user_meta( 7, '_wppa_city', 'Красноярск' );
update_user_meta( 7, '_wppa_company', 'ООО «Вектор»' );
update_user_meta( 7, '_wppa_position', 'Руководитель проектов' );
update_user_meta( 7, '_wppa_patronymic', 'Сергеевич' );
update_user_meta( 7, '_wppa_birthdate', '1990-04-12' );
update_user_meta( 7, '_wppa_telegram', '@ivan_petrov' );

$user = new WP_User();
$tabs = WPPA_Template::all_tabs();

// Демо-команда: капитан — Иван, участники — Мария и Пётр.
$demo_team = WPPA_Teams::create( 'Студия «Вектор»', 7 );
if ( ! is_wp_error( $demo_team ) ) {
	// Мария — наставник команды.
	update_user_meta( 8, WPPA_Teams::META_TEAM, $demo_team );
	update_user_meta( 9, WPPA_Teams::META_TEAM, $demo_team );
	update_user_meta( 8, WPPA_Teams::META_ROLE, 'mentor' );
	update_user_meta( 9, WPPA_Teams::META_ROLE, 'member' );
}

function demo_section( $title, $note, $html ) {
	return '<section class="demo-block"><div class="demo-block__head"><h2>' . htmlspecialchars( $title ) . '</h2>'
		. ( $note ? '<p>' . htmlspecialchars( $note ) . '</p>' : '' ) . '</div>' . $html . '</section>';
}

// Демонстрация конструктора: свой HTML-блок в «Обзоре».
update_option(
	WPPA_Builder::OPTION,
	array(
		'dashboard' => array(
			array( 'type' => 'block', 'id' => 'greeting', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
			array( 'type' => 'block', 'id' => 'profile-summary', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
			array( 'type' => 'custom', 'id' => 'custom-0', 'enabled' => 1, 'title' => 'Памятка', 'audience' => 'all', 'html' => '<p>Профиль и команда заполнены — значит, заказы и документы придут на верный адрес.</p><ul><li>Проверьте email в разделе «Безопасность»</li><li>Пригласите коллег во вкладке «Команда»</li></ul>' ),
			array( 'type' => 'block', 'id' => 'quick-actions', 'enabled' => 1, 'title' => 'Что дальше', 'audience' => 'all', 'html' => '' ),
			array( 'type' => 'block', 'id' => 'profile-summary', 'enabled' => 0, 'title' => '', 'audience' => 'all', 'html' => '' ),
		),
	)
);

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
	'Команда (капитан)',
	'Вкладка team: состав с ролями, назначение наставника, ссылка-приглашение, приглашение по email, переименование, роспуск',
	WPPA_Template::get( 'tab-team', array( 'user' => $user, 'tabs' => $tabs ) )
);

// Вид глазами наставника: приглашения есть, переименования и роспуска нет.
$mentor_user = get_userdata( 8 );
$sections   .= demo_section(
	'Команда (наставник)',
	'Так команду видит наставник: может приглашать и исключать участников, но не переименовать и не распустить команду',
	WPPA_Template::get( 'tab-team', array( 'user' => $mentor_user, 'tabs' => $tabs ) )
);

$sections .= demo_section(
	'Безопасность',
	'Вкладка security: смена пароля, смена email с подтверждением, выход',
	WPPA_Template::get( 'tab-security', array( 'user' => $user, 'tabs' => $tabs ) )
);

// Виджет для главной страницы.
$GLOBALS['wppa_test']['logged_in'] = false;
ob_start();
$demo_widget = new WPPA_Auth_Widget();
$demo_widget->widget(
	array(
		'before_widget' => '<div class="widget widget_wppa_auth">',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
		'after_widget'  => '</div>',
	),
	array( 'title' => 'Личный кабинет', 'mode' => 'both' )
);
$widget_demo = ob_get_clean();
$GLOBALS['wppa_test']['logged_in'] = true;

$sections .= demo_section(
	'Виджет на главной странице',
	'Виджет «Личный кабинет: вход и регистрация»: вкладки «Вход» и «Регистрация» в одном компактном блоке',
	'<div class="demo-sidebar"><div class="widget-title" style="margin:0 0 10px;font-size:1rem;font-weight:650;">Личный кабинет</div>'
		. str_replace( array( '<h2 class="widget-title">Личный кабинет</h2>' ), '', $widget_demo )
		. '</div>'
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
.demo-sidebar { max-width: 340px; margin: 0 auto; }
.demo-block > .demo-sidebar { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 22px; box-shadow: 0 10px 30px rgba(15,23,42,.06); }
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
