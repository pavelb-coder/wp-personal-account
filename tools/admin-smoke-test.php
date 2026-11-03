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

require $base . 'includes/class-wppa-time.php';
require $base . 'includes/class-wppa-plugin.php';
require $base . 'includes/class-wppa-template.php';
require $base . 'includes/class-wppa-notices.php';
require $base . 'includes/class-wppa-fields.php';
require $base . 'includes/class-wppa-password.php';
require $base . 'includes/class-wppa-link-button.php';
require $base . 'includes/class-wppa-account.php';
require $base . 'includes/class-wppa-profile.php';
require $base . 'includes/class-wppa-teams.php';
require $base . 'includes/class-wppa-builder.php';
require $base . 'includes/class-wppa-export.php';
require $base . 'includes/class-wppa-export-folder.php';
require $base . 'includes/class-wppa-smb.php';
require $base . 'includes/class-wppa-olympiad.php';
require $base . 'includes/class-wppa-reset.php';
require $base . 'includes/class-wppa-admin.php';
require $base . 'includes/class-wppa-settings.php';

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
wppa_check_output( 'admin/teams', static function () { WPPA_Admin::page_teams(); }, $fail );
wppa_check_output( 'admin/builder', static function () { WPPA_Builder::page(); }, $fail );

// Расчётные методы.
$stats = WPPA_Admin::stats();
echo "\nstats: пользователей {$stats['total_users']}, новых за 30 дней {$stats['new_30']}, ждут подтверждения email {$stats['pending_email']}\n";

$chart = WPPA_Admin::registrations_chart( 14 );
echo 'chart: точек ' . count( $chart['items'] ) . ", максимум {$chart['max']}\n";
echo "teams: команд {$stats['teams']}, участников {$stats['team_members']}\n";

// Страница «Команды»: редактируемое поле лимита.
$demo_team_id = WPPA_Teams::create( 'Команда для теста', 7 );
$demo_member  = new WP_User( 8 );
update_user_meta( 8, WPPA_Teams::META_TEAM, $demo_team_id );
update_user_meta( 8, WPPA_Teams::META_ROLE, 'member' );
WPPA_Teams::set_team_limit( $demo_team_id, 5 );
ob_start();
WPPA_Admin::page_teams();
$teams_html = ob_get_clean();
WPPA_Teams::set_team_min( $demo_team_id, 3 );

ob_start();
WPPA_Settings::render_page();
$settings_html = ob_get_clean();

ob_start();
WPPA_Admin::page_tools();
$tools_html = ob_get_clean();

// ---------- Экспорт аккаунтов ----------
$export_groups = WPPA_Export::groups();
$export_checks = array(
	'группы: пять блоков'              => 5 === count( $export_groups ) && isset( $export_groups['team'], $export_groups['personal'] ),
	'колонки: учётная запись идёт первой' => 'account' === WPPA_Export::columns()[0]['group'],
	'колонки: порядок групп'           => array( 'account', 'profile', 'personal', 'team', 'status' ) === array_values( array_unique( array_column( WPPA_Export::columns(), 'group' ) ) ),
);

$account_only = WPPA_Export::columns( array( 'account' ) );
$export_checks['колонки: только выбранная группа'] = 5 === count( $account_only ) && ! in_array( 'personal', array_column( $account_only, 'group' ), true );

$header    = WPPA_Export::header( WPPA_Export::columns() );
$header_rows = explode( "\n", trim( $header ) );
$export_checks['заголовок: две строки']          = 2 === count( $header_rows );
$export_checks['заголовок: названия групп']      = false !== strpos( $header_rows[0], 'Учётная запись' ) && false !== strpos( $header_rows[0], 'Команда' );
$export_checks['заголовок: подписи полей']       = false !== strpos( $header_rows[1], 'Логин' ) && false !== strpos( $header_rows[1], 'Роль в команде' );

$export_user = new WP_User( 7 );
$export_row  = WPPA_Export::row( $export_user );
$export_checks['строка: логин и email']       = 'ivan' === $export_row['login'] && 'ivan@example.com' === $export_row['email'];
$export_checks['строка: роль WordPress']      = 'subscriber' === $export_row['role'];
$no_team_row = WPPA_Export::row( new WP_User( 999 ) );
$export_checks['строка: без команды поля пустые'] = '' === $no_team_row['team'] && '' === $no_team_row['team_role'] && '' === $no_team_row['team_complete'];

$csv = WPPA_Export::csv( array( 'users' => array( $export_user ), 'groups' => array( 'account', 'profile' ) ) );
$csv_lines = explode( "\n", trim( $csv ) );
$export_checks['csv: BOM в начале']            = 0 === strpos( $csv, "\xEF\xBB\xBF" );
$export_checks['csv: заголовок + одна строка'] = 3 === count( $csv_lines );
$export_checks['csv: разделитель точка с запятой'] = false !== strpos( $csv_lines[2], ';' );
$export_checks['csv: личные данные не попали'] = false === strpos( $csv, 'Личные данные' );

$csv_comma = WPPA_Export::csv( array( 'users' => array( $export_user ), 'groups' => array( 'account' ), 'delimiter' => ',' ) );
$export_checks['csv: разделитель запятая']     = false !== strpos( explode( "\n", trim( $csv_comma ) )[2], ',' );

// Экранирование значения с разделителем.
$quoted_user = new WP_User( 7 );
$quoted_user->description = 'Люблю; доставку';
$csv_quoted  = WPPA_Export::csv( array( 'users' => array( $quoted_user ), 'groups' => array( 'profile' ) ) );
$export_checks['csv: значение с ; экранируется'] = false !== strpos( $csv_quoted, '"Люблю; доставку"' );

// Фильтры по составу.
$export_checks['кого: четыре варианта'] = 4 === count( WPPA_Export::scopes() );
$export_checks['файл: имя с датой']     = 1 === preg_match( '/^wppa-accounts-\d{8}-\d{6}\.csv$/', WPPA_Export::filename() );

foreach ( $export_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'export: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// ---------- Экспорт в сетевую папку ----------
$folder_dir = sys_get_temp_dir() . '/wppa-export-test-' . gmdate( 'His' );
wp_mkdir_p( $folder_dir );

$folder_checks = array(
	'расписания: пять вариантов'  => 5 === count( WPPA_Export_Folder::schedules() ),
	'имя файла: подстановка даты' => 1 === preg_match( '/^wppa-accounts-\d{4}-\d{2}-\d{2}\.csv$/', WPPA_Export_Folder::filename( 'wppa-accounts-{date}.csv' ) ),
	'имя файла: расширение .csv'  => '.csv' === substr( WPPA_Export_Folder::filename( 'accounts' ), -4 ),
	'имя файла: подстановки раскрыты' => false === strpos( WPPA_Export_Folder::filename( '{site}-{time}.csv' ), '{' ),
	'схема smb:// отклоняется'     => '' === WPPA_Export_Folder::normalize_path( 'smb://server/share' ),
	'обычный путь нормализуется'   => '/mnt/share/wppa' === WPPA_Export_Folder::normalize_path( '/mnt/share/wppa/' ),
	'несуществующая папка — ошибка' => false === WPPA_Export_Folder::check_folder( $folder_dir . '/nope' )['ok'],
	'схема smb:// — ошибка проверки' => false === WPPA_Export_Folder::check_folder( 'smb://server/share' )['ok'],
	'существующая папка доступна'  => true === WPPA_Export_Folder::check_folder( $folder_dir )['ok'],
);

$folder_config = array(
	'path'      => $folder_dir,
	'file'      => 'accounts-{date}.csv',
	'groups'    => WPPA_Export::default_groups(),
	'scope'     => 'all',
	'delimiter' => ';',
	'limit'     => 100,
	'keep'      => 2,
);

$written = WPPA_Export_Folder::write( $folder_config );
$folder_checks['файл записан']        = $written['ok'] && file_exists( $written['file'] );
$folder_checks['файл не пустой']      = $written['bytes'] > 0;
$folder_checks['содержимое — CSV']    = false !== strpos( (string) file_get_contents( $written['file'] ), 'Учётная запись' );
$folder_checks['временный файл убран'] = ! file_exists( $written['file'] . '.tmp' );

// Ротация: три файла при keep = 2.
file_put_contents( $folder_dir . '/old-1.csv', 'a' );
file_put_contents( $folder_dir . '/old-2.csv', 'b' );
touch( $folder_dir . '/old-1.csv', time() - 300 );
touch( $folder_dir . '/old-2.csv', time() - 200 );
$rotated = WPPA_Export_Folder::rotate( $folder_dir, 2 );
$folder_checks['ротация удаляет лишние'] = $rotated >= 1 && count( (array) glob( $folder_dir . '/*.csv' ) ) === 2;
$folder_checks['keep = 0 — не удаляет']  = 0 === WPPA_Export_Folder::rotate( $folder_dir, 0 );

// Журнал.
$folder_log = WPPA_Export_Folder::logs();
$folder_checks['журнал пишется']       = ! empty( $folder_log ) && $folder_log[0]['ok'];
$folder_checks['журнал помнит файл']   = isset( $folder_log[0]['file'] ) && $folder_log[0]['file'] === $written['file'];

// Ошибка записи попадает в журнал и не роняет вывод.
$bad = WPPA_Export_Folder::write( array_merge( $folder_config, array( 'path' => $folder_dir . '/nope' ) ) );
$folder_checks['плохой путь — ok=false'] = false === $bad['ok'] && '' !== $bad['message'];
$folder_checks['плохой путь в журнале']  = false === WPPA_Export_Folder::logs()[0]['ok'];
delete_option( WPPA_Export_Folder::LOG_OPTION );

// Расписание.
$GLOBALS['wppa_test']['cron'] = array();
update_option(
	'wppa_settings',
	array_merge(
		(array) get_option( 'wppa_settings', array() ),
		array(
			'export_folder' => array_merge(
				$folder_config,
				array( 'enabled' => 1, 'schedule' => 'daily', 'time' => '03:00' )
			),
		)
	)
);

WPPA_Export_Folder::reschedule();
$folder_checks['расписание поставлено']  = WPPA_Export_Folder::next_run() > 0;
$folder_checks['периодичность daily']    = 'daily' === wp_get_schedule( WPPA_Export_Folder::CRON_HOOK );
$folder_checks['первый запуск в будущем'] = WPPA_Export_Folder::first_run_timestamp( 'daily', '03:00' ) > time();
$folder_checks['hourly — без времени']   = WPPA_Export_Folder::first_run_timestamp( 'hourly', '03:00' ) < time() + 120;

update_option(
	'wppa_settings',
	array_merge(
		(array) get_option( 'wppa_settings', array() ),
		array( 'export_folder' => array_merge( $folder_config, array( 'enabled' => 0 ) ) )
	)
);

WPPA_Export_Folder::reschedule();
$folder_checks['выключено — расписание снято'] = 0 === WPPA_Export_Folder::next_run();

// Настройки папки: поля и сохранение.
$folder_fields_ok = false !== strpos( $settings_html, 'wppa_settings[export_folder][path]' )
	&& false !== strpos( $settings_html, 'wppa_settings[export_folder][schedule]' )
	&& false !== strpos( $settings_html, 'wppa_settings[export_folder][keep]' );

$folder_checks['настройки: поля на месте'] = $folder_fields_ok;

$folder_sanitize = WPPA_Settings::sanitize(
	array(
		'export_folder' => array(
			'enabled'  => '1',
			'path'     => '/mnt/share/wppa/',
			'file'     => 'accounts-{date}.csv',
			'schedule' => 'weekly',
			'time'     => '25:99',
			'keep'     => '999',
			'scope'    => 'unknown',
		),
	)
);

$folder_checks['настройки: путь сохранён']      = '/mnt/share/wppa/' === $folder_sanitize['export_folder']['path'];
$folder_checks['настройки: шаблон с токеном']   = 'accounts-{date}.csv' === $folder_sanitize['export_folder']['file'];
$folder_checks['настройки: расписание weekly']  = 'weekly' === $folder_sanitize['export_folder']['schedule'];
$folder_checks['настройки: мусор времени → 03:00'] = '03:00' === $folder_sanitize['export_folder']['time'];
$folder_checks['настройки: keep ограничен']     = 365 === $folder_sanitize['export_folder']['keep'];
$folder_checks['настройки: неизвестный scope → all'] = 'all' === $folder_sanitize['export_folder']['scope'];

foreach ( (array) glob( $folder_dir . '/*' ) as $folder_file_path ) {
	@unlink( $folder_file_path );
}
@rmdir( $folder_dir );

foreach ( $folder_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'export folder: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// ---------- Кнопки сетевой папки ----------
$folder_buttons_checks = array(
	'настройки: кнопка проверки подключения' => false !== strpos( $settings_html, 'Проверить подключение к сетевой папке' )
		&& false !== strpos( $settings_html, 'value="folder_verify"' ),
	'настройки: кнопка экспорта'            => false !== strpos( $settings_html, 'value="export_folder_now"' )
		&& false !== strpos( $settings_html, '>Экспортировать<' ),
	'настройки: карточка сетевой папки'      => false !== strpos( $settings_html, 'Папка на сервере:' ),
	'инструменты: кнопка проверки подключения' => false !== strpos( $tools_html, 'value="folder_verify"' ),
	'инструменты: кнопка экспорта'           => false !== strpos( $tools_html, '>Экспортировать<' ),
	'уведомления: проверка подключения'      => ( function () {
		$_GET['wppa_done'] = 'folder_verify_ok';
		$ok = WPPA_Admin::tool_notice();
		$_GET['wppa_done'] = 'folder_verify_fail';
		$bad = WPPA_Admin::tool_notice();
		unset( $_GET['wppa_done'] );
		return is_array( $ok ) && 'success' === $ok[0] && is_array( $bad ) && 'error' === $bad[0];
	} )(),
	'настройки: поля сетевой папки'          => false !== strpos( $settings_html, 'wppa_settings[smb][path]' )
		&& false !== strpos( $settings_html, 'wppa_settings[smb][user]' )
		&& false !== strpos( $settings_html, 'wppa_settings[smb][pass]' ),
	'настройки: переключатель способа записи' => false !== strpos( $settings_html, 'wppa_settings[export_folder][mode]' ),
);

delete_option( WPPA_Export_Folder::LOG_OPTION );

foreach ( $folder_buttons_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'folder buttons: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}


// ---------- Сетевая локальная папка с авторизацией ----------
$unc = WPPA_SMB::parse_path( '\\\\fileserver\\exchange\\reports\\wppa' );
$smb_checks = array(
	'разбор UNC: хост'          => 'fileserver' === $unc['host'],
	'разбор UNC: шара'          => 'exchange' === $unc['share'],
	'разбор UNC: вложенная папка' => 'reports/wppa' === $unc['subdir'],
	'разбор UNC: unc и service' => '\\\\fileserver\\exchange\\reports\\wppa' === $unc['unc'] && '//fileserver/exchange' === $unc['service'],
);

$url_smb = WPPA_SMB::parse_path( 'smb://WORKGROUP:secret@nas.local/share/docs' );
$smb_checks['разбор smb://: хост']          = 'nas.local' === $url_smb['host'] && 'share' === $url_smb['share'];
$smb_checks['разбор smb://: папка']         = 'docs' === $url_smb['subdir'];
$smb_checks['разбор smb://: логин из URL']  = 'WORKGROUP' === $url_smb['user'] && 'secret' === $url_smb['password'];
$smb_checks['разбор мусора']                = null === WPPA_SMB::parse_path( 'fileserver' );

$smb_checks['тип: UNC — это smb']              = 'smb' === WPPA_SMB::detect_type( array( 'path' => '\\\\fs\\share' ) );
$smb_checks['тип: smb:// — это smb']           = 'smb' === WPPA_SMB::detect_type( array( 'path' => 'smb://fs/share' ) );
$smb_checks['тип: //fs/share — это smb']       = 'smb' === WPPA_SMB::detect_type( array( 'path' => '//fs/share' ) );
$smb_checks['тип: /mnt/share — файловая система'] = 'filesystem' === WPPA_SMB::detect_type( array( 'path' => '/mnt/share' ) );

$split = WPPA_SMB::split_user( 'WORKGROUP\\svc_wppa' );
$smb_checks['логин и домен: DOMAIN\\user'] = 'WORKGROUP' === $split['domain'] && 'svc_wppa' === $split['user'];
$smb_checks['логин без домена']            = '' === WPPA_SMB::split_user( 'svc_wppa' )['domain'];
$smb_checks['логин из URL, если пусто']     = 'svc' === WPPA_SMB::split_user( '', 'svc' )['user'];

// Проверка записи в локальную папку.
$local_dir = sys_get_temp_dir() . '/wppa-smb-local-' . uniqid();
mkdir( $local_dir, 0777, true );
$local_ok = WPPA_SMB::test( array( 'path' => $local_dir, 'user' => '', 'pass' => '' ) );
$smb_checks['локальная папка: доступ есть']      = $local_ok['ok'] && 'filesystem' === $local_ok['method'];
$smb_checks['локальная папка: пробный файл убран'] = 0 === count( glob( $local_dir . '/.wppa-probe-*' ) );
$smb_checks['локальная папка: без пути ошибка']   = ! WPPA_SMB::test( array( 'path' => '', 'user' => '', 'pass' => '' ) )['ok'];

// Пароль из константы важнее сохранённого.
define( 'WPPA_SMB_PASSWORD', 'from-constant' );
$smb_checks['пароль из константы'] = 'from-constant' === WPPA_SMB::password( 'from-db' );

// Команда монтирования не содержит пароля.
$mount = WPPA_SMB::mount_command( array( 'path' => '\\\\fs\\share\\dir', 'user' => 'WORKGROUP\\svc', 'pass' => 'secret-pass' ) );
$smb_checks['монтирование: credentials-файл'] = false !== strpos( $mount['command'], 'credentials=' )
	&& false === strpos( $mount['command'], 'secret-pass' );
$smb_checks['монтирование: домен в файле']    = false !== strpos( $mount['credentials'], 'domain=WORKGROUP' );

// Без smbclient и без монтирования — понятная ошибка с командой.
add_filter( 'wppa_smb_runner', function ( $result, $command ) {
	$GLOBALS['wppa_test']['smb_commands'][] = $command;

	if ( false !== strpos( $command, 'command -v smbclient' ) ) {
		return array( 'code' => 1, 'output' => '' );
	}

	return array( 'code' => 0, 'output' => '' );
}, 10, 2 );

$GLOBALS['wppa_test']['smb_commands'] = array();
$no_tools = WPPA_SMB::test( array( 'path' => '\\\\fileserver\\exchange\\reports', 'user' => 'svc', 'pass' => 'x' ) );
$smb_checks['нет средств: подсказка про smbclient'] = ! $no_tools['ok'] && false !== strpos( $no_tools['message'], 'smbclient' );

$no_write = WPPA_SMB::write( 'accounts.csv', "id\n", array( 'path' => '\\\\fileserver\\exchange', 'user' => 'svc', 'pass' => 'x' ) );
$smb_checks['нет средств: в ошибке есть mount'] = ! $no_write['ok'] && false !== strpos( $no_write['message'], 'mount -t cifs' );

// Есть smbclient: проверка и запись с паролем в файле 0600.
add_filter( 'wppa_smb_runner', function ( $result, $command ) {
	$GLOBALS['wppa_test']['smb_commands'][] = $command;

	if ( false !== strpos( $command, 'command -v smbclient' ) ) {
		return array( 'code' => 0, 'output' => '/usr/bin/smbclient' );
	}

	return array( 'code' => 0, 'output' => '  blocks available' );
}, 20, 2 );

add_filter( 'wppa_smb_port_check', function () {
	return true;
} );

$GLOBALS['wppa_test']['smb_commands'] = array();
$cli_config = array( 'path' => '\\\\fileserver\\exchange\\reports', 'user' => 'WORKGROUP\\svc_wppa', 'pass' => 'secret-pass' );
$cli_test   = WPPA_SMB::test( $cli_config );

$smb_checks['smbclient: доступ получен']        = $cli_test['ok'] && 'cli' === $cli_test['method'];
$smb_checks['smbclient: вход в подпапку']       = false !== strpos( implode( "\n", $GLOBALS['wppa_test']['smb_commands'] ), 'cd' );
$smb_checks['кавычки smbclient: двойные']        = '"reports"' === WPPA_SMB::quote_arg( 'reports' );
$smb_checks['кавычки smbclient: без одинарных']  = false === strpos( WPPA_SMB::quote_arg( 'olimp' ), "'" );
$smb_checks['кавычки smbclient: двойная кавычка в имени убрана'] = '"ab"' === WPPA_SMB::quote_arg( 'a"b' );
$smb_checks['smbclient: пароль не в командной строке'] = false === strpos( implode( "\n", $GLOBALS['wppa_test']['smb_commands'] ), 'secret-pass' );
$smb_checks['smbclient: файл учётных данных после проверки удалён'] = 0 === count( glob( sys_get_temp_dir() . '/wppa_smb_creds_*' ) );

$GLOBALS['wppa_test']['smb_commands'] = array();
$cli_write = WPPA_SMB::write( 'accounts.csv', "id;email\n1;a@b.c\n", $cli_config );
$smb_checks['smbclient: файл записан']    = $cli_write['ok'] && false !== strpos( $cli_write['file'], '\\\\fileserver\\exchange\\reports\\accounts.csv' );
$smb_checks['smbclient: в команде put']   = false !== strpos( implode( "\n", $GLOBALS['wppa_test']['smb_commands'] ), 'put ' );

// Без логина и пароля — анонимный доступ -N.
add_filter( 'wppa_smb_runner', function ( $result, $command ) {
	if ( false !== strpos( $command, 'command -v smbclient' ) ) {
		return array( 'code' => 0, 'output' => '/usr/bin/smbclient' );
	}

	$GLOBALS['wppa_test']['anon_command'] = $command;

	return array( 'code' => 0, 'output' => '' );
}, 30, 2 );

WPPA_SMB::test( array( 'path' => '\\\\fs\\share', 'user' => '', 'pass' => '' ) );
$smb_checks['без учётных данных: ключ -N'] = false !== strpos( (string) ( $GLOBALS['wppa_test']['anon_command'] ?? '' ), '-N' );

// Папки внутри шары нет: проверка объясняет, запись создаёт её и повторяет.
add_filter( 'wppa_smb_runner', function ( $result, $command ) {
	if ( false !== strpos( $command, 'command -v smbclient' ) ) {
		return array( 'code' => 0, 'output' => '/usr/bin/smbclient' );
	}

	$GLOBALS['wppa_test']['missing_commands'][] = $command;

	if ( false !== strpos( $command, 'mkdir' ) ) {
		$GLOBALS['wppa_test']['mkdir_used'] = true;

		return array( 'code' => 0, 'output' => '' );
	}

	return array( 'code' => 1, 'output' => 'cd "olimp"\: NT_STATUS_OBJECT_NAME_NOT_FOUND listing \\fs\share' );
}, 50, 2 );

$GLOBALS['wppa_test']['missing_commands'] = array();
$GLOBALS['wppa_test']['mkdir_used']       = false;

$missing_cfg    = array( 'path' => '\\\\fs\\share\\olimp', 'user' => 'svc', 'pass' => 'p' );
$missing_test   = WPPA_SMB::test( $missing_cfg );
$missing_write  = WPPA_SMB::write( 'accounts.csv', "id\n", $missing_cfg );

$smb_checks['нет папки в шаре: проверка объясняет'] = ! $missing_test['ok'] && false !== strpos( $missing_test['message'], 'папки' );
$smb_checks['нет папки в шаре: запись создала папку'] = $missing_write['ok'] && true === $GLOBALS['wppa_test']['mkdir_used'];
$smb_checks['нет папки в шаре: в отчёте про создание'] = false !== strpos( $missing_write['message'], 'создана' );
$smb_checks['нет папки: повтор с mkdir и cd']        = false !== strpos( implode( "\n", $GLOBALS['wppa_test']['missing_commands'] ), 'mkdir "olimp"; cd "olimp";' );

// Отказ авторизации от сервера.
add_filter( 'wppa_smb_runner', function ( $result, $command ) {
	if ( false !== strpos( $command, 'command -v smbclient' ) ) {
		return array( 'code' => 0, 'output' => '/usr/bin/smbclient' );
	}

	return array( 'code' => 1, 'output' => 'NT_STATUS_LOGON_FAILURE listing \\\\fs\\share' );
}, 70, 2 );

$denied = WPPA_SMB::test( array( 'path' => '\\\\fs\\share', 'user' => 'svc', 'pass' => 'bad' ) );
$smb_checks['отказ сервера: сообщение про логин'] = ! $denied['ok'] && false !== strpos( $denied['message'], 'логин' );

// Выгрузка в режиме SMB идёт через WPPA_SMB и пишет журнал.
$smb_snapshot = get_option( 'wppa_settings', array() );
update_option( 'wppa_settings', array_merge( (array) $smb_snapshot, array( 'smb' => array( 'path' => $local_dir, 'user' => '', 'pass' => '' ) ) ) );

$smb_export = WPPA_Export_Folder::write(
	array_merge(
		WPPA_Export_Folder::config(),
		array(
			'mode'   => 'smb',
			'file'   => 'accounts-{date}.csv',
			'groups' => WPPA_Export::default_groups(),
			'keep'   => 0,
		)
	)
);

$smb_checks['выгрузка в режиме SMB']    = $smb_export['ok'] && 0 < $smb_export['bytes'];
$smb_checks['выгрузка в режиме SMB: файл на диске'] = 1 === count( glob( $local_dir . '/accounts-*.csv' ) );
$smb_checks['журнал помнит выгрузку SMB'] = ! empty( WPPA_Export_Folder::logs() );
update_option( 'wppa_settings', $smb_snapshot );

$smb_sanitize = WPPA_Settings::sanitize(
	array(
		'smb'           => array( 'path' => "\\\\fileserver\\exchange\0\\reports", 'user' => 'WORKGROUP\\svc', 'pass' => 'p@ss word' ),
		'export_folder' => array( 'mode' => 'smb' ),
	)
);

$smb_checks['настройки: путь сохранён']    = '\\\\fileserver\\exchange\\reports' === $smb_sanitize['smb']['path'];
$smb_checks['настройки: логин сохранён']   = 'WORKGROUP\\svc' === $smb_sanitize['smb']['user'];
$smb_checks['настройки: пароль сохранён']  = 'p@ss word' === $smb_sanitize['smb']['pass'];
$smb_checks['настройки: режим smb']        = 'smb' === $smb_sanitize['export_folder']['mode'];
$smb_checks['настройки: режим по умолчанию path'] = 'path' === WPPA_Export_Folder::defaults()['mode'];

delete_option( WPPA_Export_Folder::LOG_OPTION );

foreach ( $smb_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'smb: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}


// ---------- Выгрузка по изменениям ----------
$change_dir = sys_get_temp_dir() . '/wppa-change-' . uniqid();
mkdir( $change_dir, 0777, true );

$change_base = array_merge(
	WPPA_Export_Folder::defaults(),
	array(
		'enabled'      => 1,
		'mode'         => 'path',
		'path'         => $change_dir,
		'file'         => 'accounts-{datetime}.csv',
		'keep'         => 0,
		'on_change'    => 0,
		'change_delay' => 5,
	)
);

$change_checks = array();

$snapshot_all = get_option( 'wppa_settings', array() );

// 1. По умолчанию выключено — ничего не планируется и не записывается.
update_option( 'wppa_settings', array_merge( (array) $snapshot_all, array( 'export_folder' => $change_base ) ) );
unset( $GLOBALS['wppa_test']['cron'][ WPPA_Export_Folder::CHANGE_HOOK ] );
delete_option( WPPA_Export_Folder::PENDING_OPTION );

$change_checks['выключено: изменение не запоминается'] = false === WPPA_Export_Folder::note_change( 'тест' )
	&& array() === WPPA_Export_Folder::pending();
$change_checks['выключено: событие не создано']        = false === wp_next_scheduled( WPPA_Export_Folder::CHANGE_HOOK );

// 2. Включено с задержкой: одно событие на серию изменений.
update_option(
	'wppa_settings',
	array_merge(
		(array) $snapshot_all,
		array( 'export_folder' => array_merge( $change_base, array( 'on_change' => 1 ) ) )
	)
);
delete_option( WPPA_Export_Folder::PENDING_OPTION );
unset( $GLOBALS['wppa_test']['cron'][ WPPA_Export_Folder::CHANGE_HOOK ] );

$before = time();
WPPA_Export_Folder::note_change( 'регистрация пользователя #1' );
$first_schedule = WPPA_Export_Folder::next_change();
sleep( 1 );
WPPA_Export_Folder::note_change( 'профиль пользователя #2' );

$change_checks['задержка 5 минут: событие стоит']   = $first_schedule >= $before + 5 * MINUTE_IN_SECONDS - 2;
$change_checks['повтор не сдвигает время']          = $first_schedule === WPPA_Export_Folder::next_change();
$change_checks['причины накапливаются']             = 2 === count( WPPA_Export_Folder::pending() );
$change_checks['дубли причин не плодятся']          = ( function () {
	WPPA_Export_Folder::note_change( 'профиль пользователя #2' );
	return 2 === count( WPPA_Export_Folder::pending() );
} )();
$change_checks['файл пока не пишется']              = 0 === count( glob( $change_dir . '/*.csv' ) );

// 3. Запуск по событию: файл, журнал, очистка.
$change_run = WPPA_Export_Folder::run_change();
$change_checks['запуск по изменению: файл записан']  = $change_run['ok'] && 1 === count( glob( $change_dir . '/*.csv' ) );
$change_checks['запуск по изменению: ожидание сброшено'] = array() === WPPA_Export_Folder::pending();
$change_checks['запуск по изменению: событие снято']      = false === wp_next_scheduled( WPPA_Export_Folder::CHANGE_HOOK );

$change_log = WPPA_Export_Folder::logs();
$change_checks['журнал: причина записана'] = ! empty( $change_log ) && false !== strpos( $change_log[0]['message'], 'по изменению' );

$change_checks['повторный запуск без изменений'] = false === WPPA_Export_Folder::run_change()['ok'];

// 4. Задержка 0 — выгрузка сразу.
update_option(
	'wppa_settings',
	array_merge(
		(array) $snapshot_all,
		array( 'export_folder' => array_merge( $change_base, array( 'on_change' => 1, 'change_delay' => 0 ) ) )
	)
);
delete_option( WPPA_Export_Folder::PENDING_OPTION );
array_map( 'unlink', glob( $change_dir . '/*.csv' ) );

WPPA_Export_Folder::note_change( 'регистрация пользователя #3' );
$change_checks['задержка 0: файл записан сразу'] = 1 === count( glob( $change_dir . '/*.csv' ) );
$change_checks['задержка 0: событие не планируется'] = false === wp_next_scheduled( WPPA_Export_Folder::CHANGE_HOOK );

// 5. Какие мета-поля считаются изменением.
update_option(
	'wppa_settings',
	array_merge(
		(array) $snapshot_all,
		array( 'export_folder' => array_merge( $change_base, array( 'on_change' => 1 ) ) )
	)
);
delete_option( WPPA_Export_Folder::PENDING_OPTION );

WPPA_Export_Folder::on_meta_change( 1, 7, '_wppa_email_token' );
$change_checks['служебный токен не считается'] = array() === WPPA_Export_Folder::pending();

WPPA_Export_Folder::on_meta_change( 2, 7, 'some_other_meta' );
$change_checks['чужие мета не считаются'] = array() === WPPA_Export_Folder::pending();

WPPA_Export_Folder::on_meta_change( 3, 7, '_wppa_team_id' );
$pending_team = WPPA_Export_Folder::pending();
$change_checks['состав команды считается'] = 1 === count( $pending_team ) && false !== strpos( $pending_team[0], '_wppa_team_id' );

WPPA_Export_Folder::on_user_register( 12 );
$change_checks['регистрация считается'] = false !== strpos( implode( ' ', WPPA_Export_Folder::pending() ), 'регистрация' );

WPPA_Export_Folder::on_profile_update( 12 );
WPPA_Export_Folder::on_team_setting();
$change_checks['профиль и команды считаются'] = 4 <= count( WPPA_Export_Folder::pending() );

// 6. Выключение настройки снимает ожидание.
update_option(
	'wppa_settings',
	array_merge(
		(array) $snapshot_all,
		array( 'export_folder' => array_merge( $change_base, array( 'on_change' => 0 ) ) )
	)
);
WPPA_Export_Folder::reschedule();
$change_checks['выключение: ожидание сброшено'] = array() === WPPA_Export_Folder::pending();
$change_checks['выключение: событие снято']     = false === wp_next_scheduled( WPPA_Export_Folder::CHANGE_HOOK );

// 7. Настройки и интерфейс.
$change_sanitize = WPPA_Settings::sanitize(
	array( 'export_folder' => array( 'on_change' => '1', 'change_delay' => '15' ) )
);
$change_sanitize_bad = WPPA_Settings::sanitize(
	array( 'export_folder' => array( 'on_change' => '1', 'change_delay' => '7' ) )
);

$change_checks['настройки: включение сохраняется'] = 1 === $change_sanitize['export_folder']['on_change']
	&& 15 === $change_sanitize['export_folder']['change_delay'];
$change_checks['настройки: чужая задержка заменяется'] = 5 === $change_sanitize_bad['export_folder']['change_delay'];
$change_checks['настройки: поле в форме есть'] = false !== strpos( $settings_html, 'wppa_settings[export_folder][on_change]' )
	&& false !== strpos( $settings_html, 'wppa_settings[export_folder][change_delay]' );
$change_checks['варианты задержки'] = array( 0, 5, 15, 60 ) === array_keys( WPPA_Export_Folder::delays() );
$change_checks['по умолчанию выключено'] = 0 === WPPA_Export_Folder::defaults()['on_change'];

update_option( 'wppa_settings', $snapshot_all );
delete_option( WPPA_Export_Folder::PENDING_OPTION );
unset( $GLOBALS['wppa_test']['cron'][ WPPA_Export_Folder::CHANGE_HOOK ] );

foreach ( $change_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'on change: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}


// ---------- Баллы за тесты из плагина олимпиад ----------
global $wpdb;

$olympiad_checks = array();

// 1. Плагина нет — вкладки и блока нет, запросов к базе нет.
WPPA_Olympiad::flush();
$wpdb->data = array();
unset( $GLOBALS['wppa_test']['transients'] );

$olympiad_checks['без плагина: не активен']   = false === WPPA_Olympiad::is_active();
$olympiad_checks['без плагина: нет вкладки']  = ! array_key_exists( 'olympiad', WPPA_Template::tabs() );
$olympiad_checks['без плагина: нет блока']    = ! array_key_exists( 'olympiad-scores', WPPA_Builder::blocks() );
$olympiad_checks['без плагина: результатов нет'] = array() === WPPA_Olympiad::results( 7 );

// 2. Плагин есть: таблица найдена через SHOW TABLES.
WPPA_Olympiad::flush();
$wpdb->data  = array( 'var' => 'wp_olympiad_attempts' );
$wpdb->queries = array();

$olympiad_checks['плагин найден по таблице'] = true === WPPA_Olympiad::is_active();
$olympiad_checks['проверка кэшируется']      = false !== strpos( implode( ' ', $wpdb->queries ), 'SHOW TABLES' );

$wpdb->queries = array();
WPPA_Olympiad::is_active();
$olympiad_checks['повторный вызов без запроса'] = array() === $wpdb->queries;

// 3. Итоги: баллы только по зачтённым работам.
$wpdb->data = array(
	'var'      => 'wp_olympiad_attempts',
	'attempts' => array(
		(object) array(
			'id' => 1, 'quiz_id' => 10, 'score' => 8, 'max_score' => 10, 'percentage' => '80.00',
			'status' => 'completed', 'submitted_at' => '2026-10-28 13:51:04', 'title' => 'Математика',
		),
		(object) array(
			'id' => 2, 'quiz_id' => 11, 'score' => 15, 'max_score' => 20, 'percentage' => '75.00',
			'status' => 'completed', 'submitted_at' => '2026-10-29 10:00:00', 'title' => 'Физика',
		),
		(object) array(
			'id' => 3, 'quiz_id' => 12, 'score' => 0, 'max_score' => 5, 'percentage' => '0.00',
			'status' => 'in_progress', 'submitted_at' => '', 'title' => 'Информатика',
		),
		(object) array(
			'id' => 4, 'quiz_id' => 13, 'score' => 4, 'max_score' => 10, 'percentage' => '40.00',
			'status' => 'needs_review', 'submitted_at' => '2026-10-30 09:00:00', 'title' => 'Химия',
		),
		(object) array(
			'id' => 5, 'quiz_id' => 14, 'score' => 0, 'max_score' => 10, 'percentage' => '0.00',
			'status' => 'expired', 'submitted_at' => '2026-10-30 10:00:00', 'title' => 'История',
		),
	),
);

$results = WPPA_Olympiad::results( 7 );
$totals  = WPPA_Olympiad::totals( 7 );

$olympiad_checks['результаты: все попытки']      = 5 === count( $results );
$olympiad_checks['результаты: название теста']    = 'Математика' === $results[0]['title'];
$olympiad_checks['результаты: статус переведён']  = 'Завершено' === $results[0]['status_label'];
$olympiad_checks['итоги: зачтено работ']          = 3 === $totals['tests'];
$olympiad_checks['итоги: сумма баллов']           = 27 === $totals['score'];
$olympiad_checks['итоги: максимум']               = 40 === $totals['max_score'];
$olympiad_checks['итоги: процент']                = 67.5 === $totals['percentage'];
$olympiad_checks['итоги: лучший результат']       = 'Математика' === $totals['best']['title'];
$olympiad_checks['итоги: на проверке']            = 1 === $totals['pending'];
$olympiad_checks['незавершённые не в баллах']     = false === $results[2]['counted'] && false === $results[4]['counted'];

$summary = WPPA_Olympiad::summary( 7 );
$olympiad_checks['сводка одной строкой'] = 27 === $summary['score'] && 3 === $summary['tests'] && 67.5 === $summary['percentage'];

$olympiad_checks['запрос по конкретному пользователю'] = false !== strpos( implode( ' ', $wpdb->queries ), 'user_id = 7' );

// 4. Вкладка, блок и вывод в кабинете.
$olympiad_checks['плагин есть: вкладка появилась'] = array_key_exists( 'olympiad', WPPA_Template::tabs() );
$olympiad_checks['плагин есть: блок зарегистрирован'] = array_key_exists( 'olympiad-scores', WPPA_Builder::blocks() );
$olympiad_checks['блок стоит в раскладке вкладки'] = in_array( 'olympiad-scores', array_column( WPPA_Builder::layout()['olympiad'], 'id' ), true );

ob_start();
WPPA_Template::render(
	'tab-olympiad',
	array(
		'user' => new WP_User( 7 ),
		'tabs' => WPPA_Template::tabs(),
	)
);
$olympiad_html = ob_get_clean();

$olympiad_checks['вывод: баллы в кабинете']  = false !== strpos( $olympiad_html, '27' ) && false !== strpos( $olympiad_html, 'баллов всего' );
$olympiad_checks['вывод: список тестов']      = false !== strpos( $olympiad_html, 'Математика' ) && false !== strpos( $olympiad_html, '8 из 10' );
$olympiad_checks['вывод: статус на проверке'] = false !== strpos( $olympiad_html, 'На проверке' );
$olympiad_checks['вывод: процент']            = false !== strpos( $olympiad_html, '67.5%' );

// 5. Настройка вкладки.
update_option( 'wppa_settings', array_merge( (array) get_option( 'wppa_settings', array() ), array( 'olympiad_tab' => 0 ) ) );
$olympiad_checks['выключение вкладки работает'] = ! array_key_exists( 'olympiad', WPPA_Template::tabs() );

update_option( 'wppa_settings', array_merge( (array) get_option( 'wppa_settings', array() ), array( 'olympiad_tab' => 1 ) ) );
$olympiad_checks['включение вкладки работает'] = array_key_exists( 'olympiad', WPPA_Template::tabs() );

$olympiad_checks['настройки: поле вкладки есть'] = false !== strpos( $settings_html, 'wppa_settings[olympiad_tab]' );
$olympiad_checks['sanitize: флаг сохраняется']    = 0 === WPPA_Settings::sanitize( array() )['olympiad_tab'];
$olympiad_checks['по умолчанию вкладка включена'] = 1 === WPPA_Plugin::defaults()['olympiad_tab'];

// 6. Ручной фильтр наличия плагина.
WPPA_Olympiad::flush();
$wpdb->data = array();
add_filter( 'wppa_olympiad_active', static function ( $active ) {
	return array_key_exists( 'olympiad_force', $GLOBALS['wppa_test'] ) ? (bool) $GLOBALS['wppa_test']['olympiad_force'] : true;
} );

WPPA_Olympiad::flush();
$olympiad_checks['фильтр включает интеграцию'] = true === WPPA_Olympiad::is_active();

WPPA_Olympiad::flush();
$wpdb->data = array();
$wpdb->queries = array();

foreach ( $olympiad_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'olympiad: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}


// ---------- Баллы за тесты в выгрузке ----------
global $wpdb;

$export_olympiad_checks = array();

$wpdb->data = array(
	'var'      => 'wp_olympiad_attempts',
	'attempts' => array(
		(object) array( 'user_id' => 7, 'score' => 27, 'max_score' => 40, 'tests' => 3, 'pending' => 1 ),
		(object) array( 'user_id' => 8, 'score' => 5, 'max_score' => 10, 'tests' => 1, 'pending' => 0 ),
	),
);
WPPA_Olympiad::flush();
$wpdb->queries = array();

$export_olympiad_checks['группа появилась']  = array_key_exists( 'olympiad', WPPA_Export::groups() );
$export_olympiad_checks['группа в списке по умолчанию'] = in_array( 'olympiad', WPPA_Export::default_groups(), true );

$all_columns = array_column( WPPA_Export::columns(), 'key' );
$export_olympiad_checks['колонки баллов']    = in_array( 'olympiad_score', $all_columns, true )
	&& in_array( 'olympiad_max', $all_columns, true )
	&& in_array( 'olympiad_percentage', $all_columns, true )
	&& in_array( 'olympiad_tests', $all_columns, true )
	&& in_array( 'olympiad_pending', $all_columns, true );
$export_olympiad_checks['колонки баллов в конце'] = 'olympiad' === end( WPPA_Export::columns() )['group'];

$account_columns = array_column( WPPA_Export::columns( array( 'account' ) ), 'key' );
$export_olympiad_checks['без группы баллов нет колонок'] = ! in_array( 'olympiad_score', $account_columns, true )
	&& ! in_array( 'olympiad_tests', $account_columns, true );

$map = WPPA_Olympiad::summary_map( array( 7, 8, 9 ) );
$export_olympiad_checks['карта по нескольким пользователям'] = 2 === count( $map ) && 27 === $map[7]['score'];
$export_olympiad_checks['карта: процент']                   = 67.5 === $map[7]['percentage'];
$export_olympiad_checks['карта: один запрос']                = 1 === count( array_filter( $wpdb->queries, static function ( $q ) {
	return false !== strpos( (string) $q, 'GROUP BY user_id' );
} ) );

$row = WPPA_Export::row( get_userdata( 7 ), array( 'olympiad' => $map ) );
$export_olympiad_checks['строка: баллы']       = 27 === $row['olympiad_score'] && 40 === $row['olympiad_max'];
$export_olympiad_checks['строка: процент']     = 67.5 === $row['olympiad_percentage'];
$export_olympiad_checks['строка: зачтено']     = 3 === $row['olympiad_tests'];
$export_olympiad_checks['строка: на проверке'] = 1 === $row['olympiad_pending'];

$csv_users = array( get_userdata( 7 ), get_userdata( 8 ) );
$csv       = WPPA_Export::csv(
	array(
		'groups' => array( 'account', 'olympiad' ),
		'users'  => $csv_users,
		'limit'  => 10,
	)
);

$export_olympiad_checks['csv: заголовок с баллами'] = false !== strpos( $csv, 'Баллы за тесты' ) && false !== strpos( $csv, 'Максимум баллов' );
$export_olympiad_checks['csv: значения баллов']     = false !== strpos( $csv, '27;40;67.5;3;1' );
$export_olympiad_checks['csv: второй пользователь'] = false !== strpos( $csv, '5;10;50;1;0' );

$wpdb->queries = array();
WPPA_Export::csv( array( 'groups' => array( 'account', 'olympiad' ), 'users' => $csv_users, 'limit' => 10 ) );
$export_olympiad_checks['csv: баллы одним запросом на всех'] = 1 === count( array_filter( $wpdb->queries, static function ( $q ) {
	return false !== strpos( (string) $q, 'GROUP BY user_id' );
} ) );

// Плагина нет — ни группы, ни колонок, ни запросов.
$GLOBALS['wppa_test']['olympiad_force'] = false;
WPPA_Olympiad::flush();
$wpdb->data    = array();
$wpdb->queries = array();

$export_olympiad_checks['без плагина: группы нет']    = ! array_key_exists( 'olympiad', WPPA_Export::groups() );
$export_olympiad_checks['без плагина: колонок нет']   = ! in_array( 'olympiad_score', array_column( WPPA_Export::columns( WPPA_Export::default_groups() ), 'key' ), true );

$csv_plain = WPPA_Export::csv( array( 'groups' => array( 'account' ), 'users' => array( get_userdata( 7 ) ), 'limit' => 10 ) );
$export_olympiad_checks['без плагина: нет баллов в csv'] = false === strpos( $csv_plain, 'Баллы за тесты' );
$export_olympiad_checks['без плагина: нет запросов к попыткам'] = array() === array_filter( $wpdb->queries, static function ( $q ) {
	return false !== strpos( (string) $q, 'olympiad_attempts' ) && false === strpos( (string) $q, 'SHOW TABLES' );
} );

// Ключ группы доходит до настроек выгрузки в папку.
$GLOBALS['wppa_test']['olympiad_force'] = true;
$wpdb->data = array( 'var' => 'wp_olympiad_attempts' );
WPPA_Olympiad::flush();

$folder_groups = WPPA_Settings::sanitize(
	array( 'export_folder' => array( 'groups' => array( 'account', 'olympiad' ) ) )
)['export_folder']['groups'];

$export_olympiad_checks['настройки: группа баллов сохраняется'] = in_array( 'olympiad', $folder_groups, true );

foreach ( $export_olympiad_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'export olympiad: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

$bar_settings_ok = false !== strpos( $settings_html, 'wppa_settings[hide_admin_bar]' )
	&& false !== strpos( $settings_html, 'Скрывать панель WordPress' );

echo ( $bar_settings_ok ? 'OK    ' : 'FAIL  ' ) . "settings: галочка скрытия панели WordPress\n";
if ( ! $bar_settings_ok ) {
	$fail++;
}

$bar_sanitize_on  = WPPA_Settings::sanitize( array( 'hide_admin_bar' => '1' ) );
$bar_sanitize_off = WPPA_Settings::sanitize( array() );
$bar_sanitize_ok  = 1 === $bar_sanitize_on['hide_admin_bar'] && 0 === $bar_sanitize_off['hide_admin_bar'];

echo ( $bar_sanitize_ok ? 'OK    ' : 'FAIL  ' ) . "settings: скрытие панели сохраняется\n";
if ( ! $bar_sanitize_ok ) {
	$fail++;
}

$reg_window_fields_ok = false !== strpos( $settings_html, 'wppa_settings[registration_start]' )
	&& false !== strpos( $settings_html, 'wppa_settings[registration_end]' );

echo ( $reg_window_fields_ok ? 'OK    ' : 'FAIL  ' ) . "settings: поля времени регистрации\n";
if ( ! $reg_window_fields_ok ) {
	$fail++;
}

$reg_window_sanitize = WPPA_Settings::sanitize(
	array(
		'registration_enabled' => '1',
		'registration_start'   => '2026-12-01T09:00',
		'registration_end'     => 'мусор',
	)
);

$reg_window_sanitize_ok = '2026-12-01T09:00' === $reg_window_sanitize['registration_start']
	&& '' === $reg_window_sanitize['registration_end'];

echo ( $reg_window_sanitize_ok ? 'OK    ' : 'FAIL  ' ) . "settings: окно регистрации сохраняется\n";
if ( ! $reg_window_sanitize_ok ) {
	$fail++;
}

$mentor_settings_ok = false !== strpos( $settings_html, 'wppa_settings[require_mentor]' )
	&& false !== strpos( $settings_html, 'Требовать наставника' );

echo ( $mentor_settings_ok ? 'OK    ' : 'FAIL  ' ) . "settings: галочка «Требовать наставника»\n";
if ( ! $mentor_settings_ok ) {
	$fail++;
}

$mentor_sanitize     = WPPA_Settings::sanitize( array( 'require_mentor' => '1' ) );
$mentor_sanitize_off = WPPA_Settings::sanitize( array() );

$mentor_sanitize_ok = 1 === $mentor_sanitize['require_mentor'] && 0 === $mentor_sanitize_off['require_mentor'];

echo ( $mentor_sanitize_ok ? 'OK    ' : 'FAIL  ' ) . "settings: требование наставника сохраняется\n";
if ( ! $mentor_sanitize_ok ) {
	$fail++;
}

$mentor_badge_ok = false !== strpos( $teams_html, 'нет наставника' ) || false !== strpos( $teams_html, 'сформирована' );

echo ( $mentor_badge_ok ? 'OK    ' : 'FAIL  ' ) . "teams: статус команды в админке\n";
if ( ! $mentor_badge_ok ) {
	$fail++;
}

$link_settings_ok = false !== strpos( $settings_html, 'wppa_settings[link_button][url]' )
	&& false !== strpos( $settings_html, 'wppa_settings[link_button][start]' )
	&& false !== strpos( $settings_html, 'wppa_settings[link_button][end]' )
	&& false !== strpos( $settings_html, 'wppa_settings[link_button][require_team]' );

echo ( $link_settings_ok ? 'OK    ' : 'FAIL  ' ) . "settings: поля кнопки со ссылкой\n";
if ( ! $link_settings_ok ) {
	$fail++;
}

$link_sanitize = WPPA_Settings::sanitize(
	array(
		'link_button' => array(
			'url'          => 'https://example.com/go',
			'text'         => '  Перейти к трансляции ',
			'start'        => '2026-11-05T10:00',
			'end'          => 'не дата',
			'new_tab'      => '1',
			'require_team' => '0',
		),
	)
);

$link_sanitize_ok = 'https://example.com/go' === $link_sanitize['link_button']['url']
	&& 'Перейти к трансляции' === $link_sanitize['link_button']['text']
	&& '2026-11-05T10:00' === $link_sanitize['link_button']['start']
	&& '' === $link_sanitize['link_button']['end']
	&& 1 === $link_sanitize['link_button']['new_tab']
	&& 0 === $link_sanitize['link_button']['require_team'];

echo ( $link_sanitize_ok ? 'OK    ' : 'FAIL  ' ) . "settings: настройки кнопки сохраняются\n";
if ( ! $link_sanitize_ok ) {
	$fail++;
}

$link_unsafe = WPPA_Settings::sanitize( array( 'link_button' => array( 'url' => 'javascript:alert(1)' ) ) );
$link_unsafe_ok = '' === $link_unsafe['link_button']['url'];

echo ( $link_unsafe_ok ? 'OK    ' : 'FAIL  ' ) . "settings: небезопасная ссылка отбрасывается\n";
if ( ! $link_unsafe_ok ) {
	$fail++;
}

$rules_settings_ok = false !== strpos( $settings_html, 'wppa_settings[password_rules][uppercase]' )
	&& false !== strpos( $settings_html, 'wppa_settings[password_rules][no_sequence]' );

echo ( $rules_settings_ok ? 'OK    ' : 'FAIL  ' ) . "settings: требования к паролю\n";
if ( ! $rules_settings_ok ) {
	$fail++;
}

$rules_sanitize = WPPA_Settings::sanitize(
	array(
		'password_rules' => array( 'uppercase' => '1' ),
	)
);

$rules_sanitize_ok = 1 === $rules_sanitize['password_rules']['uppercase']
	&& 0 === $rules_sanitize['password_rules']['no_sequence']
	&& count( $rules_sanitize['password_rules'] ) === count( WPPA_Password::rules() );

echo ( $rules_sanitize_ok ? 'OK    ' : 'FAIL  ' ) . "settings: правила пароля сохраняются и сбрасываются\n";
if ( ! $rules_sanitize_ok ) {
	$fail++;
}

$fields_settings_ok = false !== strpos( $settings_html, 'wppa_settings[fields][phone][enabled]' )
	&& false !== strpos( $settings_html, 'wppa_settings[fields][phone][required]' )
	&& false !== strpos( $settings_html, 'wppa_settings[fields][first_name][enabled]' );

echo ( $fields_settings_ok ? 'OK    ' : 'FAIL  ' ) . "settings: таблица полей персональных данных\n";
if ( ! $fields_settings_ok ) {
	$fail++;
}

// Санитизация настроек полей.
$sanitized = WPPA_Settings::sanitize(
	array(
		'fields' => array(
			'first_name' => array( 'enabled' => 1, 'required' => 1 ),
			'phone'      => array( 'enabled' => 1 ),
			'city'       => array( 'enabled' => 0, 'required' => 1 ),
		),
	)
);

$fields_sanitize_ok = 1 === $sanitized['fields']['phone']['enabled']
	&& 0 === $sanitized['fields']['phone']['required']
	&& 0 === $sanitized['fields']['city']['enabled']
	&& 0 === $sanitized['fields']['city']['required'];

echo ( $fields_sanitize_ok ? 'OK    ' : 'FAIL  ' ) . "settings: обязательность снимается вместе с показом\n";
if ( ! $fields_sanitize_ok ) {
	$fail++;
}

// Наставники: страница «Команды» до назначения и после.
$mentor_before = false !== strpos( $teams_html, 'value="set_team_mentor"' );

WPPA_Teams::make_mentor( $demo_team_id, 8 );

ob_start();
WPPA_Admin::page_teams();
$teams_mentor_html = ob_get_clean();

$mentor_ok = $mentor_before
	&& 'mentor' === WPPA_Teams::role( 8 )
	&& 1 === count( WPPA_Teams::mentors( $demo_team_id ) )
	&& false !== strpos( $teams_mentor_html, 'value="remove_team_mentor"' )
	&& false !== strpos( $teams_mentor_html, 'wppa-badge--mentor' )
	&& false !== strpos( $teams_mentor_html, 'Участников (без наставников)' )
	&& false !== strpos( $teams_mentor_html, 'наставников: 1' )
	&& 1 === WPPA_Teams::members_count( $demo_team_id );

echo ( $mentor_ok ? 'OK    ' : 'FAIL  ' ) . "teams: назначение и снятие наставника в админке\n";
if ( ! $mentor_ok ) {
	$fail++;
}

$limit_ok = false !== strpos( $teams_html, 'name="team_limit"' )
	&& false !== strpos( $teams_html, 'name="team_min"' )
	&& 5 === WPPA_Teams::team_limit( $demo_team_id )
	&& 3 === WPPA_Teams::team_min( $demo_team_id );
echo ( $limit_ok ? 'OK    ' : 'FAIL  ' ) . "teams: поля минимума и лимита в таблице, значения применены\n";
if ( ! $limit_ok ) {
	$fail++;
}

$status = WPPA_Admin::system_status();
echo 'status: страница «' . $status['account_page']['label'] . '», регистрация «' . $status['registration']['label'] . "»\n";

// Меню: проверяем, что страницы зарегистрированы без ошибок.
WPPA_Admin::menu();
WPPA_Admin::assets( 'toplevel_page_wppa-dashboard' );
echo "menu/assets: ок\n";

// Санитизация раскладки: чужие блоки и вкладки отбрасываются.
$clean = WPPA_Builder::sanitize(
	array(
		'dashboard' => array(
			array( 'type' => 'block', 'id' => 'greeting', 'enabled' => 1, 'title' => '  Привет  ', 'audience' => 'хакеры' ),
			array( 'type' => 'block', 'id' => 'password-form', 'enabled' => 1 ),
			array( 'type' => 'custom', 'id' => 'custom-1', 'enabled' => 1, 'title' => 'Свой', 'html' => '<p>ok</p><script>alert(1)</script>' ),
		),
		'unknown-tab' => array( array( 'type' => 'block', 'id' => 'greeting', 'enabled' => 1 ) ),
	)
);

$sanitize_ok = 2 === count( $clean['dashboard'] )
	&& 'Привет' === $clean['dashboard'][0]['title']
	&& 'all' === $clean['dashboard'][0]['audience']
	&& false === strpos( $clean['dashboard'][1]['html'], '<script' );

echo ( $sanitize_ok ? 'OK    ' : 'FAIL  ' ) . "builder: sanitize отбрасывает чужие блоки, чистит HTML и нормализует поля\n";
if ( ! $sanitize_ok ) {
	$fail++;
}

echo $fail ? "\nПРОБЛЕМ: {$fail}\n" : "\nАдмин-панель отрисована без ошибок\n";
exit( $fail ? 1 : 0 );
