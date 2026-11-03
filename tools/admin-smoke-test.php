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
require $base . 'includes/class-wppa-fields.php';
require $base . 'includes/class-wppa-password.php';
require $base . 'includes/class-wppa-link-button.php';
require $base . 'includes/class-wppa-account.php';
require $base . 'includes/class-wppa-profile.php';
require $base . 'includes/class-wppa-teams.php';
require $base . 'includes/class-wppa-builder.php';
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
