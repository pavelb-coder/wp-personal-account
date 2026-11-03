<?php
/**
 * Дымовой тест админ-панели: рендер страниц «Обзор», «Аккаунты», «Инструменты».
 *
 * Запуск: php tools/admin-smoke-test.php
 *
 * @package WP_Personal_Account
 */

require __DIR__ . '/wp-admin-stubs.php';

$base = dirname( __DIR__ ) . '/';

define( 'WPPA_VERSION', '1.0.0' );
define( 'WPPA_FILE', $base . 'wp-personal-account.php' );
define( 'WPPA_PATH', $base );
define( 'WPPA_URL', 'https://example.com/wp-content/plugins/wp-personal-account/' );

require $base . 'includes/class-wppa-plugin.php';
require $base . 'includes/class-wppa-template.php';
require $base . 'includes/class-wppa-notices.php';
require $base . 'includes/class-wppa-account.php';
require $base . 'includes/class-wppa-profile.php';
require $base . 'includes/class-wppa-admin.php';

$fail = 0;

function wppa_check_output( $name, $callback, &$fail ) {
	ob_start();
	try {
		$callback();
		$html = ob_get_clean();
	} catch ( Throwable $e ) {
		ob_end_clean();
		echo "FAIL  {$name}: " . $e->getMessage() . "\n";
		$fail++;
		return;
	}

	if ( preg_match( '/Warning:|Deprecated:|Fatal error|Undefined (variable|array key|property)/i', $html ) ) {
		preg_match( '/(Warning|Deprecated|Undefined)[^<]*/', $html, $m );
		echo "WARN  {$name}: " . trim( $m[0] ) . "\n";
		$fail++;
		return;
	}

	echo "OK    {$name} — " . strlen( $html ) . " байт\n";
}

// Страницы панели.
wppa_check_output( 'admin/dashboard', static function () { WPPA_Admin::page_dashboard(); }, $fail );
wppa_check_output( 'admin/accounts', static function () { WPPA_Admin::page_accounts(); }, $fail );
wppa_check_output( 'admin/tools', static function () { WPPA_Admin::page_tools(); }, $fail );

// Расчётные методы.
$stats = WPPA_Admin::stats();
echo "\nstats: пользователей {$stats['total_users']}, новых за 30 дней {$stats['new_30']}, ждут подтверждения email {$stats['pending_email']}\n";

$chart = WPPA_Admin::registrations_chart( 14 );
echo 'chart: точек ' . count( $chart['items'] ) . ", максимум {$chart['max']}\n";

$status = WPPA_Admin::system_status();
echo 'status: страница «' . $status['account_page']['label'] . '», регистрация «' . $status['registration']['label'] . "»\n";

// Меню: проверяем, что страницы зарегистрированы без ошибок.
WPPA_Admin::menu();
WPPA_Admin::assets( 'toplevel_page_wppa-dashboard' );
echo "menu/assets: ок\n";

echo $fail ? "\nПРОБЛЕМ: {$fail}\n" : "\nАдмин-панель отрисована без ошибок\n";
exit( $fail ? 1 : 0 );
