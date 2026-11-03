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
require $base . 'includes/class-wppa-fields.php';
require $base . 'includes/class-wppa-password.php';
require $base . 'includes/class-wppa-link-button.php';
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

	// Наставник: назначение, права, снятие.
	$mentor_checks = array(
		'капитан не становится наставником' => false === WPPA_Teams::make_mentor( $team_id, 7 ) && false === WPPA_Teams::is_mentor( 7 ),
		'посторонний не становится наставником' => false === WPPA_Teams::make_mentor( $team_id, 15 ),
	);

	$mentor_checks['наставник назначен']        = true === WPPA_Teams::make_mentor( $team_id, 8 );
	$mentor_checks['роль = mentor']             = 'mentor' === WPPA_Teams::role( 8 );
	$mentor_checks['is_mentor']                 = true === WPPA_Teams::is_mentor( 8 );
	$mentor_checks['is_manager у наставника']   = true === WPPA_Teams::is_manager( 8 );
	$mentor_checks['капитан тоже manager']      = true === WPPA_Teams::is_manager( 7 );
	$mentor_checks['обычный участник не manager'] = false === WPPA_Teams::is_manager( 9 );
	$mentor_checks['наставник в списке команды'] = 1 === count( WPPA_Teams::mentors( $team_id ) );
	$mentor_checks['наставник не может сменить роль на owner'] = false === WPPA_Teams::set_role( $team_id, 8, 'owner' );
	$mentor_checks['наставник может исключить участника'] = true === WPPA_Teams::remove_member( $team_id, 9 );
	$mentor_checks['капитана исключить нельзя']  = false === WPPA_Teams::remove_member( $team_id, 7 );
	$mentor_checks['роль снята']                = true === WPPA_Teams::dismiss_mentor( $team_id, 8 ) && 'member' === WPPA_Teams::role( 8 );

	foreach ( $mentor_checks as $check_name => $ok ) {
		echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'team mentor: ' . $check_name . "\n";
		if ( ! $ok ) {
			$fail++;
		}
	}

	// Наставник не считается участником: не занимает лимит и не идёт в минимум.
	// Приводим состав к известному виду: капитан 7 + наставник 8.
	WPPA_Teams::dismiss_mentor( $team_id, 9 );
	WPPA_Teams::remove_member( $team_id, 9 );
	WPPA_Teams::set_team_limit( $team_id, 2 );
	WPPA_Teams::set_team_min( $team_id, 2 );
	WPPA_Teams::make_mentor( $team_id, 8 );

	$count_checks = array(
		'участник в команде только капитан'  => 1 === WPPA_Teams::members_count( $team_id ),
		'participants = капитан'             => 1 === count( WPPA_Teams::participants( $team_id ) ),
		'наставников = 1'                    => 1 === WPPA_Teams::mentors_count( $team_id ),
		'всего человек в команде = 2'        => 2 === count( WPPA_Teams::members( $team_id ) ),
		'наставник не закрывает минимум'     => 1 === WPPA_Teams::missing_members( $team_id ),
	);

	// Наставник не занимает место в лимите: при лимите 2 можно добрать участника.
	$extra = WPPA_Teams::add_member( $team_id, 15 );
	$count_checks['место наставника свободно для участника'] = 'joined' === $extra;
	$count_checks['участников стало 2']                      = 2 === WPPA_Teams::members_count( $team_id );
	$count_checks['минимум набран участниками']              = 0 === WPPA_Teams::missing_members( $team_id );

	// Лимит считается только по участникам: 2 из 2 — следующий не пройдёт.
	$overflow = WPPA_Teams::add_member( $team_id, 16 );
	$count_checks['лимит закрыт участниками'] = is_wp_error( $overflow ) && 'wppa_team_full' === $overflow->get_error_code();

	// Снятие роли возвращает человека в счёт участников.
	WPPA_Teams::dismiss_mentor( $team_id, 8 );
	$count_checks['после снятия роли участников 3']  = 3 === WPPA_Teams::members_count( $team_id );
	$count_checks['наставников не осталось']         = 0 === WPPA_Teams::mentors_count( $team_id );

	foreach ( $count_checks as $check_name => $ok ) {
		echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'team count: ' . $check_name . "\n";
		if ( ! $ok ) {
			$fail++;
		}
	}

	// Возвращаем наставника 9 и участника 8 для проверок интерфейса.
	WPPA_Teams::make_mentor( $team_id, 9 );
	WPPA_Teams::set_team_min( $team_id, 0 );
	WPPA_Teams::set_team_limit( $team_id, 0 );

	// Наставник в интерфейсе кабинета.
	WPPA_Teams::add_member( $team_id, 9 );
	WPPA_Teams::make_mentor( $team_id, 9 );

	$mentor_html = WPPA_Builder::render_block(
		array( 'type' => 'block', 'id' => 'team-widget', 'title' => '' ),
		array( 'user' => get_userdata( 9 ), 'tabs' => $tabs )
	);

	$mentor_ui = array(
		'кабинет: бейдж «Наставник»'        => false !== strpos( $mentor_html, 'wppa-badge--mentor' ) && false !== strpos( $mentor_html, 'Наставник' ),
		'кабинет: подсказка наставнику'      => false !== strpos( $mentor_html, 'Вы наставник команды' ),
		'кабинет: приглашения доступны'      => false !== strpos( $mentor_html, 'Пригласить участников' ),
		'кабинет: переименование недоступно' => false === strpos( $mentor_html, 'value="rename"' ),
		'кабинет: выход вместо роспуска'     => false !== strpos( $mentor_html, 'Выйти из команды' ),
	);

	// Капитан видит управление наставниками.
	$owner_html = WPPA_Builder::render_block(
		array( 'type' => 'block', 'id' => 'team-widget', 'title' => '' ),
		array( 'user' => get_userdata( 7 ), 'tabs' => $tabs )
	);

	$mentor_ui['капитан: кнопка снятия наставника'] = false !== strpos( $owner_html, 'value="dismiss_mentor"' );
	$mentor_ui['капитан: назначение наставника']    = false !== strpos( $owner_html, 'value="make_mentor"' ) || false !== strpos( $owner_html, 'Сделать наставником' );

	foreach ( $mentor_ui as $check_name => $ok ) {
		echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'team mentor ui: ' . $check_name . "\n";
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
// ---------- Дополнительные персональные данные ----------
$fields_schema  = WPPA_Fields::schema();
$fields_checks  = array(
	'реестр полей содержит телефон'      => isset( $fields_schema['phone'] ),
	'по умолчанию телефон включён'       => WPPA_Fields::is_enabled( 'phone' ),
	'имя обязательно по умолчанию'       => WPPA_Fields::is_required( 'first_name' ),
	'отчество по умолчанию скрыто'       => ! WPPA_Fields::is_enabled( 'patronymic' ),
);

// Санитизация.
$fields_checks['телефон очищается'] = '+7 (913) 555-21-08' === WPPA_Fields::sanitize_value( 'phone', '+7 (913) 555-21-08; DROP' );
$fields_checks['дата рождения только в формате Y-m-d'] = '' === WPPA_Fields::sanitize_value( 'birthdate', '12.04.1990' ) && '1990-04-12' === WPPA_Fields::sanitize_value( 'birthdate', '1990-04-12' );

// Валидация.
$fields_checks['обязательное имя проверяется'] = 'field_required' === WPPA_Fields::validate( array( 'first_name' => '' ), 'register' );
$fields_checks['короткий телефон отклоняется'] = 'phone_invalid' === WPPA_Fields::validate( array( 'first_name' => 'Иван', 'phone' => '+7 12' ), 'register' );
$fields_checks['корректный телефон принимается'] = '' === WPPA_Fields::validate( array( 'first_name' => 'Иван', 'phone' => '+7 913 555-21-08' ), 'register' );
// Включаем дату рождения и сайт, чтобы проверить их валидацию.
$fields_backup = get_option( 'wppa_settings', array() );
update_option(
	'wppa_settings',
	array_merge(
		$fields_backup,
		array(
			'fields' => array(
				'first_name' => array( 'enabled' => 1, 'required' => 1 ),
				'birthdate'  => array( 'enabled' => 1, 'required' => 0 ),
				'website'    => array( 'enabled' => 1, 'required' => 0 ),
			),
		)
	)
);

$fields_checks['будущая дата рождения отклоняется'] = 'birthdate_invalid' === WPPA_Fields::validate( array( 'first_name' => 'Иван', 'birthdate' => gmdate( 'Y-m-d', time() + 86400 * 5 ) ), 'register' );
$fields_checks['сайт без протокола отклоняется']    = 'website_invalid' === WPPA_Fields::validate( array( 'first_name' => 'Иван', 'website' => 'example.com' ), 'register' );
$fields_checks['корректный сайт принимается']       = '' === WPPA_Fields::validate( array( 'first_name' => 'Иван', 'website' => 'https://example.com' ), 'register' );

update_option( 'wppa_settings', $fields_backup );

// Сохранение мета-полей.
WPPA_Fields::save_meta( 21, array( 'phone' => '+7 913 555-21-08', 'city' => 'Красноярск', 'company' => '' ) );
$fields_checks['телефон сохранён в мета'] = '+7 913 555-21-08' === WPPA_Fields::get( 21, 'phone' );
$fields_checks['пустое значение не пишется'] = '' === WPPA_Fields::get( 21, 'company' );

// Форма регистрации содержит поля.
$GLOBALS['wppa_test']['logged_in'] = false;
ob_start();
WPPA_Template::render( 'register' );
$register_html = ob_get_clean();
$GLOBALS['wppa_test']['logged_in'] = true;

$fields_checks['форма регистрации: имя из реестра']     = false !== strpos( $register_html, 'name="wppa_fields[first_name]"' );
$fields_checks['форма регистрации: телефон']            = false !== strpos( $register_html, 'name="wppa_fields[phone]"' );
$fields_checks['форма регистрации: организация']        = false !== strpos( $register_html, 'name="wppa_fields[company]"' );
$fields_checks['форма регистрации: обязательность']     = false !== strpos( $register_html, 'required' );
$fields_checks['форма регистрации: без отчества']       = false === strpos( $register_html, 'wppa_fields[patronymic]' );

// Профиль: нативные «Имя»/«Фамилия» не дублируются, мета-поля выводятся.
ob_start();
WPPA_Fields::render( 'profile', 21 );
$profile_fields_html = ob_get_clean();
$fields_checks['профиль: без дубля имени']    = false === strpos( $profile_fields_html, 'wppa_fields[first_name]' );
$fields_checks['профиль: телефон с значением'] = false !== strpos( $profile_fields_html, 'value="+7 913 555-21-08"' );

foreach ( $fields_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'fields: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// ---------- Кнопка «Регистрация» на форме входа ----------
$GLOBALS['wppa_test']['logged_in'] = false;

$login_html = WPPA_Template::get( 'login', array( 'redirect' => '', 'title' => 'Вход' ) );
$reg_btn_checks = array(
	'вход: кнопка «Регистрация»'        => false !== strpos( $login_html, 'wppa-switch--button' ) && false !== strpos( $login_html, 'Регистрация' ),
	'вход: ссылка на форму регистрации' => false !== strpos( $login_html, 'wppa_view=register' ),
	'вход: кнопка «Войти» на месте'     => false !== strpos( $login_html, 'type="submit"' ),
);

$login_hidden = WPPA_Template::get( 'login', array( 'hide_register' => true ) );
$reg_btn_checks['вход: кнопку можно отключить'] = false === strpos( $login_hidden, 'wppa_view=register' );

$register_html = WPPA_Template::get( 'register', array( 'title' => '' ) );
$reg_btn_checks['регистрация: кнопка «Войти»'] = false !== strpos( $register_html, 'wppa_view=login' );
$reg_btn_checks['регистрация: кнопку можно отключить'] = false === strpos( WPPA_Template::get( 'register', array( 'hide_login' => true ) ), 'wppa_view=login' );

// Регистрация закрыта — кнопки нет.
update_option( 'users_can_register', 0 );
$reg_btn_checks['регистрация закрыта: кнопки нет'] = false === strpos( WPPA_Template::get( 'login', array() ), 'wppa_view=register' );
update_option( 'users_can_register', 1 );

$reg_btn_checks['хелпер registration_open()'] = true === WPPA_Plugin::registration_open();

// Шорткод [wppa_login]: кнопка по умолчанию и скрытие атрибутом.
$short_login    = WPPA_Shortcodes::login( array() );
$short_login_no = WPPA_Shortcodes::login( array( 'hide_register' => '1' ) );
$reg_btn_checks['шорткод: кнопка по умолчанию'] = false !== strpos( $short_login, 'wppa_view=register' );
$reg_btn_checks['шорткод: hide_register="1"']   = false === strpos( $short_login_no, 'wppa_view=register' );

foreach ( $reg_btn_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'register button: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

$GLOBALS['wppa_test']['logged_in'] = true;

// ---------- Переход по ссылке ?wppa_view=register ----------
$GLOBALS['wppa_test']['logged_in'] = false;

$view_checks = array(
	'query_vars: wppa_view зарегистрирован' => in_array( 'wppa_view', WPPA_Plugin::query_vars( array( 'p' ) ), true ),
	'query_vars: wppa_tab зарегистрирован'  => in_array( 'wppa_tab', WPPA_Plugin::query_vars( array() ), true ),
	'requested_view(): пусто без параметра' => '' === WPPA_Plugin::requested_view(),
);

$_GET['wppa_view'] = 'register';
$view_checks['requested_view(): register'] = 'register' === WPPA_Plugin::requested_view();

// Виджет «только вход» переключается на регистрацию по ссылке.
ob_start();
WPPA_Auth_Form::render( array( 'mode' => 'login' ) );
$view_register_html = ob_get_clean();

$view_checks['логин-виджет показывает регистрацию'] = false !== strpos( $view_register_html, 'wppa_fields[first_name]' );
$view_checks['логин-виджет: панель без is-hidden']  = false === strpos( $view_register_html, 'id="wppa-panel-register"' ) ? false : true;

// Виджет «оба»: активна вкладка регистрации, панель входа скрыта.
ob_start();
WPPA_Auth_Form::render( array( 'mode' => 'both' ) );
$view_both_html = ob_get_clean();

$view_checks['режим «оба»: активна вкладка регистрации'] = false !== strpos( $view_both_html, 'data-wppa-panel-btn="register" role="tab" aria-selected="true"' );
$view_checks['режим «оба»: панель входа скрыта']         = false !== strpos( $view_both_html, 'wppa-widget__panel is-hidden' );

// Экран кабинета для гостя.
$view_checks['экран гостя: регистрация'] = false !== strpos( WPPA_Template::get( 'guest', array( 'view' => WPPA_Plugin::requested_view(), 'registration' => true ) ), 'wppa_fields[first_name]' );

// Регистрация закрыта — параметр игнорируется.
update_option( 'users_can_register', 0 );
$view_checks['закрытая регистрация: fallback на вход'] = 'login' === WPPA_Plugin::requested_view();
update_option( 'users_can_register', 1 );

unset( $_GET['wppa_view'] );

foreach ( $view_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'view: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// ---------- Пароль: минимум 16 символов, обязательные поля ----------
$password_checks = array(
	'минимум 16 по умолчанию'          => 16 === WPPA_Plugin::min_password_length(),
	'15 символов — мало'               => true === WPPA_Plugin::password_too_short( str_repeat( 'a', 15 ) ),
	'16 символов — достаточно'         => false === WPPA_Plugin::password_too_short( str_repeat( 'a', 16 ) ),
	'21 символ — достаточно'           => false === WPPA_Plugin::password_too_short( str_repeat( 'a', 21 ) ),
);

$register_form = WPPA_Template::get( 'register', array( 'title' => '' ) );
$password_checks['форма регистрации: minlength=16'] = false !== strpos( $register_form, 'minlength="16"' );
$password_checks['форма регистрации: подсказка о 16'] = false !== strpos( $register_form, 'Минимум 16 символов' );
$password_checks['форма регистрации: отметка у email'] = 1 === preg_match( '/for="wppa-email">Email.*?wppa-req/s', $register_form );
$password_checks['форма регистрации: отметки обязательных полей'] = 5 === substr_count( $register_form, 'wppa-req' );
$password_checks['форма регистрации: пояснение про звёздочку'] = false !== strpos( $register_form, 'Поля со звёздочкой' );

$login_form = WPPA_Template::get( 'login', array() );
$password_checks['форма входа: отметки у логина и пароля'] = 2 === substr_count( $login_form, 'wppa-req' );

$password_block = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'password-form', 'title' => '' ),
	array( 'user' => new WP_User(), 'tabs' => WPPA_Template::tabs() )
);
$password_checks['смена пароля: minlength=16'] = false !== strpos( $password_block, 'minlength="16"' );
$password_checks['смена пароля: подсказка']    = false !== strpos( $password_block, 'Минимум 16 символов' );

$notices = WPPA_Notices::registry();
$password_checks['сообщение содержит 16'] = false !== strpos( $notices['password_short'][1], '16' );

foreach ( $password_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'password: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// ---------- Правила проверки пароля ----------
$strong = 'Kr7#mQz!vLp2wXe9';
$rule_checks = array(
	'сильный пароль проходит'             => '' === WPPA_Password::error_code( $strong ),
	'15 символов — короткий'              => 'password_short' === WPPA_Password::error_code( 'Kr7#mQz!vLp2wXe' ),
	'без заглавной'                       => 'password_no_uppercase' === WPPA_Password::error_code( 'kr7#mqz!vlp2wxe9' ),
	'без строчной'                        => 'password_no_lowercase' === WPPA_Password::error_code( 'KR7#MQZ!VLP2WXE9' ),
	'без цифры'                           => 'password_no_digit' === WPPA_Password::error_code( 'KrQ#mQz!vLpAwXeY' ),
	'без спецсимвола'                     => 'password_no_special' === WPPA_Password::error_code( 'Kr7vmQz2vLp9wXe4' ),
	'повтор вида aaa'                     => 'password_repeat' === WPPA_Password::error_code( 'Kraaa#7mQz2vLp9X' ),
	'последовательность 5678'             => 'password_sequence' === WPPA_Password::error_code( 'Kl#5678mQzRvXe9j' ),
	'раскладка qwerty'                    => 'password_sequence' === WPPA_Password::error_code( 'Qwerty#9LmPz4Xv2K' ),
	'частый пароль'                       => 'password_common' === WPPA_Password::error_code( 'Password#Secret99' ),
	'пароль с email'                      => 'password_personal' === WPPA_Password::error_code( 'Ivan#Petrov#7712', array( 'email' => 'ivan.petrov@example.com' ) ),
	'пароль с именем'                     => 'password_personal' === WPPA_Password::error_code( 'Kr7#mQz!Иван#9Xv2', array( 'name' => 'Иван Петров' ) ),
	'мало разных символов'                => 'password_variety' === WPPA_Password::error_code( 'Aa1#Aa1#Aa1#Aa1#' ),
);

// Отключённое правило не проверяется.
update_option(
	'wppa_settings',
	array_merge(
		get_option( 'wppa_settings', array() ),
		array( 'password_rules' => array( 'uppercase' => 0 ) )
	)
);
$rule_checks['отключённое правило игнорируется'] = '' === WPPA_Password::error_code( 'kr7#mqz!vlp2wxe9' );
update_option( 'wppa_settings', array_merge( get_option( 'wppa_settings', array() ), array( 'password_rules' => array() ) ) );

$rule_checks['список правил непустой'] = count( WPPA_Password::labels() ) >= 9;

ob_start();
WPPA_Password::render_requirements( 'wppa-reg-password' );
$requirements_html = ob_get_clean();

$rule_checks['подсказки: список выводится']        = false !== strpos( $requirements_html, 'data-wppa-password-rules' );
$rule_checks['подсказки: привязка к полю']         = false !== strpos( $requirements_html, 'data-for="wppa-reg-password"' );
$rule_checks['подсказки: правило в разметке']      = false !== strpos( $requirements_html, 'data-rule="no_sequence"' );
$rule_checks['подсказки: параметры длины']         = false !== strpos( $requirements_html, 'data-params="{&quot;min&quot;:16}"' );

$register_rules_html = WPPA_Template::get( 'register', array() );
$rule_checks['форма регистрации: список требований'] = false !== strpos( $register_rules_html, 'Требования к паролю:' );

foreach ( $rule_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'password rules: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// ---------- Кнопка со ссылкой: окно дат и условие по команде ----------
$link_base = array(
	'url'          => 'https://example.com/go',
	'text'         => 'Перейти',
	'start'        => '',
	'end'          => '',
	'new_tab'      => 1,
	'require_team' => 0,
);

$link_settings_snapshot = get_option( 'wppa_settings', array() );
$link_meta_snapshot     = isset( $GLOBALS['wppa_test']['meta'] ) ? $GLOBALS['wppa_test']['meta'] : array();
$link_posts_snapshot    = isset( $GLOBALS['wppa_test']['posts'] ) ? $GLOBALS['wppa_test']['posts'] : array();
$link_postmeta_snapshot = isset( $GLOBALS['wppa_test']['postmeta'] ) ? $GLOBALS['wppa_test']['postmeta'] : array();

/**
 * Состояние кнопки при заданных настройках.
 *
 * @param array $config Настройки.
 * @return array
 */
function wppa_link_state( $config ) {
	update_option( 'wppa_settings', array_merge( (array) get_option( 'wppa_settings', array() ), array( 'link_button' => $config ) ) );
	return WPPA_Link_Button::state();
}

$link_checks = array();

$state = wppa_link_state( array_merge( $link_base, array( 'url' => '' ) ) );
$link_checks['без ссылки — блок неактивен'] = 'unconfigured' === $state['status'] && ! $state['is_open'];

$state = wppa_link_state( $link_base );
$link_checks['без дат — ссылка открыта'] = 'ready' === $state['status'] && $state['is_open'] && 0 === $state['countdown'];

$state = wppa_link_state( array_merge( $link_base, array( 'start' => gmdate( 'Y-m-d\TH:i', time() + 3600 ) ) ) );
$link_checks['до начала — закрыто и есть отсчёт'] = 'before' === $state['status'] && ! $state['is_open'] && $state['countdown'] > time();
$link_checks['до начала — сообщение с датой']      = false !== strpos( $state['message'], 'Откроется' );

$state = wppa_link_state( array_merge( $link_base, array( 'start' => gmdate( 'Y-m-d\TH:i', time() - 7200 ) ) ) );
$link_checks['после начала — открыто'] = 'ready' === $state['status'] && $state['is_open'];

$state = wppa_link_state( array_merge( $link_base, array( 'end' => gmdate( 'Y-m-d\TH:i', time() - 60 ) ) ) );
$link_checks['после окончания — закрыто']   = 'after' === $state['status'] && ! $state['is_open'];
$link_checks['после окончания — сообщение'] = false !== strpos( $state['message'], 'Срок доступа истёк' );

$state = wppa_link_state( array_merge( $link_base, array( 'end' => gmdate( 'Y-m-d\TH:i', time() + 3600 ) ) ) );
$link_checks['внутри окна — открыто и отсчёт до конца'] = 'ready' === $state['status'] && 'end' === $state['countdown_of'];

// Условие «команда сформирована»: пользователь без команды.
$GLOBALS['wppa_test']['meta'] = array();
$state = wppa_link_state( array_merge( $link_base, array( 'require_team' => 1 ) ) );
$link_checks['нет команды — закрыто']      = 'team' === $state['status'] && ! $state['is_open'];
$link_checks['нет команды — сообщение']    = false !== strpos( $state['message'], 'команда' );

// Команда из одного человека при минимуме 2 — не сформирована.
$GLOBALS['wppa_test']['meta']     = array( 7 => array( '_wppa_team_id' => 901, '_wppa_team_role' => 'owner' ) );
$GLOBALS['wppa_test']['posts'][901] = new WP_Post( array( 'ID' => 901, 'post_type' => WPPA_Teams::CPT, 'post_title' => 'Команда' ) );
$GLOBALS['wppa_test']['postmeta'][901]['_wppa_team_min'] = 2;

$state = wppa_link_state( array_merge( $link_base, array( 'require_team' => 1 ) ) );
$link_checks['один из двух — закрыто']   = 'team' === $state['status'] && 1 === $state['team']['members'] && 2 === $state['team']['required'];
$link_checks['один из двух — текст']     = false !== strpos( $state['message'], '1 из 2' );

// Наставник в зачёт не идёт: второй участник — наставник.
$GLOBALS['wppa_test']['meta'][8] = array( '_wppa_team_id' => 901, '_wppa_team_role' => 'mentor' );
$state = wppa_link_state( array_merge( $link_base, array( 'require_team' => 1 ) ) );
$link_checks['наставник не считается'] = 'team' === $state['status'] && 1 === $state['team']['members'];

// Обычный участник закрывает условие.
$GLOBALS['wppa_test']['meta'][8]['_wppa_team_role'] = 'member';
$state = wppa_link_state( array_merge( $link_base, array( 'require_team' => 1 ) ) );
$link_checks['минимум набран — открыто'] = 'ready' === $state['status'] && $state['is_open'] && 2 === $state['team']['members'];

// Условие команды + окно дат одновременно.
$state = wppa_link_state( array_merge( $link_base, array( 'require_team' => 1, 'start' => gmdate( 'Y-m-d\TH:i', time() + 3600 ) ) ) );
$link_checks['команда есть, но рано — отсчёт'] = 'before' === $state['status'] && $state['countdown'] > time();

// Разметка блока: открытое состояние.
wppa_link_state( $link_base );
$link_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'link-button', 'enabled' => 1, 'title' => 'Трансляция', 'audience' => 'all', 'html' => '' ),
	array()
);
$link_checks['открытая кнопка — ссылка с target'] = false !== strpos( $link_html, 'href="https://example.com/go"' ) && false !== strpos( $link_html, 'noopener' );

$state = wppa_link_state( array_merge( $link_base, array( 'end' => gmdate( 'Y-m-d\TH:i', time() - 60 ) ) ) );
$link_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'link-button', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array()
);
$link_checks['закрытая кнопка — без href']         = false === strpos( $link_html, 'href="https://example.com/go"' );
$link_checks['закрытая кнопка — aria-disabled']    = false !== strpos( $link_html, 'aria-disabled="true"' );

$state = wppa_link_state( array_merge( $link_base, array( 'start' => gmdate( 'Y-m-d\TH:i', time() + 7200 ) ) ) );
$link_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'link-button', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array()
);
$link_checks['таймер выводится в разметке'] = false !== strpos( $link_html, 'data-wppa-countdown=' );
$link_checks['таймер перезагружает страницу'] = false !== strpos( $link_html, 'data-wppa-reload="1"' );
$link_checks['адрес не утекает в разметке']   = false === strpos( $link_html, 'https://example.com/go' );

// Форматирование и сохранение дат.
$link_checks['datetime-local сохраняется'] = '2026-11-05T10:00' === WPPA_Link_Button::sanitize_datetime( '2026-11-05T10:00' );
$link_checks['мусор в дате отбрасывается'] = '' === WPPA_Link_Button::sanitize_datetime( 'завтра' );
$link_checks['отсчёт: часы и минуты']      = '01:01:01' === WPPA_Link_Button::countdown_text( 3661 );
$link_checks['отсчёт: дни']                = '1 д 01:00:00' === WPPA_Link_Button::countdown_text( 90000 );
$link_checks['отсчёт: не уходит в минус']  = '00:00:00' === WPPA_Link_Button::countdown_text( -50 );

// Блок в конструкторе и на «Обзоре» по умолчанию.
$link_checks['блок есть в конструкторе'] = isset( WPPA_Builder::blocks()['link-button'] );
$link_checks['блок стоит на «Обзоре» по умолчанию'] = in_array(
	'link-button',
	array_map(
		function ( $item ) {
			return $item['id'];
		},
		WPPA_Builder::defaults()['dashboard']
	),
	true
);

$link_checks['на других вкладках по умолчанию нет'] = ! in_array(
	'link-button',
	array_map(
		function ( $item ) {
			return $item['id'];
		},
		WPPA_Builder::defaults()['security']
	),
	true
);

// Сидинг для существующих установок: блок добавляется один раз.
delete_option( 'wppa_layout' );
update_option(
	'wppa_layout',
	array(
		'dashboard' => array(
			array( 'type' => 'block', 'id' => 'greeting', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
		),
	)
);
$seeded = WPPA_Builder::seed_block( 'link-button', 'dashboard' );
$link_checks['сидинг добавил блок в сохранённую раскладку'] = $seeded && 'link-button' === WPPA_Builder::layout()['dashboard'][0]['id'];
$link_checks['повторный сидинг ничего не дублирует']        = false === WPPA_Builder::seed_block( 'link-button', 'dashboard' );
delete_option( 'wppa_layout' );

update_option( 'wppa_settings', $link_settings_snapshot );
$GLOBALS['wppa_test']['meta']     = $link_meta_snapshot;
$GLOBALS['wppa_test']['posts']    = $link_posts_snapshot;
$GLOBALS['wppa_test']['postmeta'] = $link_postmeta_snapshot;

foreach ( $link_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'link button: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

$GLOBALS['wppa_test']['logged_in'] = true;

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
	'режим «оба»: кнопка не дублирует вкладки' => false === strpos( $widget_html, 'wppa_view=register' ),
);

// Режим «только вход»: кнопка регистрации внутри виджета нужна.
ob_start();
WPPA_Auth_Form::render( array( 'mode' => 'login' ) );
$widget_login_only = ob_get_clean();
$widget_checks['режим «только вход»: кнопка регистрации'] = false !== strpos( $widget_login_only, 'wppa_view=register' );

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

// Отдельные блоки: wppa/login и wppa/register.
$GLOBALS['wppa_test']['logged_in'] = false;
$registry = WPPA_Blocks::blocks();
$login_html = WPPA_Blocks::render( array( 'mode' => 'login', 'showTitle' => true, 'title' => 'Вход' ) );
$register_html_block = WPPA_Blocks::render( array( 'mode' => 'register' ) );

$separate_checks = array(
	'реестр: три блока'                 => 3 === count( $registry ) && isset( $registry['login'], $registry['register'], $registry['auth'] ),
	'имена блоков'                      => 'wppa/login' === $registry['login']['name'] && 'wppa/register' === $registry['register']['name'],
	'вход: своя обёртка'                => false !== strpos( $login_html, 'wp-block-wppa-login' ),
	'вход: форма входа'                 => false !== strpos( $login_html, 'value="login"' ),
	'вход: без полей регистрации'       => false === strpos( $login_html, 'wppa_fields[first_name]' ),
	'вход: заголовок'                   => false !== strpos( $login_html, 'Вход' ),
	'регистрация: своя обёртка'         => false !== strpos( $register_html_block, 'wp-block-wppa-register' ),
	'регистрация: поля персональных данных' => false !== strpos( $register_html_block, 'wppa_fields[first_name]' ),
	'регистрация: без формы входа'      => false === strpos( $register_html_block, 'name="log"' ),
	'кнопка входа'                      => false !== strpos( WPPA_Blocks::render( array( 'mode' => 'login', 'display' => 'button' ) ), 'wppa_view=login' ),
	'кнопка регистрации'                => false !== strpos( WPPA_Blocks::render( array( 'mode' => 'register', 'display' => 'button', 'buttonText' => 'Создать аккаунт' ) ), 'Создать аккаунт' ),
	'кнопка — это ссылка с классом'      => false !== strpos( WPPA_Blocks::render( array( 'mode' => 'login', 'display' => 'button' ) ), '<a class="wppa-button' ),
);

// Регистрация закрыта — кнопка регистрации не выводится.
update_option( 'users_can_register', 0 );
$separate_checks['кнопка регистрации при закрытой регистрации'] = '' === WPPA_Blocks::render( array( 'mode' => 'register', 'display' => 'button' ) );
update_option( 'users_can_register', 1 );

// hideOther убирает кнопку перехода внутри формы.
$login_no_switch = WPPA_Blocks::render( array( 'mode' => 'login', 'hideOther' => true ) );
$separate_checks['hideOther скрывает кнопку перехода'] = false === strpos( $login_no_switch, 'wppa_view=register' );

// Оформление блока: пресеты, ширина, кнопки, акцент.
$look_card = WPPA_Blocks::render(
	array(
		'mode'            => 'login',
		'appearance'      => 'card',
		'maxWidth'        => 'medium',
		'buttonSize'      => 'lg',
		'buttonFullWidth' => true,
		'accentColor'     => '#16a34a',
		'cornerRadius'    => 20,
	)
);

$separate_checks['оформление: карточка']           = false !== strpos( $look_card, 'wppa-block--card' );
$separate_checks['оформление: ширина']             = false !== strpos( $look_card, 'wppa-block--w-medium' );
$separate_checks['оформление: размер кнопок']      = false !== strpos( $look_card, 'wppa-block--btn-lg' );
$separate_checks['оформление: кнопки во всю ширину'] = false !== strpos( $look_card, 'wppa-block--btn-full' );
$separate_checks['оформление: акцентная переменная'] = false !== strpos( $look_card, '--wppa-accent:#16a34a' );
$separate_checks['оформление: радиус']             = false !== strpos( $look_card, '--wppa-radius:20px' );
$separate_checks['оформление: по умолчанию без карточки'] = false !== strpos( WPPA_Blocks::render( array( 'mode' => 'login' ) ), 'wppa-block--plain' );

// Кнопка-ссылка с оформлением.
$look_button = WPPA_Blocks::render(
	array(
		'mode'            => 'register',
		'display'         => 'button',
		'buttonStyle'     => 'ghost',
		'buttonSize'      => 'lg',
		'buttonFullWidth' => true,
		'appearance'      => 'bordered',
	)
);

$separate_checks['кнопка: вторичный стиль']   = false !== strpos( $look_button, 'wppa-button--ghost' );
$separate_checks['кнопка: крупный размер']    = false !== strpos( $look_button, 'wppa-button--lg' );
$separate_checks['кнопка: во всю ширину']     = false !== strpos( $look_button, 'wppa-button--block' );
$separate_checks['кнопка: рамка обёртки']     = false !== strpos( $look_button, 'wppa-block--bordered' );
$separate_checks['некорректный акцент игнорируется'] = false === strpos( WPPA_Blocks::render( array( 'mode' => 'login', 'accentColor' => 'javascript:alert(1)' ) ), 'javascript' );

foreach ( $separate_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'blocks separate: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

$GLOBALS['wppa_test']['logged_in'] = true;
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
