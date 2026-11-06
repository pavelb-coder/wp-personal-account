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

require $base . 'includes/class-wppa-time.php';
require $base . 'includes/class-wppa-plugin.php';
require $base . 'includes/class-wppa-fields.php';
require $base . 'includes/class-wppa-password.php';
require $base . 'includes/class-wppa-link-button.php';
require $base . 'includes/class-wppa-template.php';
require $base . 'includes/class-wppa-notices.php';
require $base . 'includes/class-wppa-account.php';
require $base . 'includes/class-wppa-profile.php';
require $base . 'includes/class-wppa-teams.php';
require $base . 'includes/class-wppa-builder.php';
require $base . 'includes/class-wppa-export.php';
require $base . 'includes/class-wppa-export-folder.php';
require $base . 'includes/class-wppa-smb.php';
require $base . 'includes/class-wppa-curator-files.php';
require $base . 'includes/class-wppa-participants.php';
require $base . 'includes/class-wppa-certificates.php';
require $base . 'includes/class-wppa-team-files-export.php';
require $base . 'includes/class-wppa-mailing.php';
require $base . 'includes/class-wppa-mailing-files.php';
require $base . 'includes/class-wppa-olympiad.php';
require $base . 'includes/class-wppa-settings.php';
require $base . 'includes/class-wppa-theme.php';
require $base . 'includes/class-wppa-reset.php';
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

$demo_team_ids = array();

foreach ( array( 'Студия «Вектор»', 'Отдел закупок', 'Фриланс-группа' ) as $demo_name ) {
	$demo_team = WPPA_Teams::create( $demo_name, 7 );
	if ( ! is_wp_error( $demo_team ) ) {
		$demo_team_ids[] = (int) $demo_team;
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

// Демонстрация рассылки: команда второго этапа, шаблон и очередь с интервалом.
if ( ! empty( $demo_team_ids[0] ) ) {
	update_user_meta( 7, WPPA_Teams::META_TEAM, $demo_team_ids[0] );
	update_user_meta( 7, WPPA_Teams::META_ROLE, 'owner' );
	update_user_meta( 8, WPPA_Teams::META_TEAM, $demo_team_ids[0] );
	update_user_meta( 8, WPPA_Teams::META_ROLE, 'mentor' );

	WPPA_Teams::set_curator( 7, true );
	WPPA_Teams::set_stage2( $demo_team_ids[0], true, 7 );
	WPPA_Teams::set_curator( 7, false );

	WPPA_Mailing::save(
		array(
			'subject'  => 'Команда «{команда}» прошла во второй этап',
			'body'     => "Здравствуйте, {имя}!\n\nКоманда «{команда}» прошла во второй этап — отметка от {дата_отметки}.\n\nСледующий шаг пришлём отдельным письмом.\n\n— {сайт}",
			'interval' => 10,
			'batch'    => 2,
		)
	);
	WPPA_Mailing::start();
	WPPA_Mailing::process_due();

	update_post_meta(
		$demo_team_ids[0],
		WPPA_Curator_Files::META,
		array(
			'preview-file' => array(
				'file' => 'preview.pdf',
				'name' => 'Положение об олимпиаде.pdf',
				'from' => 'curator',
				'time' => time(),
				'user' => 7,
			),
		)
	);

	WPPA_Mailing_Files::save(
		array(
			'subject'  => 'Файлы для команды «{команда}»',
			'body'     => "Здравствуйте, {имя}!\n\nКуратор прикрепил файлы для команды «{команда}» — они уже в личном кабинете, во вкладке «Файлы».\n\nПисьмо отправлено на {email}.\n\n— {сайт}",
			'interval' => 10,
			'batch'    => 2,
		)
	);
}

$sections .= demo_page( 'Рассылка: второй этап', 'Шаблон письма с подстановками, предпросмотр, очередь с интервалом между письмами, отмена и журнал', static function () { WPPA_Admin::page_mailing(); } );
$sections .= demo_page(
	'Рассылка: прикреплённые файлы',
	'Вторая рассылка — тем же получателям, но со своим шаблоном, очередью и журналом',
	static function () {
		$_GET['wppa_kind'] = 'files';
		WPPA_Admin::page_mailing();
		unset( $_GET['wppa_kind'] );
	}
);
// Демонстрация панели выгрузки в сетевую папку: настройки, расписание, журнал.
$wppa_folder_snapshot = get_option( 'wppa_settings', array() );

update_option(
	'wppa_settings',
	array_merge(
		(array) $wppa_folder_snapshot,
		array(
			'export_folder' => array(
				'enabled'   => 1,
				'path'      => '/mnt/share/wppa',
				'file'      => 'wppa-accounts-{date}.csv',
				'schedule'  => 'daily',
				'time'      => '03:00',
				'keep'      => 30,
				'groups'    => WPPA_Export::default_groups(),
				'scope'     => 'all',
				'delimiter' => ';',
				'limit'     => 5000,
			),
		)
	)
);

$GLOBALS['wppa_test']['cron'][ WPPA_Export_Folder::CRON_HOOK ] = time() + 3600 * 8;
$GLOBALS['wppa_test']['cron_recurrence'][ WPPA_Export_Folder::CRON_HOOK ] = 'daily';

delete_option( WPPA_Export_Folder::LOG_OPTION );
WPPA_Export_Folder::log_entry( true, '/mnt/share/wppa/wppa-accounts-' . gmdate( 'Y-m-d' ) . '.csv', 51200, 'Файл записан (50 КБ).' );
WPPA_Export_Folder::log_entry( false, '/mnt/share/wppa/wppa-accounts-2026-11-02.csv', 0, 'Не удалось записать файл: нет прав на запись.' );
WPPA_Export_Folder::log_entry( true, '/mnt/share/wppa/wppa-accounts-2026-11-02.csv', 50120, 'Файл записан (49 КБ). Удалён 1 старый файл по правилу ротации.' );

// Настройки и инструменты: сетевая локальная папка с авторизацией.
$wppa_demo_snapshot = get_option( 'wppa_settings', array() );

update_option(
	'wppa_settings',
	array_merge(
		(array) $wppa_demo_snapshot,
		array(
			'smb'           => array(
				'path' => '\\\\fileserver\\exchange\\reports\\wppa',
				'user' => 'WORKGROUP\\svc_wppa',
				'pass' => 'demo-pass',
			),
			'export_folder' => array_merge(
				WPPA_Export_Folder::config(),
				array( 'mode' => 'smb', 'enabled' => 1, 'on_change' => 1, 'change_delay' => 5 )
			),
		)
	)
);

// Демонстрация ожидающей выгрузки по изменениям.
delete_option( WPPA_Export_Folder::PENDING_OPTION );
WPPA_Export_Folder::note_change( 'регистрация пользователя #14' );
WPPA_Export_Folder::note_change( 'поле _wppa_team_id у пользователя #9' );
WPPA_Export_Folder::note_change( 'профиль пользователя #11' );

// Заглушка WP Diplom, чтобы показать выбор бланка в настройках.
if ( ! class_exists( 'WPDC_Certificate' ) ) {
	/**
	 * Заглушка WPDC_Certificate для превью админки.
	 */
	class WPDC_Certificate {

		/** Выдать сертификат. */
		public static function create( $args = array() ) {
			return 9001;
		}

		/** Путь к PDF. */
		public static function pdf( $id ) {
			return '';
		}
	}
}

$GLOBALS['wppa_test']['posts'][601] = (object) array( 'ID' => 601, 'post_title' => 'Бланк «Олимпиада»', 'post_type' => 'wpdc_template', 'post_status' => 'publish', 'post_author' => 12 );
$GLOBALS['wppa_test']['posts'][602] = (object) array( 'ID' => 602, 'post_title' => 'Бланк «Основной»', 'post_type' => 'wpdc_template', 'post_status' => 'publish', 'post_author' => 12 );

update_option( 'wppa_settings', array_merge( (array) get_option( 'wppa_settings', array() ), array( 'certificate_template' => 601, 'certificate_email' => 1 ) ) );

$sections .= demo_page( 'Настройки', 'Параметры кабинета, регистрации, команд, паролей и сетевой папки с логином и паролем', static function () { WPPA_Settings::render_page(); } );

$sections .= demo_page( 'Инструменты', 'Обслуживание: восстановление страницы, сброс лимитов, очистка заявок, экспорт CSV', static function () { WPPA_Admin::page_tools(); } );

update_option( 'wppa_settings', $wppa_demo_snapshot );
delete_option( WPPA_Export_Folder::PENDING_OPTION );
unset( $GLOBALS['wppa_test']['cron'][ WPPA_Export_Folder::CHANGE_HOOK ] );

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
<script>
' . file_get_contents( $base . 'assets/js/admin-theme.js' ) . '
</script>
</body>
</html>';

file_put_contents( $base . 'preview-admin.html', $html );
// Образец выгрузки для проверки структуры: вместе с баллами за тесты.
global $wpdb;

if ( $wpdb instanceof WPPA_Test_WPDB ) {
	$wpdb->data = array(
		'var'      => $wpdb->prefix . 'olympiad_attempts',
		'attempts' => array(
			(object) array( 'user_id' => 7, 'score' => 27, 'max_score' => 40, 'tests' => 3, 'pending' => 1 ),
		),
	);

	WPPA_Olympiad::flush();
}

// Демо-участники трёх команд, чтобы в образце был виден порядок группировки.
$GLOBALS['wppa_test']['users'][7]  = array( 'Иван Петров', 'email' => 'ivan@example.com', 'login' => 'ivan', 'first_name' => 'Иван', 'last_name' => 'Петров', 'registered' => '2025-09-02 10:15:00' );
$GLOBALS['wppa_test']['users'][8]  = array( 'Мария Соколова', 'email' => 'maria@example.com', 'login' => 'maria', 'first_name' => 'Мария', 'last_name' => 'Соколова', 'registered' => '2025-09-04 12:40:00' );
$GLOBALS['wppa_test']['users'][9]  = array( 'Алексей Громов', 'email' => 'gromov@example.com', 'login' => 'gromov', 'first_name' => 'Алексей', 'last_name' => 'Громов', 'registered' => '2025-09-07 09:05:00' );
$GLOBALS['wppa_test']['users'][10] = array( 'Ольга Ким', 'email' => 'kim@example.com', 'login' => 'kim', 'first_name' => 'Ольга', 'last_name' => 'Ким', 'registered' => '2025-09-11 18:20:00' );
$GLOBALS['wppa_test']['users'][11] = array( 'Павел Безруков', 'email' => 'bezrukov@example.com', 'login' => 'bezrukov', 'first_name' => 'Павел', 'last_name' => 'Безруков', 'registered' => '2025-09-15 08:00:00' );

$GLOBALS['wppa_test']['meta'][7]  = array( WPPA_Teams::META_TEAM => 102, WPPA_Teams::META_ROLE => 'owner' );
$GLOBALS['wppa_test']['meta'][8]  = array( WPPA_Teams::META_TEAM => 102, WPPA_Teams::META_ROLE => 'member' );
$GLOBALS['wppa_test']['meta'][9]  = array( WPPA_Teams::META_TEAM => 101, WPPA_Teams::META_ROLE => 'owner' );
$GLOBALS['wppa_test']['meta'][10] = array( WPPA_Teams::META_TEAM => 101, WPPA_Teams::META_ROLE => 'member' );
$GLOBALS['wppa_test']['meta'][11] = array();

// Капитаны демо-команд — те, кто в фикстуре помечен owner.
if ( isset( $GLOBALS['wppa_test']['posts'][101] ) ) {
	$GLOBALS['wppa_test']['posts'][101]->post_author = 9;
	$GLOBALS['wppa_test']['posts'][102]->post_author = 7;
	$GLOBALS['wppa_test']['posts'][103]->post_author = 11;
}

if ( $wpdb instanceof WPPA_Test_WPDB ) {
	$wpdb->data = array(
		'var'      => $wpdb->prefix . 'olympiad_attempts',
		'attempts' => array(
			(object) array( 'user_id' => 7, 'score' => 27, 'max_score' => 40, 'tests' => 3, 'pending' => 1 ),
			(object) array( 'user_id' => 9, 'score' => 34, 'max_score' => 40, 'tests' => 4, 'pending' => 0 ),
			(object) array( 'user_id' => 10, 'score' => 18, 'max_score' => 30, 'tests' => 3, 'pending' => 2 ),
		),
	);

	WPPA_Olympiad::flush();
}

$sample_users = array( new WP_User( 11 ), new WP_User( 7 ), new WP_User( 9 ), new WP_User( 8 ), new WP_User( 10 ) );
$sample = WPPA_Export::csv( array( 'users' => $sample_users ) );
file_put_contents( $base . 'export-sample.csv', $sample );
echo "export-sample.csv: " . strlen( $sample ) . " байт\n";

delete_option( WPPA_Export_Folder::LOG_OPTION );
update_option( 'wppa_settings', $wppa_folder_snapshot );
unset( $GLOBALS['wppa_test']['cron'][ WPPA_Export_Folder::CRON_HOOK ], $GLOBALS['wppa_test']['cron_recurrence'][ WPPA_Export_Folder::CRON_HOOK ] );

echo 'preview-admin.html: ' . strlen( $html ) . " байт\n";
