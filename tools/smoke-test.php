<?php
/**
 * Дымовой тест: рендер всех шаблонов на заглушках WordPress.
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
require $base . 'includes/class-wppa-auth.php';
require $base . 'includes/class-wppa-profile.php';
require $base . 'includes/class-wppa-teams.php';
require $base . 'includes/class-wppa-builder.php';
require $base . 'includes/class-wppa-shortcodes.php';
require $base . 'includes/class-wppa-widget.php';
require $base . 'includes/class-wppa-blocks.php';

// Обход автозагрузки шаблонов: путь к templates.
$user  = new WP_User();
$tabs  = WPPA_Template::all_tabs();
$cases = array(
	'account'         => array( 'user' => $user, 'tabs' => $tabs, 'current_tab' => 'dashboard' ),
	'tab-dashboard'   => array( 'user' => $user, 'tabs' => $tabs ),
	'tab-profile'     => array( 'user' => $user, 'tabs' => $tabs ),
	'tab-security'    => array( 'user' => $user, 'tabs' => $tabs ),
	'tab-team'        => array( 'user' => $user, 'tabs' => $tabs ),
	'login'           => array( 'redirect' => '', 'title' => '' ),
	'register'        => array( 'title' => '' ),
	'logged-in'       => array( 'message' => 'Вы уже авторизованы.' ),
	'notice'          => array( 'type' => 'info', 'text' => 'Тест' ),
	'guest'           => array( 'view' => 'login', 'registration' => true ),
	'account-wrapper' => array(),
);

$fail = 0;

foreach ( $cases as $name => $args ) {
	$GLOBALS['wppa_test']['logged_in'] = ! in_array( $name, array( 'login', 'register', 'guest' ), true );

	ob_start();
	try {
		WPPA_Template::render( $name, $args );
		$html = ob_get_clean();
	} catch ( Throwable $e ) {
		ob_end_clean();
		echo "FAIL  {$name}: " . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ")\n";
		$fail++;
		continue;
	}

	$len = strlen( $html );
	if ( $len < 40 ) {
		echo "WARN  {$name}: подозрительно короткий вывод ({$len} байт)\n";
		$fail++;
		continue;
	}

	if ( preg_match( '/<php|\?>|Warning|Notice:|Deprecated:/i', $html ) ) {
		echo "WARN  {$name}: в выводе мусор\n";
	}

	echo "OK    {$name} — {$len} байт\n";
}

// Сценарий «пользователь уже в команде».
$GLOBALS['wppa_test']['logged_in'] = true;
$team_id = WPPA_Teams::create( 'Тестовая команда', 7 );

if ( is_wp_error( $team_id ) ) {
	echo "FAIL  teams: не удалось создать команду\n";
	$fail++;
} else {
	ob_start();
	WPPA_Template::render( 'tab-team', array( 'user' => new WP_User(), 'tabs' => WPPA_Template::tabs() ) );
	$html = ob_get_clean();

	if ( false === strpos( $html, 'Тестовая команда' ) ) {
		echo "FAIL  tab-team (в команде): нет названия команды\n";
		$fail++;
	} else {
		echo 'OK    tab-team (в команде) — ' . strlen( $html ) . " байт\n";
	}

	$token = get_post_meta( $team_id, WPPA_Teams::META_TOKEN, true );
	$found = WPPA_Teams::find_by_token( $token );
	echo 'team: id=' . $team_id . ', токен ' . ( $token ? 'есть' : 'нет' ) . ', поиск по токену → ' . $found . "\n";
	echo 'team: участников ' . WPPA_Teams::members_count( $team_id ) . ', капитан=' . ( WPPA_Teams::is_owner( 7 ) ? 'да' : 'нет' ) . "\n";

	// Индивидуальный лимит участников.
	WPPA_Teams::set_team_limit( $team_id, 2 );
	$first  = WPPA_Teams::add_member( $team_id, 8 );
	$second = WPPA_Teams::add_member( $team_id, 9 );

	$limit_checks = array(
		'лимит команды = 2'              => 2 === WPPA_Teams::team_limit( $team_id ),
		'свой лимит записан'             => 2 === WPPA_Teams::raw_team_limit( $team_id ),
		'второй участник добавлен'       => 'joined' === $first,
		'третий не прошёл (team_full)'   => is_wp_error( $second ) && 'wppa_team_full' === $second->get_error_code(),
		'участников стало 2 (капитан + 1)' => 2 === WPPA_Teams::members_count( $team_id ),
	);

	WPPA_Teams::set_team_limit( $team_id, 0 );
	$limit_checks['сброс к общему лимиту'] = 0 === WPPA_Teams::raw_team_limit( $team_id ) && 10 === WPPA_Teams::team_limit( $team_id );

	foreach ( $limit_checks as $check_name => $ok ) {
		echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'team limit: ' . $check_name . "\n";
		if ( ! $ok ) {
			$fail++;
		}
	}

	// Минимум участников.
	WPPA_Teams::set_team_limit( $team_id, 5 );
	WPPA_Teams::set_team_min( $team_id, 3 );

	$min_checks = array(
		'минимум команды = 3'          => 3 === WPPA_Teams::team_min( $team_id ),
		'свой минимум записан'         => 3 === WPPA_Teams::raw_team_min( $team_id ),
		'не хватает 1 участника'       => 1 === WPPA_Teams::missing_members( $team_id ),
		'команда ниже минимума'        => true === WPPA_Teams::is_below_min( $team_id ),
	);

	WPPA_Teams::add_member( $team_id, 9 );
	$min_checks['минимум набран'] = false === WPPA_Teams::is_below_min( $team_id );
	$min_checks['недобор = 0']    = 0 === WPPA_Teams::missing_members( $team_id );

	// Минимум не может превышать лимит.
	WPPA_Teams::set_team_min( $team_id, 50 );
	$min_checks['минимум ограничен лимитом'] = 5 === WPPA_Teams::raw_team_min( $team_id );

	// Сброс минимума к общему значению.
	WPPA_Teams::set_team_min( $team_id, 0 );
	$min_checks['сброс к общему минимуму'] = 0 === WPPA_Teams::raw_team_min( $team_id ) && WPPA_Teams::min_members() === WPPA_Teams::team_min( $team_id );

	foreach ( $min_checks as $check_name => $ok ) {
		echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'team min: ' . $check_name . "\n";
		if ( ! $ok ) {
			$fail++;
		}
	}

	WPPA_Teams::dissolve( $team_id );
	echo 'team: после роспуска команда ' . ( get_post( $team_id ) ? 'осталась' : 'удалена' ) . ", мета пользователя «" . get_user_meta( 7, WPPA_Teams::META_TEAM, true ) . "»\n";
}

// Конструктор: раскладка по умолчанию и свой HTML-блок.
$layout = WPPA_Builder::layout();
echo "builder: вкладки " . implode( ', ', array_keys( $layout ) ) . ", блоков " . array_sum( array_map( 'count', $layout ) ) . "\n";

update_option(
	WPPA_Builder::OPTION,
	array(
		'dashboard' => array(
			array( 'type' => 'block', 'id' => 'greeting', 'enabled' => 1, 'title' => 'С возвращением', 'audience' => 'all', 'html' => '' ),
			array( 'type' => 'custom', 'id' => 'custom-1', 'enabled' => 1, 'title' => 'Инструкция', 'audience' => 'all', 'html' => '<p>Заполните профиль.</p>' ),
			array( 'type' => 'block', 'id' => 'quick-actions', 'enabled' => 0, 'title' => '', 'audience' => 'all', 'html' => '' ),
		),
	)
);

ob_start();
WPPA_Builder::render_tab( 'dashboard', array( 'user' => new WP_User(), 'tabs' => WPPA_Template::tabs() ) );
$built = ob_get_clean();

$checks = array(
	'заголовок-переопределение' => false !== strpos( $built, 'С возвращением' ),
	'свой HTML-блок'            => false !== strpos( $built, 'Заполните профиль.' ),
	'выключенный блок скрыт'    => false === strpos( $built, 'Быстрые действия' ),
);

foreach ( $checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'builder: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// Очистка раскладки, чтобы не влиять на другие проверки.
delete_option( WPPA_Builder::OPTION );

// Виджет входа и регистрации.
WPPA_Auth_Widget::register();
echo 'widget: зарегистрирован ' . implode( ', ', (array) ( $GLOBALS['wppa_test']['widgets'] ?? array() ) ) . "\n";

$GLOBALS['wppa_test']['logged_in'] = false;

ob_start();
$widget = new WPPA_Auth_Widget();
$widget->widget(
	array(
		'before_widget' => '<section class="widget">',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
		'after_widget'  => '</section>',
	),
	array( 'title' => 'Вход на сайт', 'mode' => 'both' )
);
$widget_html = ob_get_clean();

$widget_checks = array(
	'заголовок виджета'          => false !== strpos( $widget_html, 'Вход на сайт' ),
	'панель входа'               => false !== strpos( $widget_html, 'wppa-panel-login' ),
	'панель регистрации'         => false !== strpos( $widget_html, 'wppa-panel-register' ),
	'переключатель вкладок'      => false !== strpos( $widget_html, 'data-wppa-panel-btn' ),
);

foreach ( $widget_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'widget: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// Виджет скрывается от авторизованных.
$GLOBALS['wppa_test']['logged_in'] = true;
ob_start();
$widget->widget( array( 'before_widget' => '<section>', 'after_widget' => '</section>' ), array( 'hide_for_members' => 1 ) );
$hidden = ob_get_clean();
echo ( '' === trim( $hidden ) ? 'OK    ' : 'FAIL  ' ) . "widget: скрыт от авторизованных\n";
if ( '' !== trim( $hidden ) ) {
	$fail++;
}

// Блок Gutenberg wppa/auth.
$GLOBALS['wppa_test']['logged_in'] = false;
WPPA_Blocks::register();

$block_html = WPPA_Blocks::render(
	array(
		'mode'      => 'both',
		'showTitle' => true,
		'title'     => 'Вход на сайт',
	)
);

$block_checks = array(
	'обёртка блока'            => false !== strpos( $block_html, 'wp-block-wppa-auth' ),
	'заголовок блока'          => false !== strpos( $block_html, 'Вход на сайт' ),
	'формы внутри блока'       => false !== strpos( $block_html, 'wppa-panel-login' ),
	'стили подключены'         => ! empty( $GLOBALS['wppa_test']['styles']['wppa-account'] ),
);

foreach ( $block_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'block: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// Блок скрыт от авторизованных при hideForMembers.
$GLOBALS['wppa_test']['logged_in'] = true;
$block_hidden = WPPA_Blocks::render( array( 'hideForMembers' => true ) );
echo ( '' === $block_hidden ? 'OK    ' : 'FAIL  ' ) . "block: скрыт от авторизованных\n";
if ( '' !== $block_hidden ) {
	$fail++;
}

// Шорткод [wppa_auth].
$GLOBALS['wppa_test']['logged_in'] = false;
$short = WPPA_Shortcodes::auth( array( 'mode' => 'login' ) );
$short_ok = false !== strpos( $short, 'wppa-panel-login' ) && false === strpos( $short, 'wppa-panel-register' );
echo ( $short_ok ? 'OK    ' : 'FAIL  ' ) . "shortcode: [wppa_auth mode=login] выводит только вход\n";
if ( ! $short_ok ) {
	$fail++;
}

$GLOBALS['wppa_test']['logged_in'] = true;

// Проверка утилит.
echo "\nВкладки: " . implode( ', ', array_keys( WPPA_Template::tabs() ) ) . "\n";
echo 'URL вкладки: ' . WPPA_Plugin::tab_url( 'security' ) . "\n";
echo 'Сообщение: ' . print_r( WPPA_Notices::registry()['profile_saved'], true );

echo $fail ? "\nПРОБЛЕМ: {$fail}\n" : "\nВсе шаблоны отрисованы без ошибок\n";
exit( $fail ? 1 : 0 );
