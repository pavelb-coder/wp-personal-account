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
require $base . 'includes/class-wppa-shortcodes.php';

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

	WPPA_Teams::dissolve( $team_id );
	echo 'team: после роспуска команда ' . ( get_post( $team_id ) ? 'осталась' : 'удалена' ) . ", мета пользователя «" . get_user_meta( 7, WPPA_Teams::META_TEAM, true ) . "»\n";
}

// Проверка утилит.
echo "\nВкладки: " . implode( ', ', array_keys( WPPA_Template::tabs() ) ) . "\n";
echo 'URL вкладки: ' . WPPA_Plugin::tab_url( 'security' ) . "\n";
echo 'Сообщение: ' . print_r( WPPA_Notices::registry()['profile_saved'], true );

echo $fail ? "\nПРОБЛЕМ: {$fail}\n" : "\nВсе шаблоны отрисованы без ошибок\n";
exit( $fail ? 1 : 0 );
