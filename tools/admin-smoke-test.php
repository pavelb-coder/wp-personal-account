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

// ---------- Авторизация SMB ----------
$smb_config = array(
	'host'   => 'fileserver',
	'share'  => 'exchange',
	'dir'    => 'reports/wppa',
	'user'   => 'svc_wppa',
	'pass'   => 'secret-pass',
	'domain' => 'WORKGROUP',
	'port'   => 445,
);

$smb_checks = array(
	'цель: //сервер/шара/папка'      => '//fileserver/exchange/reports/wppa' === WPPA_SMB::target( $smb_config ),
	'цель без папки'                 => '//fileserver/exchange' === WPPA_SMB::target( array_merge( $smb_config, array( 'dir' => '' ) ) ),
	'учётные данные: домен\логин%пароль' => 'WORKGROUP\svc_wppa%secret-pass' === WPPA_SMB::auth_string( $smb_config ),
	'учётные данные без домена'      => 'svc_wppa%secret-pass' === WPPA_SMB::auth_string( array_merge( $smb_config, array( 'domain' => '' ) ) ),
	'шара указана — настроено'       => true === WPPA_SMB::is_configured( $smb_config ),
	'пустой сервер — не настроено'   => false === WPPA_SMB::is_configured( array( 'host' => '', 'share' => 'x', 'user' => 'u' ) ),
);

// Пароль из константы важнее сохранённого.
define( 'WPPA_SMB_PASSWORD', 'from-constant' );
$smb_checks['пароль из константы'] = 'from-constant' === WPPA_SMB::password( 'from-db' ) && 'from-constant' === WPPA_SMB::config()['pass'];

// Команда монтирования: пароль не в командной строке.
$mount = WPPA_SMB::mount_command( $smb_config );
$smb_checks['монтирование: credentials-файл'] = false !== strpos( $mount['command'], 'credentials=' )
	&& false === strpos( $mount['command'], 'secret-pass' );
$smb_checks['монтирование: пароль в файле']   = false !== strpos( $mount['credentials'], 'password=secret-pass' );
$smb_checks['монтирование: домен в файле']    = false !== strpos( $mount['credentials'], 'domain=WORKGROUP' );

// Проверка доступа без расширения и без утилиты.
$smb_checks['нет средств — понятная ошибка'] = false === WPPA_SMB::test( $smb_config )['ok']
	&& false !== strpos( WPPA_SMB::test( $smb_config )['message'], 'smbclient' );

// Проверка доступа через утилиту smbclient (подменяем запуск команд).
add_filter( 'wppa_smb_runner', function ( $result, $command ) {
	$GLOBALS['wppa_test']['smb_command'] = $command;

	if ( false !== strpos( $command, 'command -v smbclient' ) ) {
		return array( 'code' => 0, 'output' => '/usr/bin/smbclient' );
	}

	return array( 'code' => 0, 'output' => 'NT_STATUS_OK' );
}, 10, 2 );

$GLOBALS['wppa_test']['smb_runner'] = true;
$smb_test_result = WPPA_SMB::test( $smb_config );
$smb_checks['smbclient: доступ получен']      = $smb_test_result['ok'] && 'cli' === $smb_test_result['method'];
$smb_checks['smbclient: логин с паролем в вызове'] = false !== strpos( (string) $GLOBALS['wppa_test']['smb_command'], 'WORKGROUP\\svc_wppa%secret-pass' );

$smb_write = WPPA_SMB::write( 'accounts.csv', "id;email\n", $smb_config );
$smb_checks['smbclient: файл записан']        = $smb_write['ok'] && '//fileserver/exchange/reports/wppa/accounts.csv' === $smb_write['file'];
$smb_checks['smbclient: cd в папку шары']     = false !== strpos( (string) $GLOBALS['wppa_test']['smb_command'], 'cd' );

// Выгрузка в режиме SMB идёт через WPPA_SMB и пишет журнал.
$smb_settings_snapshot = get_option( 'wppa_settings', array() );
update_option( 'wppa_settings', array_merge( (array) $smb_settings_snapshot, array( 'smb' => array_merge( $smb_config, array( 'pass' => 'from-db' ) ) ) ) );

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
$smb_checks['выгрузка в режиме SMB'] = $smb_export['ok'] && false !== strpos( $smb_export['file'], '//fileserver/exchange/reports/wppa/' );
$smb_checks['журнал помнит режим SMB'] = ! empty( WPPA_Export_Folder::logs() );
update_option( 'wppa_settings', $smb_settings_snapshot );

// Настройки: поля авторизации и очистка значений.
$smb_fields_ok = false !== strpos( $settings_html, 'wppa_settings[smb][user]' )
	&& false !== strpos( $settings_html, 'wppa_settings[smb][pass]' )
	&& false !== strpos( $settings_html, 'wppa_settings[export_folder][mode]' );

$smb_checks['настройки: поля авторизации'] = $smb_fields_ok;

// Кнопки «Проверить подключение» и «Экспортировать».
$smb_checks['настройки: кнопка проверки подключения'] = false !== strpos( $settings_html, 'Проверить подключение к сетевой папке' )
	&& false !== strpos( $settings_html, 'value="folder_verify"' );
$smb_checks['настройки: кнопка экспорта'] = false !== strpos( $settings_html, 'value="export_folder_now"' )
	&& false !== strpos( $settings_html, '>Экспортировать<' );
$smb_checks['настройки: кнопки не вложены в форму настроек'] = ( false === strpos( $settings_html, '<form action="options.php" method="post">\n\t\t\t\t\t\t\t<form' ) );
$smb_checks['инструменты: кнопка проверки подключения'] = false !== strpos( $tools_html, 'value="folder_verify"' );
$smb_checks['инструменты: кнопка экспорта'] = false !== strpos( $tools_html, '>Экспортировать<' );
$smb_checks['уведомления: проверка подключения'] = ( function () {
	$_GET['wppa_done'] = 'folder_verify_ok';
	$ok = WPPA_Admin::tool_notice();
	$_GET['wppa_done'] = 'folder_verify_fail';
	$bad = WPPA_Admin::tool_notice();
	unset( $_GET['wppa_done'] );
	return is_array( $ok ) && 'success' === $ok[0] && is_array( $bad ) && 'error' === $bad[0];
} )();

$smb_sanitize = WPPA_Settings::sanitize(
	array(
		'smb' => array(
			'host'   => 'smb://fileserver/share',
			'share'  => 'exchange',
			'dir'    => '/reports/../wppa/',
			'user'   => 'svc_wppa',
			'pass'   => 'p@ss word',
			'domain' => 'WORKGROUP',
			'port'   => '0',
		),
		'export_folder' => array( 'mode' => 'smb' ),
	)
);

$smb_checks['настройки: хост очищен']    = 'fileserver' === $smb_sanitize['smb']['host'];
$smb_checks['настройки: папка без ..']   = false === strpos( $smb_sanitize['smb']['dir'], '..' );
$smb_checks['настройки: пароль сохранён'] = 'p@ss word' === $smb_sanitize['smb']['pass'];
$smb_checks['настройки: режим smb']      = 'smb' === $smb_sanitize['export_folder']['mode'];

delete_option( WPPA_Export_Folder::LOG_OPTION );

foreach ( $smb_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'smb: ' . $check_name . "\n";
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
