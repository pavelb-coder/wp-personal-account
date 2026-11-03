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
