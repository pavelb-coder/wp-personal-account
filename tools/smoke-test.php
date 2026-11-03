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
require $base . 'includes/class-wppa-shortcodes.php';

// Обход автозагрузки шаблонов: путь к templates.
$user  = new WP_User();
$tabs  = WPPA_Template::all_tabs();
$cases = array(
	'account'         => array( 'user' => $user, 'tabs' => $tabs, 'current_tab' => 'dashboard' ),
	'tab-dashboard'   => array( 'user' => $user, 'tabs' => $tabs ),
	'tab-profile'     => array( 'user' => $user, 'tabs' => $tabs ),
	'tab-security'    => array( 'user' => $user, 'tabs' => $tabs ),
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

// Проверка утилит.
echo "\nВкладки: " . implode( ', ', array_keys( WPPA_Template::tabs() ) ) . "\n";
echo 'URL вкладки: ' . WPPA_Plugin::tab_url( 'security' ) . "\n";
echo 'Сообщение: ' . print_r( WPPA_Notices::registry()['profile_saved'], true );

echo $fail ? "\nПРОБЛЕМ: {$fail}\n" : "\nВсе шаблоны отрисованы без ошибок\n";
exit( $fail ? 1 : 0 );
