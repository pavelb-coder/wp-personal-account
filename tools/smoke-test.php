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

require $base . 'includes/class-wppa-time.php';
require $base . 'includes/class-wppa-plugin.php';
require $base . 'includes/class-wppa-template.php';
require $base . 'includes/class-wppa-notices.php';
require $base . 'includes/class-wppa-settings.php';
require $base . 'includes/class-wppa-fields.php';
require $base . 'includes/class-wppa-password.php';
require $base . 'includes/class-wppa-export.php';
require $base . 'includes/class-wppa-export-folder.php';
require $base . 'includes/class-wppa-smb.php';
require $base . 'includes/class-wppa-curator-files.php';
require $base . 'includes/class-wppa-team-files-export.php';
require $base . 'includes/class-wppa-mailing.php';
require $base . 'includes/class-wppa-olympiad.php';
require $base . 'includes/class-wppa-reset.php';
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

	$mentor_ui['капитан: приглашения доступны']     = false !== strpos( $owner_html, 'Пригласить участников' );
	$mentor_ui['капитан: переименование доступно']  = false !== strpos( $owner_html, 'value="rename"' );
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
			array( 'type' => 'block', 'id' => 'profile-summary', 'enabled' => 0, 'title' => '', 'audience' => 'all', 'html' => '' ),
		),
	)
);

ob_start();
WPPA_Builder::render_tab( 'dashboard', array( 'user' => new WP_User(), 'tabs' => WPPA_Template::tabs() ) );
$built = ob_get_clean();

$checks = array(
	'заголовок-переопределение' => false !== strpos( $built, 'С возвращением' ),
	'свой HTML-блок'            => false !== strpos( $built, 'Заполните профиль.' ),
	'выключенный блок скрыт'    => false === strpos( $built, 'Сводка профиля' ),
);

foreach ( $checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'builder: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// Блок «Быстрые действия» удалён в 2.15.1.
$removed_checks = array(
	'блока нет в реестре'   => ! isset( WPPA_Builder::blocks()['quick-actions'] ),
	'блока нет в раскладке' => ! in_array(
		'quick-actions',
		array_map(
			function ( $item ) {
				return $item['id'];
			},
			call_user_func_array( 'array_merge', array_values( WPPA_Builder::defaults() ) )
		),
		true
	),
	'шаблона нет на диске'  => ! file_exists( WPPA_PATH . 'templates/blocks/quick-actions.php' ),
);

update_option(
	WPPA_Builder::OPTION,
	array(
		'dashboard' => array(
			array( 'type' => 'block', 'id' => 'greeting', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
			array( 'type' => 'block', 'id' => 'quick-actions', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
		),
	)
);

$removed_checks['drop_block чистит сохранённую раскладку'] = WPPA_Builder::drop_block( 'quick-actions' )
	&& 1 === count( WPPA_Builder::layout()['dashboard'] )
	&& 'greeting' === WPPA_Builder::layout()['dashboard'][0]['id'];

$removed_checks['повторный drop_block ничего не меняет'] = false === WPPA_Builder::drop_block( 'quick-actions' );

foreach ( $removed_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'removed: ' . $check_name . "\n";
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
	'note'         => '',
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

// Наставник в зачёт минимума не идёт: он есть, но участников всё равно не хватает.
$GLOBALS['wppa_test']['meta'][8] = array( '_wppa_team_id' => 901, '_wppa_team_role' => 'mentor' );
$state = wppa_link_state( array_merge( $link_base, array( 'require_team' => 1 ) ) );
$link_checks['наставник не считается участником'] = 'team' === $state['status'] && 1 === $state['team']['members'];
$link_checks['наставник есть — про него не напоминаем'] = false === strpos( $state['message'], 'наставник' );

// Минимум набран, но наставника нет — команда не сформирована.
$GLOBALS['wppa_test']['meta'][9] = array( '_wppa_team_id' => 901, '_wppa_team_role' => 'member' );
unset( $GLOBALS['wppa_test']['meta'][8] );
$state = wppa_link_state( array_merge( $link_base, array( 'require_team' => 1 ) ) );
$link_checks['минимум без наставника — закрыто']  = 'team' === $state['status'] && ! $state['is_open'];
$link_checks['минимум без наставника — текст']    = false !== strpos( $state['message'], 'Ждём наставника' );
$link_checks['прогресс: участники есть, наставника нет'] = 2 === $state['team']['members'] && false === $state['team']['has_mentor'];

// Наставник назначен — условие закрыто.
$GLOBALS['wppa_test']['meta'][8] = array( '_wppa_team_id' => 901, '_wppa_team_role' => 'mentor' );
$state = wppa_link_state( array_merge( $link_base, array( 'require_team' => 1 ) ) );
$link_checks['минимум и наставник — открыто'] = 'ready' === $state['status'] && $state['is_open'] && 2 === $state['team']['members'] && $state['team']['has_mentor'];

// Требование наставника выключено — хватает минимума.
unset( $GLOBALS['wppa_test']['meta'][8] );
update_option( 'wppa_settings', array_merge( (array) get_option( 'wppa_settings', array() ), array( 'require_mentor' => 0 ) ) );
$state = wppa_link_state( array_merge( $link_base, array( 'require_team' => 1 ) ) );
$link_checks['наставник не требуется — открыто'] = 'ready' === $state['status'] && $state['is_open'];

update_option( 'wppa_settings', array_merge( (array) get_option( 'wppa_settings', array() ), array( 'require_mentor' => 1 ) ) );
$GLOBALS['wppa_test']['meta'][8] = array( '_wppa_team_id' => 901, '_wppa_team_role' => 'mentor' );

// Условие команды + окно дат одновременно.
$state = wppa_link_state( array_merge( $link_base, array( 'require_team' => 1, 'start' => gmdate( 'Y-m-d\TH:i', time() + 3600 ) ) ) );
$link_checks['команда есть, но рано — отсчёт'] = 'before' === $state['status'] && $state['countdown'] > time();

// Условия команды: gaps() и is_complete().
$team_gaps_checks = array(
	'gaps: всё в порядке'                => array( 'members' => 0, 'mentor' => false ) === WPPA_Teams::gaps( 901 ),
	'is_complete: состав и наставник'    => true === WPPA_Teams::is_complete( 901 ),
	'has_mentor: наставник есть'         => true === WPPA_Teams::has_mentor( 901 ),
	'require_mentor включено по умолчанию' => true === WPPA_Teams::require_mentor(),
);

unset( $GLOBALS['wppa_test']['meta'][8] );
$team_gaps_checks['gaps: нет наставника']    = array( 'members' => 0, 'mentor' => true ) === WPPA_Teams::gaps( 901 );
$team_gaps_checks['is_complete: без наставника — нет'] = false === WPPA_Teams::is_complete( 901 );
$team_gaps_checks['is_missing_mentor']       = true === WPPA_Teams::is_missing_mentor( 901 );

update_option( 'wppa_settings', array_merge( (array) get_option( 'wppa_settings', array() ), array( 'require_mentor' => 0 ) ) );
$team_gaps_checks['выключено — наставник не мешает'] = false === WPPA_Teams::is_missing_mentor( 901 ) && true === WPPA_Teams::is_complete( 901 );
update_option( 'wppa_settings', array_merge( (array) get_option( 'wppa_settings', array() ), array( 'require_mentor' => 1 ) ) );

unset( $GLOBALS['wppa_test']['meta'][9] );
$team_gaps_checks['is_complete: пусто — нет'] = false === WPPA_Teams::is_complete( 901 );
$GLOBALS['wppa_test']['meta'][8] = array( '_wppa_team_id' => 901, '_wppa_team_role' => 'mentor' );
$GLOBALS['wppa_test']['meta'][9] = array( '_wppa_team_id' => 901, '_wppa_team_role' => 'member' );

foreach ( $team_gaps_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'team mentor: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

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

// Комментарий к кнопке: необязательный текст под кнопкой.
$link_checks['комментарий: пусто — блока нет'] = false === strpos( $link_html, 'wppa-link-block__note' );

$state = wppa_link_state( array_merge( $link_base, array( 'note' => "Ссылка ведёт на платформу.\nЛогин — ваш email." ) ) );
$link_checks['комментарий: есть в состоянии'] = "Ссылка ведёт на платформу.\nЛогин — ваш email." === $state['note'];

$note_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'link-button', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array()
);
$link_checks['комментарий: выведен под кнопкой']    = false !== strpos( $note_html, 'wppa-link-block__note' );
$link_checks['комментарий: перенос строки — <br />'] = false !== strpos( $note_html, 'платформу.<br />' );

$state = wppa_link_state( array_merge( $link_base, array( 'end' => gmdate( 'Y-m-d\\TH:i', time() - 60 ), 'note' => 'Доступ закроется вместе с приёмом работ.' ) ) );

$note_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'link-button', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array()
);
$link_checks['комментарий: виден и у закрытой кнопки'] = false !== strpos( $note_html, 'Доступ закроется вместе с приёмом работ.' );

$note_saved = WPPA_Settings::sanitize(
	array(
		'link_button' => array(
			'url'  => 'https://example.com/go',
			'note' => "  <b>Курсив</b> и текст\nвторая строка  ",
		),
	)
);
$link_checks['комментарий: разметка вырезается'] = "Курсив и текст\nвторая строка" === $note_saved['link_button']['note'];
$link_checks['комментарий: по умолчанию пусто']  = '' === WPPA_Link_Button::defaults()['note'];


$state = wppa_link_state( array_merge( $link_base, array( 'start' => gmdate( 'Y-m-d\TH:i', time() + 7200 ) ) ) );
$link_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'link-button', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array()
);
$link_checks['таймер выводится в разметке'] = false !== strpos( $link_html, 'data-wppa-countdown=' );
$link_checks['таймер перезагружает страницу'] = false !== strpos( $link_html, 'data-wppa-reload="1"' );
$link_checks['адрес не утекает в разметке']   = false === strpos( $link_html, 'https://example.com/go' );

// Наставнику кнопка не выдаётся — ни открытой, ни закрытой.
$GLOBALS['wppa_test']['meta'] = array(
	7 => array( '_wppa_team_id' => 901, '_wppa_team_role' => 'owner' ),
	8 => array( '_wppa_team_id' => 901, '_wppa_team_role' => 'mentor' ),
	9 => array( '_wppa_team_id' => 901, '_wppa_team_role' => 'member' ),
);
$GLOBALS['wppa_test']['current_user'] = 8;

$state = wppa_link_state( array_merge( $link_base, array( 'require_team' => 0 ) ) );
$link_checks['наставник — статус mentor']        = 'mentor' === $state['status'] && true === $state['is_mentor'];
$link_checks['наставник — кнопка не открыта']    = false === $state['is_open'];
$link_checks['наставник — без отсчёта и дат']    = 0 === $state['countdown'];
$link_checks['is_mentor: наставник']             = true === WPPA_Link_Button::is_mentor( 8 );

$link_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'link-button', 'enabled' => 1, 'title' => 'Трансляция', 'audience' => 'all', 'html' => '' ),
	array()
);
$link_checks['наставник — блок не выводится']      = '' === trim( $link_html );
$link_checks['наставник — адреса нет в разметке']  = false === strpos( $link_html, 'example.com' );
$link_checks['наставник — заголовка блока нет']    = false === strpos( $link_html, 'Трансляция' );

// Условие по команде не важно: наставник без доступа в любом случае.
$state = wppa_link_state( array_merge( $link_base, array( 'require_team' => 1 ) ) );
$link_checks['наставник — и при условии по команде mentor'] = 'mentor' === $state['status'];

// Участники и капитан кнопку получают.
$GLOBALS['wppa_test']['current_user'] = 9;
$state = wppa_link_state( array_merge( $link_base, array( 'require_team' => 0 ) ) );
$link_checks['участник — кнопка открыта'] = 'ready' === $state['status'] && $state['is_open'];
$link_checks['is_mentor: участник']       = false === WPPA_Link_Button::is_mentor( 9 );

$member_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'link-button', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array()
);
$link_checks['участник — ссылка в разметке'] = false !== strpos( $member_html, 'href="https://example.com/go"' );

$GLOBALS['wppa_test']['current_user'] = 7;
$state = wppa_link_state( array_merge( $link_base, array( 'require_team' => 0 ) ) );
$link_checks['капитан — кнопка открыта']   = 'ready' === $state['status'] && $state['is_open'];
$link_checks['is_mentor: капитан']         = false === WPPA_Link_Button::is_mentor( 7 );
$link_checks['is_mentor: без команды']     = false === WPPA_Link_Button::is_mentor( 555 );

$captain_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'link-button', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array()
);
$link_checks['капитан — ссылка в разметке'] = false !== strpos( $captain_html, 'href="https://example.com/go"' );

unset( $GLOBALS['wppa_test']['current_user'] );

// Форматирование и сохранение дат.
$link_checks['datetime-local сохраняется'] = '2026-11-05T10:00' === WPPA_Link_Button::sanitize_datetime( '2026-11-05T10:00' );
$link_checks['мусор в дате отбрасывается'] = '' === WPPA_Link_Button::sanitize_datetime( 'завтра' );
$link_checks['отсчёт: часы и минуты']      = '01:01:01' === WPPA_Link_Button::countdown_text( 3661 );
$link_checks['отсчёт: дни']                = '1 д 01:00:00' === WPPA_Link_Button::countdown_text( 90000 );
$link_checks['отсчёт: не уходит в минус']  = '00:00:00' === WPPA_Link_Button::countdown_text( -50 );

// Проверка календаря: date_create() «додумывал» несуществующие даты.
$date_checks = array(
	'30 февраля отклонено'        => '' === WPPA_Link_Button::sanitize_datetime( '2026-02-30T10:00' ),
	'месяц 13 отклонён'           => '' === WPPA_Link_Button::sanitize_datetime( '2026-13-01T10:00' ),
	'часы 99 отклонены'           => '' === WPPA_Link_Button::sanitize_datetime( '2026-11-05T99:99' ),
	'минуты 60 отклонены'         => '' === WPPA_Link_Button::sanitize_datetime( '2026-11-05T10:60' ),
	'29 февраля високосного года' => '2028-02-29T10:00' === WPPA_Link_Button::sanitize_datetime( '2028-02-29T10:00' ),
	'29 февраля невисокосного'    => '' === WPPA_Link_Button::sanitize_datetime( '2027-02-29T10:00' ),
	'год 1900 отклонён'           => '' === WPPA_Link_Button::sanitize_datetime( '1900-01-01T00:00' ),
	'корректная дата сохраняется' => '2026-11-05T10:00' === WPPA_Link_Button::sanitize_datetime( '2026-11-05T10:00' ),
	'пустое значение остаётся пустым' => '' === WPPA_Link_Button::sanitize_datetime( '' ),
);

// Даты личных полей: та же проблема с регуляркой.
$date_checks['поле birthdate: 30 февраля'] = '' === WPPA_Fields::sanitize_value( 'birthdate', '2026-02-30' );
$date_checks['поле birthdate: месяц 13']   = '' === WPPA_Fields::sanitize_value( 'birthdate', '2026-13-01' );
$date_checks['поле birthdate: верная дата'] = '1990-05-05' === WPPA_Fields::sanitize_value( 'birthdate', '1990-05-05' );

foreach ( $date_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'date check: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

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

// ---------- Регистрация по таймеру ----------
$reg_settings_snapshot = get_option( 'wppa_settings', array() );

/**
 * Настройки окна регистрации.
 *
 * @param string $start Начало (datetime-local).
 * @param string $end   Конец.
 * @return void
 */
function wppa_reg_window( $start = '', $end = '' ) {
	update_option(
		'wppa_settings',
		array_merge(
			(array) get_option( 'wppa_settings', array() ),
			array(
				'registration_enabled' => 1,
				'registration_start'   => $start,
				'registration_end'     => $end,
			)
		)
	);
}

$reg_checks = array();

wppa_reg_window();
$window = WPPA_Plugin::registration_window();
$reg_checks['без дат — окно открыто']      = 'open' === $window['state'] && ! $window['limited'];
$reg_checks['без дат — регистрация идёт']  = true === WPPA_Plugin::registration_live();

wppa_reg_window( gmdate( 'Y-m-d\TH:i', time() + 3600 ), '' );
$window = WPPA_Plugin::registration_window();
$reg_checks['до начала — состояние before']   = 'before' === $window['state'];
$reg_checks['до начала — регистрация стоит']  = false === WPPA_Plugin::registration_live();
$reg_checks['до начала — есть дата']          = $window['start'] > time();
$reg_checks['до начала — есть отсчёт']        = $window['countdown'] === $window['start'];
$reg_checks['до начала — сообщение']          = false !== strpos( $window['message'], 'Регистрация откроется' );

wppa_reg_window( gmdate( 'Y-m-d\TH:i', time() - 7200 ), '' );
$window = WPPA_Plugin::registration_window();
$reg_checks['после начала — регистрация идёт'] = 'open' === $window['state'] && true === WPPA_Plugin::registration_live();

wppa_reg_window( '', gmdate( 'Y-m-d\TH:i', time() - 60 ) );
$window = WPPA_Plugin::registration_window();
$reg_checks['после конца — состояние after']  = 'after' === $window['state'];
$reg_checks['после конца — регистрация стоит'] = false === WPPA_Plugin::registration_live();
$reg_checks['после конца — сообщение']        = false !== strpos( $window['message'], 'Регистрация закрыта' );

wppa_reg_window( gmdate( 'Y-m-d\TH:i', time() - 7200 ), gmdate( 'Y-m-d\TH:i', time() + 7200 ) );
$window = WPPA_Plugin::registration_window();
$reg_checks['внутри окна — регистрация идёт'] = 'open' === $window['state'] && true === WPPA_Plugin::registration_live();

// Форма регистрации и кнопки внутри окна и вне его.
$reg_open_html = WPPA_Template::get( 'register', array( 'title' => '' ) );
$reg_checks['окно открыто — форма на месте'] = false !== strpos( $reg_open_html, 'name="wppa_action" value="register"' );

wppa_reg_window( gmdate( 'Y-m-d\TH:i', time() + 3600 ), '' );
$reg_locked_html = WPPA_Template::get( 'register', array( 'title' => '' ) );
$reg_checks['до открытия — формы нет']            = false === strpos( $reg_locked_html, 'name="wppa_action" value="register"' );
$reg_checks['до открытия — заглушка с таймером']  = false !== strpos( $reg_locked_html, 'wppa-register-locked' ) && false !== strpos( $reg_locked_html, 'data-wppa-countdown=' );
$reg_checks['до открытия — текст об открытии']    = false !== strpos( $reg_locked_html, 'Форма регистрации откроется' );

$login_locked_html = WPPA_Template::get( 'login', array( 'title' => '' ) );
$reg_checks['вход: кнопка регистрации неактивна'] = false !== strpos( $login_locked_html, 'wppa-button--disabled' );
$reg_checks['вход: подсказка о времени']          = false !== strpos( $login_locked_html, 'Регистрация откроется' );

wppa_reg_window();
$login_open_html = WPPA_Template::get( 'login', array( 'title' => '' ) );
$reg_checks['вход: кнопка регистрации ведёт на форму'] = false !== strpos( $login_open_html, 'wppa_view=register' )
	&& false === strpos( $login_open_html, 'wppa-button--disabled' );

// Кнопка блока wppa/register вне окна.
wppa_reg_window( gmdate( 'Y-m-d\TH:i', time() + 3600 ), '' );
$reg_block_button = WPPA_Blocks::render( array( 'mode' => 'register', 'display' => 'button' ) );
$reg_checks['блок-кнопка: нет ссылки']       = false === strpos( $reg_block_button, 'wppa_view=register' );
$reg_checks['блок-кнопка: aria-disabled']    = false !== strpos( $reg_block_button, 'aria-disabled="true"' );
$reg_checks['блок-кнопка: отсчёт']           = false !== strpos( $reg_block_button, 'data-wppa-countdown=' );

wppa_reg_window();
$reg_block_button = WPPA_Blocks::render( array( 'mode' => 'register', 'display' => 'button' ) );
$reg_checks['блок-кнопка открыта — ссылка есть'] = false !== strpos( $reg_block_button, 'wppa_view=register' );

// Серверная блокировка: коды уведомлений на месте.
$notices = WPPA_Notices::registry();
$reg_checks['уведомление «ещё не открыта»'] = isset( $notices['registration_not_yet'] );
$reg_checks['уведомление «закрыта»']        = isset( $notices['registration_over'] );

update_option( 'wppa_settings', $reg_settings_snapshot );

foreach ( $reg_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'registration window: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// ---------- «Обзор» без личных данных и email ----------
$dashboard_layout_snapshot = get_option( WPPA_Builder::OPTION, null );
delete_option( WPPA_Builder::OPTION );

$demo_user = get_userdata( 7 );
$demo_user->user_email     = 'ivan.petrov@example.com';
$demo_user->display_name   = 'Иван Петров';

$dashboard_html = WPPA_Template::get(
	'account',
	array(
		'user'        => $demo_user,
		'tabs'        => WPPA_Template::tabs(),
		'current_tab' => 'dashboard',
	)
);

$profile_html = WPPA_Template::get(
	'account',
	array(
		'user'        => $demo_user,
		'tabs'        => WPPA_Template::tabs(),
		'current_tab' => 'profile',
	)
);

$dashboard_checks = array(
	'обзор: нет карточки email'        => false === strpos( $dashboard_html, 'wppa-header__email' ) && false === strpos( $dashboard_html, 'ivan.petrov@example.com' ),
	'обзор: нет блока личных данных'   => false === strpos( $dashboard_html, 'Личные данные' ),
	'обзор: нет ссылки «Редактировать данные»' => false === strpos( $dashboard_html, 'Редактировать данные' ),
	'обзор: дата регистрации на месте' => false !== strpos( $dashboard_html, 'Дата регистрации' ),
	'обзор: имя в шапке осталось'      => false !== strpos( $dashboard_html, 'Иван Петров' ),
	'профиль: email в шапке остаётся'  => false !== strpos( $profile_html, 'ivan.petrov@example.com' ),
);

$summary_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'profile-summary', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array( 'user' => $demo_user, 'tabs' => WPPA_Template::tabs() )
);

$dashboard_checks['сводка: нет email']            = false === strpos( $summary_html, 'ivan.petrov@example.com' );
$dashboard_checks['сводка: нет личных данных']    = false === strpos( $summary_html, 'Личные данные' );
$dashboard_checks['сводка: дата регистрации']     = false !== strpos( $summary_html, 'Дата регистрации' );

if ( null !== $dashboard_layout_snapshot ) {
	update_option( WPPA_Builder::OPTION, $dashboard_layout_snapshot );
}

foreach ( $dashboard_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'dashboard: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// ---------- Панель WordPress в кабинете ----------
$bar_settings_snapshot = get_option( 'wppa_settings', array() );

/**
 * Прогон скрытия админ-бара и возврат флага.
 *
 * @return bool|null true — скрыт, false — показан, null — не трогали.
 */
function wppa_admin_bar_state() {
	$GLOBALS['wppa_test']['admin_bar'] = null;
	WPPA_Plugin::maybe_hide_admin_bar();
	return $GLOBALS['wppa_test']['admin_bar'];
}

$bar_checks = array();

update_option( 'wppa_settings', array_merge( (array) get_option( 'wppa_settings', array() ), array( 'hide_admin_bar' => 1 ) ) );
$GLOBALS['wppa_test']['logged_in']      = true;
$GLOBALS['wppa_test']['is_admin']       = false;
$GLOBALS['wppa_test']['is_singular']    = true;

$bar_checks['в кабинете панель скрыта'] = false === wppa_admin_bar_state();

$GLOBALS['wppa_test']['is_admin'] = true;
$bar_checks['в админке панель не трогаем'] = null === wppa_admin_bar_state();
$GLOBALS['wppa_test']['is_admin'] = false;

$GLOBALS['wppa_test']['logged_in'] = false;
$bar_checks['гостю панель не нужна'] = null === wppa_admin_bar_state();
$GLOBALS['wppa_test']['logged_in'] = true;

$GLOBALS['wppa_test']['doing_ajax'] = true;
$bar_checks['в ajax не вмешиваемся'] = null === wppa_admin_bar_state();
$GLOBALS['wppa_test']['doing_ajax'] = false;

$GLOBALS['wppa_test']['is_singular']  = false;
unset( $GLOBALS['wppa_test']['has_block'] );
update_option( 'wppa_settings', array_merge( (array) get_option( 'wppa_settings', array() ), array( 'account_page_id' => 0 ) ) );
$bar_checks['не кабинет — панель остаётся'] = null === wppa_admin_bar_state();
$GLOBALS['wppa_test']['is_singular'] = true;

// Кабинет через блок на произвольной странице.
update_option( 'wppa_settings', array_merge( (array) get_option( 'wppa_settings', array() ), array( 'account_page_id' => 0 ) ) );
$GLOBALS['wppa_test']['has_block'] = array( 'wppa/auth' => true );
$bar_checks['кабинет блоком на другой странице'] = false === wppa_admin_bar_state();
unset( $GLOBALS['wppa_test']['has_block'] );

// Настройка выключена — панель остаётся.
update_option( 'wppa_settings', array_merge( (array) get_option( 'wppa_settings', array() ), array( 'hide_admin_bar' => 0, 'account_page_id' => 5 ) ) );
$bar_checks['настройка выключена — панель видна'] = null === wppa_admin_bar_state();

update_option( 'wppa_settings', $bar_settings_snapshot );
$GLOBALS['wppa_test']['admin_bar'] = null;

foreach ( $bar_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'admin bar: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// ---------- Восстановление пароля внутри кабинета ----------
$reset_settings_snapshot = get_option( 'wppa_settings', array() );

update_option( 'wppa_settings', array_merge( (array) get_option( 'wppa_settings', array() ), array( 'account_page_id' => 5 ) ) );

$reset_checks = array(
	'ссылка «Забыли пароль?» ведёт в кабинет' => false !== strpos( WPPA_Plugin::lost_password_url(), 'wppa_view=lostpassword' )
		&& false === strpos( WPPA_Plugin::lost_password_url(), 'wp-login.php' ),
	'экран сброса: ключ и логин в ссылке'      => false !== strpos( WPPA_Reset::reset_url( 'ivan', 'test-reset-key' ), 'wppa_key=test-reset-key' ),
);

$login_form_html = WPPA_Template::get( 'login', array( 'title' => '' ) );
$reset_checks['форма входа не ведёт на wp-login.php'] = false === strpos( $login_form_html, 'wp-login.php' )
	&& false !== strpos( $login_form_html, 'wppa_view=lostpassword' );

$lost_html = WPPA_Template::get( 'lost-password', array() );
$reset_checks['форма заявки: поле логина']   = false !== strpos( $lost_html, 'name="user_login"' );
$reset_checks['форма заявки: действие']      = false !== strpos( $lost_html, 'value="lostpassword"' );
$reset_checks['форма заявки: кнопка входа']  = false !== strpos( $lost_html, 'wppa_view=login' );

// Письмо со ссылкой.
$reset_user = new WP_User( 7 );
$mail_body  = WPPA_Reset::mail_body( $reset_user, 'test-reset-key' );
$reset_checks['письмо содержит ссылку на кабинет'] = false !== strpos( $mail_body, 'wppa_view=resetpass' )
	&& false === strpos( $mail_body, 'wp-login.php' );
$reset_checks['письмо объясняет срок жизни']       = false !== strpos( $mail_body, '24 часа' );

// Экран установки пароля: недействительный ключ.
$bad_html = WPPA_Template::get( 'reset-password', array( 'key' => 'broken-key', 'login' => 'ivan' ) );
$reset_checks['неверный ключ: формы нет']        = false === strpos( $bad_html, 'name="wppa_action" value="resetpass"' );
$reset_checks['неверный ключ: просьба запросить заново'] = false !== strpos( $bad_html, 'wppa_view=lostpassword' );

// Экран установки пароля: верный ключ.
$good_html = WPPA_Template::get( 'reset-password', array( 'key' => 'test-reset-key', 'login' => 'ivan' ) );
$reset_checks['верный ключ: форма есть']         = false !== strpos( $good_html, 'value="resetpass"' );
$reset_checks['верный ключ: minlength=16']       = false !== strpos( $good_html, 'minlength="16"' );
$reset_checks['верный ключ: требования к паролю'] = false !== strpos( $good_html, 'data-wppa-password-rules' );
$reset_checks['верный ключ: ключ в скрытом поле'] = false !== strpos( $good_html, 'value="test-reset-key"' );

// Экран гостя переключается на новые формы.
$guest_lost = WPPA_Template::get( 'guest', array( 'view' => 'lostpassword', 'registration' => true ) );
$reset_checks['экран гостя: форма заявки']   = false !== strpos( $guest_lost, 'name="user_login"' );
$guest_reset = WPPA_Template::get( 'guest', array( 'view' => 'resetpass', 'registration' => true, 'key' => 'test-reset-key', 'login' => 'ivan' ) );
$reset_checks['экран гостя: форма нового пароля'] = false !== strpos( $guest_reset, 'value="resetpass"' );

// Коды уведомлений.
$notices = WPPA_Notices::registry();
$reset_checks['уведомления сброса на месте'] = isset( $notices['reset_sent'], $notices['reset_done'], $notices['reset_link_invalid'], $notices['reset_too_fast'] );

// Поиск пользователя и защита от частых заявок.
$GLOBALS['wppa_test']['users_by'] = array(
	'email' => array( 'ivan@example.com' => 7 ),
	'login' => array( 'ivan' => 7 ),
);
$reset_checks['поиск по email']  = 7 === WPPA_Reset::find_user( 'ivan@example.com' )->ID;
$reset_checks['поиск по логину'] = 7 === WPPA_Reset::find_user( 'ivan' )->ID;
$reset_checks['чужой логин — не найден'] = null === WPPA_Reset::find_user( 'nobody' );
$reset_checks['пустой логин — не найден'] = null === WPPA_Reset::find_user( '  ' );

// Проверка ключа.
$reset_checks['верный ключ принимается']   = null !== WPPA_Reset::validate( 'test-reset-key', 'ivan' );
$reset_checks['неверный ключ отклоняется'] = null === WPPA_Reset::validate( 'broken-key', 'ivan' );
$reset_checks['пустой ключ отклоняется']   = null === WPPA_Reset::validate( '', 'ivan' );

update_option( 'wppa_settings', $reset_settings_snapshot );
unset( $GLOBALS['wppa_test']['users_by'] );

foreach ( $reset_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'password reset: ' . $check_name . "\n";
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

// Куратор: статус на уровне сайта (3.10.0).
$curator_checks     = array();
$curator_snapshot   = isset( $GLOBALS['wppa_test']['meta'] ) ? $GLOBALS['wppa_test']['meta'] : array();
$curator_user       = 41;
$curator_other      = 42;
$curator_member     = 43;

$GLOBALS['wppa_test']['meta'] = $curator_snapshot;

$curator_checks['по умолчанию не куратор'] = false === WPPA_Teams::is_curator( $curator_user );
$curator_checks['назначение куратора']     = true === WPPA_Teams::set_curator( $curator_user, true );
$curator_checks['куратор отмечен']         = true === WPPA_Teams::is_curator( $curator_user );
$curator_checks['мета-ключ куратора']      = '1' === (string) get_user_meta( $curator_user, WPPA_Teams::META_CURATOR, true );
$curator_checks['чужой пользователь — нет'] = false === WPPA_Teams::is_curator( $curator_other );
$curator_checks['несуществующий не назначается'] = false === WPPA_Teams::set_curator( 0, true );
$curator_checks['подпись роли']            = 'Куратор' === WPPA_Teams::role_label( 'curator' );

$curator_names = array_map(
	static function ( $user ) {
		return (int) $user->ID;
	},
	WPPA_Teams::curators()
);
$curator_checks['список кураторов'] = in_array( $curator_user, $curator_names, true );

// Куратор управляет любой командой, участник — только своей.
$curator_team_id = WPPA_Teams::create( 'Команда для куратора', $curator_other );

if ( is_wp_error( $curator_team_id ) ) {
	$curator_checks['команда создана'] = false;
} else {
	$curator_checks['команда создана']                   = true;
	$curator_checks['куратор состав не ведёт']           = false === WPPA_Teams::can_manage_team( $curator_team_id, $curator_user );
	$curator_checks['капитан управляет своей']           = true === WPPA_Teams::can_manage_team( $curator_team_id, $curator_other );
	$curator_checks['посторонний не управляет']          = false === WPPA_Teams::can_manage_team( $curator_team_id, 555 );
	$curator_checks['не команда — нельзя']               = false === WPPA_Teams::can_manage_team( 0, $curator_user );

	// В команде есть участник — иначе нечего исключать и некого делать наставником.
	WPPA_Teams::add_member( $curator_team_id, $curator_member );

	$all_teams  = WPPA_Teams::all_teams();
	$team_row   = array();

	foreach ( $all_teams as $curator_row ) {
		if ( (int) $curator_row['id'] === (int) $curator_team_id ) {
			$team_row = $curator_row;
		}
	}

	$curator_checks['все команды: команда в списке'] = array() !== $team_row;
	$curator_checks['все команды: поля строки']     = isset( $team_row['name'], $team_row['owner'], $team_row['participants'], $team_row['mentors'], $team_row['complete'], $team_row['missing'] );
	$curator_checks['все команды: капитан']         = 'Куратор' !== $team_row['owner'];

	// Куратору кнопка перехода не выдаётся.
	$GLOBALS['wppa_test']['current_user'] = $curator_user;
	$curator_link_state = wppa_link_state( $link_base );
	$curator_checks['кнопка: статус curator']   = 'curator' === $curator_link_state['status'];
	$curator_checks['кнопка: не открыта']       = false === $curator_link_state['is_open'];

	$curator_link_html = WPPA_Builder::render_block(
		array( 'type' => 'block', 'id' => 'link-button', 'enabled' => 1, 'title' => 'Трансляция', 'audience' => 'all', 'html' => '' ),
		array()
	);
	$curator_checks['кнопка: блок не выводится'] = '' === trim( $curator_link_html );

	// Список команд в кабинете куратора.
	$team_block = array( 'type' => 'block', 'id' => 'team-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' );
	$curator_html = WPPA_Builder::render_block( $team_block, array() );

	$curator_checks['кабинет: панель куратора']     = false !== strpos( $curator_html, 'Куратор: все команды' );
	$curator_checks['кабинет: команда в списке']    = false !== strpos( $curator_html, 'Команда для куратора' );
	$curator_checks['кабинет: ссылка «Управлять»']  = false !== strpos( $curator_html, 'wppa_team=' . (int) $curator_team_id );

	// Открываем чужую команду: ?wppa_team=ID.
	$_GET['wppa_team'] = (int) $curator_team_id;

	$curator_checks['открытая команда определяется'] = (int) $curator_team_id === (int) WPPA_Teams::viewing_team_id( $curator_user );

	$view_html = WPPA_Builder::render_block( $team_block, array() );

	$curator_checks['просмотр: скрытое team_id']       = false !== strpos( $view_html, 'name="team_id" value="' . (int) $curator_team_id . '"' );
	$curator_checks['просмотр: нет переименования']    = false === strpos( $view_html, 'wppa_team_action" value="rename"' );
	$curator_checks['просмотр: нет карточки названия'] = false === strpos( $view_html, 'Название команды' );
	$curator_checks['просмотр: нет выхода из команды'] = false === strpos( $view_html, 'wppa_team_action" value="leave"' );
	$curator_checks['просмотр: плашка куратора']       = false !== strpos( $view_html, 'как куратор' );
	$curator_checks['просмотр: нет приглашения']        = false === strpos( $view_html, 'Пригласить участников' );
	$curator_checks['просмотр: нет поля email']         = false === strpos( $view_html, 'name="invite_email"' );
	$curator_checks['просмотр: нет ссылки-приглашения'] = false === strpos( $view_html, 'Ссылка-приглашение' );
	$curator_checks['просмотр: состава нет']            = false === strpos( $view_html, 'class="wppa-subtitle">Состав команды' );
	$curator_checks['просмотр: нет исключения']         = false === strpos( $view_html, 'Исключить' );
	$curator_checks['просмотр: нет наставника из состава'] = false === strpos( $view_html, 'Сделать наставником' );
	$curator_checks['просмотр: пояснение про состав']   = false !== strpos( $view_html, 'Список участников ведут капитан и наставник' );

	unset( $_GET['wppa_team'] );

	// team_id из формы куратора важнее своей команды.
	$_POST['team_id'] = (int) $curator_team_id;
	$_GET['wppa_team'] = (int) $curator_team_id;

	$curator_checks['team_id из формы'] = (int) $curator_team_id === (int) WPPA_Teams::request_team_id( $curator_user );

	unset( $_POST['team_id'], $_GET['wppa_team'] );

	// Обычный пользователь чужую команду не откроет.
	$GLOBALS['wppa_test']['current_user'] = $curator_other;
	$curator_checks['не куратор — чужой team_id не берётся'] = 0 !== (int) WPPA_Teams::viewing_team_id( $curator_other )
		? (int) $curator_team_id !== (int) WPPA_Teams::viewing_team_id( $curator_other )
		: true;

	WPPA_Teams::dissolve( $curator_team_id );
}

// Снятие куратора.
$curator_checks['снятие куратора']     = true === WPPA_Teams::set_curator( $curator_user, false );
$curator_checks['после снятия — нет']  = false === WPPA_Teams::is_curator( $curator_user );

$GLOBALS['wppa_test']['meta'] = $curator_snapshot;

foreach ( $curator_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'curator: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// Отметка «команда прошла во второй этап» (ставит куратор).
$stage_checks   = array();
$stage_snapshot = isset( $GLOBALS['wppa_test']['meta'] ) ? $GLOBALS['wppa_test']['meta'] : array();
$stage_team     = WPPA_Teams::create( 'Команда второго этапа', 71 );

if ( is_wp_error( $stage_team ) ) {
	$stage_checks['команда создана'] = false;
} else {
	$stage_checks['команда создана'] = true;

	WPPA_Teams::add_member( $stage_team, 72 );
	WPPA_Teams::make_mentor( $stage_team, 72 );
	WPPA_Teams::add_member( $stage_team, 73 );
	WPPA_Teams::set_curator( 74, true );

	$stage_checks['по умолчанию нет отметки'] = false === WPPA_Teams::is_stage2( $stage_team );
	$stage_checks['куратор ставит отметку']   = true === WPPA_Teams::set_stage2( $stage_team, true, 74 );
	$stage_checks['отметка видна']            = true === WPPA_Teams::is_stage2( $stage_team );
	$stage_checks['в отметке есть время']     = ! empty( WPPA_Teams::stage2( $stage_team )['time'] );
	$stage_checks['в отметке есть автор']     = 74 === (int) WPPA_Teams::stage2( $stage_team )['user'];
	$stage_checks['наставник не отмечает']    = false === WPPA_Teams::set_stage2( $stage_team, true, 72 );
	$stage_checks['участник не отмечает']     = false === WPPA_Teams::set_stage2( $stage_team, true, 73 );
	$stage_checks['посторонний не отмечает']  = false === WPPA_Teams::set_stage2( $stage_team, true, 999 );

	// Признак есть в списке команд.
	$stage_row = array();

	foreach ( WPPA_Teams::all_teams() as $stage_candidate ) {
		if ( (int) $stage_candidate['id'] === (int) $stage_team ) {
			$stage_row = $stage_candidate;
		}
	}

	$stage_checks['список: признак есть'] = ! empty( $stage_row['stage2'] );

	// Отметка видна на главной странице кабинета — всем участникам команды и наставнику.
	$stage_tabs        = WPPA_Template::tabs();
	$stage_dash_member = WPPA_Template::get( 'tab-dashboard', array( 'user' => get_userdata( 73 ), 'tabs' => $stage_tabs ) );
	$stage_checks['главная: участник видит отметку'] = false !== strpos( $stage_dash_member, 'Прошла во второй этап' );
	$stage_checks['главная: есть дата отметки']      = false !== strpos( $stage_dash_member, 'прошла во второй этап — отмечено' );
	$stage_checks['главная: есть название команды']  = false !== strpos( $stage_dash_member, 'Команда второго этапа' );
	$stage_checks['главная: ссылка на команду есть'] = false !== strpos( $stage_dash_member, 'Перейти к команде' );

	$stage_dash_owner = WPPA_Template::get( 'tab-dashboard', array( 'user' => get_userdata( 71 ), 'tabs' => $stage_tabs ) );
	$stage_checks['главная: капитан видит отметку'] = false !== strpos( $stage_dash_owner, 'Прошла во второй этап' );

	$stage_dash_mentor = WPPA_Template::get( 'tab-dashboard', array( 'user' => get_userdata( 72 ), 'tabs' => $stage_tabs ) );
	$stage_checks['главная: наставник видит отметку'] = false !== strpos( $stage_dash_mentor, 'Прошла во второй этап' );

	// Блок «Сводка профиля» дублирует отметку бейджем в карточке команды.
	$stage_summary_block = array( 'type' => 'block', 'id' => 'profile-summary', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' );
	$stage_summary_html  = WPPA_Builder::render_block( $stage_summary_block, array( 'user' => get_userdata( 73 ) ) );
	$stage_checks['сводка профиля: бейдж отметки'] = false !== strpos( $stage_summary_html, 'Прошла во второй этап' );

	// Бейдж видят все участники команды и куратор.
	$stage_block = array( 'type' => 'block', 'id' => 'team-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' );

	$GLOBALS['wppa_test']['current_user'] = 73;
	$stage_member_html = WPPA_Builder::render_block( $stage_block, array( 'user' => get_userdata( 73 ) ) );
	$stage_checks['участник видит отметку'] = false !== strpos( $stage_member_html, 'Прошла во второй этап' );

	$GLOBALS['wppa_test']['current_user'] = 72;
	$stage_mentor_html = WPPA_Builder::render_block( $stage_block, array( 'user' => get_userdata( 72 ) ) );
	$stage_checks['наставник видит отметку']   = false !== strpos( $stage_mentor_html, 'Прошла во второй этап' );
	$stage_checks['наставник не переключает']  = false === strpos( $stage_mentor_html, 'value="stage2_remove"' );

	$GLOBALS['wppa_test']['current_user'] = 74;
	$_GET['wppa_team']                     = (int) $stage_team;
	$stage_curator_html                    = WPPA_Builder::render_block( $stage_block, array( 'user' => get_userdata( 74 ) ) );

	$stage_checks['куратор видит переключатель'] = false !== strpos( $stage_curator_html, 'value="stage2_remove"' );
	$stage_checks['куратор видит колонку']       = false !== strpos( $stage_curator_html, 'Второй этап' );

	unset( $_GET['wppa_team'] );

	// Обработчики форм.
	$GLOBALS['wppa_test']['catch_redirect'] = true;

	$wppa_run_stage = function ( $user_id, $action ) use ( $stage_team ) {
		$GLOBALS['wppa_test']['current_user'] = $user_id;
		$_POST                                = array(
			'wppa_action'      => 'team',
			'wppa_team_action' => $action,
			'team_id'          => $stage_team,
		);
		$_GET                                 = array();

		try {
			WPPA_Teams::handle_request();
		} catch ( RuntimeException $e ) {
			$query = (string) parse_url( substr( $e->getMessage(), strlen( 'redirect:' ) ), PHP_URL_QUERY );
			$args  = array();

			parse_str( $query, $args );

			return isset( $args['wppa_notice'] ) ? (string) $args['wppa_notice'] : '';
		}

		return '';
	};

	WPPA_Teams::set_stage2( $stage_team, false, 74 );

	$stage_checks['обработчик: куратор ставит']   = 'stage2_added' === $wppa_run_stage( 74, 'stage2_add' );
	$stage_checks['обработчик: отметка появилась'] = true === WPPA_Teams::is_stage2( $stage_team );
	$stage_checks['обработчик: куратор снимает']  = 'stage2_removed' === $wppa_run_stage( 74, 'stage2_remove' );
	$stage_checks['обработчик: отметки нет']      = false === WPPA_Teams::is_stage2( $stage_team );

	$stage_dash_after = WPPA_Template::get( 'tab-dashboard', array( 'user' => get_userdata( 73 ), 'tabs' => $stage_tabs ) );
	$stage_checks['главная: после снятия блока нет'] = false === strpos( $stage_dash_after, 'wppa-stage2--notice' );
	$stage_checks['обработчик: наставнику нельзя'] = 'stage2_denied' === $wppa_run_stage( 72, 'stage2_add' );
	$stage_checks['обработчик: участнику нельзя']  = 'stage2_denied' === $wppa_run_stage( 73, 'stage2_add' );

	$GLOBALS['wppa_test']['catch_redirect'] = false;
	$_POST                                  = array();

	WPPA_Teams::dissolve( $stage_team );
}

WPPA_Teams::set_curator( 74, false );
$GLOBALS['wppa_test']['meta'] = $stage_snapshot;

foreach ( $stage_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'stage2: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// Действия через обработчик форм: куратор работает с составом, приглашает и переименовывает капитан.
$action_checks   = array();
$action_snapshot = isset( $GLOBALS['wppa_test']['meta'] ) ? $GLOBALS['wppa_test']['meta'] : array();
$action_team     = WPPA_Teams::create( 'Команда для действий', 51 );

if ( is_wp_error( $action_team ) ) {
	$action_checks['команда создана'] = false;
} else {
	$action_checks['команда создана'] = true;

	WPPA_Teams::add_member( $action_team, 53 );
	WPPA_Teams::make_mentor( $action_team, 53 );
	WPPA_Teams::add_member( $action_team, 54 );
	WPPA_Teams::set_curator( 52, true );

	$GLOBALS['wppa_test']['catch_redirect'] = true;

	// Прогоняет обработчик формы и возвращает код уведомления и адрес возврата.
	$wppa_run_action = function ( $user_id, $post ) {
		$GLOBALS['wppa_test']['current_user'] = $user_id;
		$_POST                                = $post;
		$_GET                                 = array();

		try {
			WPPA_Teams::handle_request();
		} catch ( RuntimeException $e ) {
			$url   = substr( $e->getMessage(), strlen( 'redirect:' ) );
			$query = (string) parse_url( $url, PHP_URL_QUERY );
			$args  = array();

			parse_str( $query, $args );

			return array(
				'code' => isset( $args['wppa_notice'] ) ? (string) $args['wppa_notice'] : '',
				'url'  => $url,
			);
		}

		return array( 'code' => '', 'url' => '' );
	};

	// Куратору приглашения и переименование недоступны.
	$curator_rename = $wppa_run_action(
		52,
		array(
			'wppa_action'      => 'team',
			'wppa_team_action' => 'rename',
			'team_id'          => $action_team,
			'team_name'        => 'Новое название',
		)
	);
	$action_checks['куратор не переименовывает']  = 'team_owner_only' === $curator_rename['code'];
	$action_checks['имя команды не изменилось']   = 'Команда для действий' === WPPA_Teams::get( $action_team )['name'];

	$curator_invite = $wppa_run_action(
		52,
		array(
			'wppa_action'      => 'team',
			'wppa_team_action' => 'invite_email',
			'team_id'          => $action_team,
			'invite_email'     => 'new@example.com',
		)
	);
	$action_checks['куратор не приглашает']       = 'team_manager_only' === $curator_invite['code'];

	$curator_token = $wppa_run_action(
		52,
		array(
			'wppa_action'      => 'team',
			'wppa_team_action' => 'refresh_token',
			'team_id'          => $action_team,
		)
	);
	$action_checks['куратор не обновляет ссылку'] = 'team_manager_only' === $curator_token['code'];

	// Состав команды куратор по-прежнему правит.
	$curator_mentor = $wppa_run_action(
		52,
		array(
			'wppa_action'      => 'team',
			'wppa_team_action' => 'make_mentor',
			'team_id'          => $action_team,
			'member_id'        => 54,
		)
	);
	$action_checks['куратор не назначает наставника'] = 'team_curator_denied' === $curator_mentor['code'];

	$curator_remove = $wppa_run_action(
		52,
		array(
			'wppa_action'      => 'team',
			'wppa_team_action' => 'remove_member',
			'team_id'          => $action_team,
			'member_id'        => 54,
		)
	);
	$action_checks['куратор не исключает участника']  = 'team_curator_denied' === $curator_remove['code'];
	$action_checks['участник остался в команде']       = (int) WPPA_Teams::team_id( 54 ) === (int) $action_team;

	// Капитан и наставник приглашают и работают со ссылкой.
	$captain_rename = $wppa_run_action(
		51,
		array(
			'wppa_action'      => 'team',
			'wppa_team_action' => 'rename',
			'team_name'        => 'Новое название',
		)
	);
	$action_checks['капитан переименовал: уведомление'] = 'team_renamed' === $captain_rename['code'];
	$action_checks['капитан переименовал: имя']         = 'Новое название' === WPPA_Teams::get( $action_team )['name'];
	$action_checks['капитан: возврат без team_id']      = false === strpos( $captain_rename['url'], 'wppa_team=' );

	$captain_invite = $wppa_run_action(
		51,
		array(
			'wppa_action'      => 'team',
			'wppa_team_action' => 'invite_email',
			'invite_email'     => 'new@example.com',
		)
	);
	$action_checks['капитан приглашает по email'] = 'team_invite_sent' === $captain_invite['code'];

	$captain_token = $wppa_run_action(
		51,
		array(
			'wppa_action'      => 'team',
			'wppa_team_action' => 'refresh_token',
		)
	);
	$action_checks['капитан обновляет ссылку'] = 'team_token_refreshed' === $captain_token['code'];

	$mentor_invite = $wppa_run_action(
		53,
		array(
			'wppa_action'      => 'team',
			'wppa_team_action' => 'invite_email',
			'invite_email'     => 'new@example.com',
		)
	);
	$action_checks['наставник приглашает'] = 'team_invite_sent' === $mentor_invite['code'];

	$mentor_rename = $wppa_run_action(
		53,
		array(
			'wppa_action'      => 'team',
			'wppa_team_action' => 'rename',
			'team_name'        => 'Имя от наставника',
		)
	);
	$action_checks['наставник не переименовывает'] = 'team_owner_only' === $mentor_rename['code'];

	$member_invite = $wppa_run_action(
		54,
		array(
			'wppa_action'      => 'team',
			'wppa_team_action' => 'invite_email',
			'team_id'          => $action_team,
			'invite_email'     => 'new@example.com',
		)
	);
	$action_checks['участник не приглашает'] = 'team_manager_only' === $member_invite['code'];

	$GLOBALS['wppa_test']['catch_redirect'] = false;
	$_POST                                  = array();
	$_GET                                   = array();

	WPPA_Teams::dissolve( $action_team );
}

WPPA_Teams::set_curator( 52, false );
$GLOBALS['wppa_test']['meta'] = $action_snapshot;

foreach ( $action_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'curator actions: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// Файлы куратора для наставника команды (3.11.0).
$file_checks      = array();
$files_meta_backup = isset( $GLOBALS['wppa_test']['meta'] ) ? $GLOBALS['wppa_test']['meta'] : array();
$files_posts_backup = isset( $GLOBALS['wppa_test']['posts'] ) ? $GLOBALS['wppa_test']['posts'] : array();

$GLOBALS['wppa_test']['meta'] = $files_meta_backup;

$files_team = WPPA_Teams::create( 'Команда с файлами', 7 );
WPPA_Teams::add_member( $files_team, 8 );
WPPA_Teams::make_mentor( $files_team, 8 );
WPPA_Teams::add_member( $files_team, 9 );

WPPA_Teams::set_curator( 12, true );
WPPA_Teams::set_curator( 13, false );

// Наставник получает возможность прикреплять файлы только после второго этапа.
$mentor_wait_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'files-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array( 'user' => get_userdata( 8 ) )
);
$mentor_early_store = WPPA_Curator_Files::store( $files_team, wppa_test_file( 'рано.pdf', 'x' ), 'рано.pdf', 8 );

$file_checks['второй этап: наставнику рано грузить']  = false === strpos( $mentor_wait_html, 'name="curator_file"' );
$file_checks['второй этап: блока наставника нет']     = false === strpos( $mentor_wait_html, 'Файлы от наставника' );
$file_checks['второй этап: подсказки тоже нет']       = false === strpos( $mentor_wait_html, 'второй этап' );
$file_checks['второй этап: файлы куратора видны']     = false !== strpos( $mentor_wait_html, 'Файлы от куратора' );
$file_checks['второй этап: store отклоняет']          = is_wp_error( $mentor_early_store ) && 'wppa_file_stage2' === $mentor_early_store->get_error_code();
$file_checks['второй этап: код уведомления']          = 'file_stage2' === WPPA_Curator_Files::notice_code( 'wppa_file_stage2' );
$file_checks['второй этап: can_attach запрещает']     = false === WPPA_Curator_Files::can_attach( $files_team, 8 );
$file_checks['второй этап: waiting_stage2']           = true === WPPA_Curator_Files::waiting_stage2( $files_team, 8 );
$file_checks['второй этап: куратору можно и без']     = true === WPPA_Curator_Files::can_attach( $files_team, 12 );

WPPA_Teams::set_stage2( $files_team, true, 12 );

$file_checks['второй этап: can_attach разрешает']     = true === WPPA_Curator_Files::can_attach( $files_team, 8 );
$file_checks['второй этап: подсказки больше нет']     = false === WPPA_Curator_Files::waiting_stage2( $files_team, 8 );

// После отметки блок файлов наставника появляется вместе с формой.
$mentor_ready_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'files-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array( 'user' => get_userdata( 8 ) )
);
$file_checks['после отметки: блок наставника есть'] = false !== strpos( $mentor_ready_html, 'Файлы от наставника' );
$file_checks['после отметки: форма прикрепления']   = false !== strpos( $mentor_ready_html, 'name="curator_file"' );

/**
 * Временный файл для проверки загрузки.
 *
 * @param string $name Имя.
 * @param string $body Содержимое.
 * @return string Путь.
 */
function wppa_test_file( $name, $body = 'данные' ) {
	$dir  = sys_get_temp_dir() . '/wppa-files-test';
	$path = $dir . '/' . $name;

	wp_mkdir_p( $dir );
	file_put_contents( $path, $body );

	return $path;
}

$pdf = wppa_test_file( 'методичка.pdf', str_repeat( 'a', 240 ) );

$file_checks['команда создана'] = $files_team && ! is_wp_error( $files_team );

$stored = WPPA_Curator_Files::store( $files_team, $pdf, 'методичка.pdf', 12 );

$file_checks['куратор прикрепил файл']    = is_array( $stored ) && ! empty( $stored['key'] );
$file_checks['файл виден в списке']       = 1 === WPPA_Curator_Files::count( $files_team );
$file_checks['имя файла сохранено']       = 'методичка.pdf' === WPPA_Curator_Files::files( $files_team )[ $stored['key'] ]['name'];
$file_checks['размер посчитан']           = 240 === WPPA_Curator_Files::files( $files_team )[ $stored['key'] ]['size'];
$file_checks['файл лежит в папке']        = is_file( WPPA_Curator_Files::dir()['dir'] . '/' . WPPA_Curator_Files::files( $files_team )[ $stored['key'] ]['file'] );
$file_checks['папка закрыта от прямого доступа'] = is_file( WPPA_Curator_Files::dir()['dir'] . '/.htaccess' );

// Второй файл: ключи не совпадают.
$stored2 = WPPA_Curator_Files::store( $files_team, wppa_test_file( 'план.xlsx', str_repeat( 'b', 120 ) ), 'план.xlsx', 12 );

$file_checks['второй файл прикреплён'] = is_array( $stored2 );
$file_checks['ключи файлов разные']    = $stored['key'] !== $stored2['key'];
$file_checks['в списке два файла']     = 2 === WPPA_Curator_Files::count( $files_team );

// Кому можно.
$file_checks['наставник видит файлы']      = true === WPPA_Curator_Files::can_view( $files_team, 8 );
$file_checks['куратор видит файлы']         = true === WPPA_Curator_Files::can_view( $files_team, 12 );

$GLOBALS['wppa_test']['can_manage'] = false;
$file_checks['участник видит файлы куратора'] = true === WPPA_Curator_Files::can_view( $files_team, 9 );
$file_checks['есть файлы куратора в команде'] = true === WPPA_Curator_Files::has_curator_files( $files_team );
$file_checks['чужой команде участник не нужен'] = false === WPPA_Curator_Files::can_view( $files_team, 555 );
$file_checks['чужой не видит файлы']        = false === WPPA_Curator_Files::can_view( $files_team, 555 );
unset( $GLOBALS['wppa_test']['can_manage'] );

$file_checks['куратор управляет файлами']   = true === WPPA_Curator_Files::can_manage( $files_team, 12 );
$file_checks['наставник управляет своей']    = true === WPPA_Curator_Files::can_manage( $files_team, 8 );
$file_checks['наставник чужой не управляет'] = false === WPPA_Curator_Files::can_manage( $files_team, 555 );

// Отказы.
$not_curator = WPPA_Curator_Files::store( $files_team, wppa_test_file( 'чужой.pdf', 'данные' ), 'чужой.pdf', 9 );
$file_checks['не куратор — ошибка']         = is_wp_error( $not_curator ) && 'wppa_file_forbidden' === $not_curator->get_error_code();

$bad_type = WPPA_Curator_Files::store( $files_team, wppa_test_file( 'скрипт.php', '<?php echo 1;' ), 'скрипт.php', 12 );
$file_checks['запрещённый тип отклонён']    = is_wp_error( $bad_type ) && 'wppa_file_type' === $bad_type->get_error_code();

$empty = WPPA_Curator_Files::store( $files_team, wppa_test_file( 'пусто.pdf' , '' ), 'пусто.pdf', 12 );
$file_checks['пустой файл отклонён']        = is_wp_error( $empty ) && 'wppa_file_empty' === $empty->get_error_code();

$missing = WPPA_Curator_Files::store( $files_team, sys_get_temp_dir() . '/нет-такого.pdf', 'нет.pdf', 12 );
$file_checks['несуществующий файл отклонён'] = is_wp_error( $missing ) && 'wppa_file_missing' === $missing->get_error_code();

function wppa_test_tiny_size() {
	return 1;
}

add_filter( 'wppa_curator_file_max_size', 'wppa_test_tiny_size' );
$big = WPPA_Curator_Files::store( $files_team, wppa_test_file( 'большой.pdf', str_repeat( 'c', 500 ) ), 'большой.pdf', 12 );
remove_filter( 'wppa_curator_file_max_size', 'wppa_test_tiny_size' );
$file_checks['слишком большой файл отклонён'] = is_wp_error( $big ) && 'wppa_file_size' === $big->get_error_code();

// Коды уведомлений.
$file_checks['уведомление: тип']     = 'file_type' === WPPA_Curator_Files::notice_code( 'wppa_file_type' );
$file_checks['уведомление: размер'] = 'file_size' === WPPA_Curator_Files::notice_code( 'wppa_file_size' );
$file_checks['уведомление: прочее'] = 'file_missing' === WPPA_Curator_Files::notice_code( 'wppa_file_upload' );

// Ссылка на скачивание.
$download = WPPA_Curator_Files::download_url( $files_team, $stored['key'] );

$file_checks['ссылка: точка входа']  = false !== strpos( $download, 'action=wppa_curator_file' );
$file_checks['ссылка: номер команды'] = false !== strpos( $download, 'team=' . (int) $files_team );
$file_checks['ссылка: одноразовый ключ'] = false !== strpos( $download, '_wpnonce' );

// Удаление.
$file_checks['куратор удалил файл']    = true === WPPA_Curator_Files::remove( $files_team, $stored2['key'], 12 );
$file_checks['после удаления один файл'] = 1 === WPPA_Curator_Files::count( $files_team );
$file_checks['файл удалён с диска']    = ! is_file( WPPA_Curator_Files::dir()['dir'] . '/' . $stored2['key'] . '-план.xlsx' );
$file_checks['наставник не удаляет']   = false === WPPA_Curator_Files::remove( $files_team, $stored['key'], 8 );

// Кабинет: карточка куратора и карточка наставника.
$_GET['wppa_team'] = (int) $files_team;
$GLOBALS['wppa_test']['current_user'] = 12;

$curator_files_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'team-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array()
);

$file_checks['кабинет: карточка «Файлы для наставника»'] = false !== strpos( $curator_files_html, 'Файлы для наставника' );
$file_checks['кабинет: форма загрузки']       = false !== strpos( $curator_files_html, 'enctype="multipart/form-data"' );
$file_checks['кабинет: поле файла']           = false !== strpos( $curator_files_html, 'name="curator_file"' );
$file_checks['кабинет: список файлов']        = false !== strpos( $curator_files_html, 'методичка.pdf' );
$file_checks['кабинет: кнопка удаления']      = false !== strpos( $curator_files_html, 'curator_file_action" value="remove"' );

unset( $_GET['wppa_team'] );

// Наставник своей команды видит файлы в отдельном разделе «Файлы», но не может их прикреплять.
$mentor_files_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'files-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array( 'user' => get_userdata( 8 ) )
);

// Файл, присланный наставником: направление сохраняется, куратор его видит.
$mentor_file = WPPA_Curator_Files::store( $files_team, wppa_test_file( 'ответ.pdf', str_repeat( 'm', 64 ) ), 'ответ.pdf', 8 );

$file_checks['наставник прикрепил файл']        = is_array( $mentor_file ) && ! empty( $mentor_file['key'] );
$file_checks['направление: от наставника']      = 'mentor' === WPPA_Curator_Files::files( $files_team )[ $mentor_file['key'] ]['from'];
$file_checks['направление: от куратора']        = 'curator' === WPPA_Curator_Files::files( $files_team )[ $stored['key'] ]['from'];
$file_checks['наставник удаляет свой файл']     = true === WPPA_Curator_Files::can_remove( $files_team, $mentor_file['key'], 8 );
$file_checks['наставник не удаляет чужой']      = false === WPPA_Curator_Files::can_remove( $files_team, $stored['key'], 8 );
$file_checks['куратор удаляет любой']           = true === WPPA_Curator_Files::can_remove( $files_team, $mentor_file['key'], 12 );

// Участнику раздел недоступен и загрузки у него нет.
$member_files_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'files-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array( 'user' => get_userdata( 9 ) )
);

$file_checks['раздел: участнику не грузить']          = false === strpos( $member_files_html, 'name="curator_file"' );
$file_checks['раздел: участнику файл куратора виден'] = false !== strpos( $member_files_html, 'методичка.pdf' );
$file_checks['раздел: участнику карточка куратора']   = false !== strpos( $member_files_html, 'Файлы от куратора' );
$file_checks['раздел: полки наставника нет']          = false === strpos( $member_files_html, 'Файлы от наставника' );
$file_checks['раздел: файла наставника нет']          = false === strpos( $member_files_html, 'ответ.pdf' );

// Команда, куда куратор ничего не присылал: у участника нет ни вкладки, ни раздела.
$no_cur_team = WPPA_Teams::create( 'Команда без файлов куратора', 93 );
WPPA_Teams::add_member( $no_cur_team, 94 );
WPPA_Teams::make_mentor( $no_cur_team, 94 );
WPPA_Teams::add_member( $no_cur_team, 95 );
WPPA_Teams::set_stage2( $no_cur_team, true, 12 );

$no_cur_file = WPPA_Curator_Files::store( $no_cur_team, wppa_test_file( 'только-наставник.pdf', 'm' ), 'только-наставник.pdf', 94 );

$GLOBALS['wppa_test']['current_user'] = 95;
$GLOBALS['wppa_test']['can_manage']   = false;

$file_checks['без файлов куратора: файл наставника есть'] = is_array( $no_cur_file ) && ! empty( $no_cur_file['key'] );
$file_checks['без файлов куратора: has_curator_files ложь'] = false === WPPA_Curator_Files::has_curator_files( $no_cur_team );
$file_checks['без файлов куратора: can_view ложь']          = false === WPPA_Curator_Files::can_view( $no_cur_team, 95 );
$file_checks['без файлов куратора: can_use_tab ложь']       = false === WPPA_Curator_Files::can_use_tab( 95 );
$file_checks['без файлов куратора: вкладки нет']            = ! array_key_exists( 'files', WPPA_Template::tabs() );

unset( $GLOBALS['wppa_test']['can_manage'] );

WPPA_Teams::dissolve( $no_cur_team );

$GLOBALS['wppa_test']['current_user'] = 9;

// Куратор прикрепляет файлы любой команде — отметка второго этапа не нужна.
$no_stage_team = WPPA_Teams::create( 'Команда без отметки', 96 );
WPPA_Teams::add_member( $no_stage_team, 97 );
WPPA_Teams::make_mentor( $no_stage_team, 97 );

$_GET['wppa_team']                    = (int) $no_stage_team;
$GLOBALS['wppa_test']['current_user'] = 12;

$curator_no_stage_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'files-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array( 'user' => get_userdata( 12 ) )
);

// Наставнику вкладка нужна, только если есть файлы куратора или отметка второго этапа.
$file_checks['наставник без отметки: вкладки нет']  = false === WPPA_Curator_Files::can_use_tab( 97 );
$file_checks['куратор всегда: вкладка есть']        = true === WPPA_Curator_Files::can_use_tab( 12 );

$file_checks['куратор без отметки: can_attach']     = true === WPPA_Curator_Files::can_attach( $no_stage_team, 12 );
$file_checks['куратор без отметки: форма есть']      = false !== strpos( $curator_no_stage_html, 'name="curator_file"' );
$file_checks['куратор без отметки: team_id в форме'] = false !== strpos( $curator_no_stage_html, 'name="team_id" value="' . (int) $no_stage_team . '"' );
$file_checks['куратор без отметки: полки наставника нет'] = false === strpos( $curator_no_stage_html, 'Файлы от наставника' );
$file_checks['куратор без отметки: файл принят']     = is_array( WPPA_Curator_Files::store( $no_stage_team, wppa_test_file( 'куратор-файл.pdf', 'c' ), 'куратор-файл.pdf', 12 ) );
$file_checks['куратор без отметки: файл в команде']  = true === WPPA_Curator_Files::has_curator_files( $no_stage_team );

// Появились файлы куратора — вкладка у наставника открывается, хотя отметки нет.
$file_checks['наставник: вкладка после файлов куратора'] = true === WPPA_Curator_Files::can_use_tab( 97 );

WPPA_Teams::set_stage2( $no_stage_team, true, 12 );
$file_checks['наставник: вкладка после отметки']         = true === WPPA_Curator_Files::can_use_tab( 97 );

// Отметку возвращаем: дальше проверяем наставника без второго этапа.
WPPA_Teams::set_stage2( $no_stage_team, false, 12 );

// Наставнику без отметки по-прежнему нельзя.
$GLOBALS['wppa_test']['current_user'] = 97;

$mentor_no_stage_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'files-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array( 'user' => get_userdata( 97 ) )
);

$file_checks['наставник без отметки: can_attach ложь'] = false === WPPA_Curator_Files::can_attach( $no_stage_team, 97 );
$file_checks['наставник без отметки: формы нет']       = false === strpos( $mentor_no_stage_html, 'name="curator_file"' );

// Куратор после загрузки остаётся в разделе «Файлы», а не в карточке команды.
$file_back_redirect = function ( $stay ) use ( $no_stage_team ) {
	$GLOBALS['wppa_test']['catch_redirect'] = true;
	$GLOBALS['wppa_test']['current_user']   = 12;

	$_POST = array(
		'wppa_action'         => 'curator_file',
		'curator_file_action' => 'upload',
		'team_id'             => (int) $no_stage_team,
		'wppa_nonce'          => wp_create_nonce( 'wppa_curator_file' ),
	);

	if ( '' !== $stay ) {
		$_POST['wppa_back'] = $stay;
	}

	$_FILES = array();

	try {
		WPPA_Curator_Files::handle_request();
	} catch ( RuntimeException $e ) {
		$GLOBALS['wppa_test']['catch_redirect'] = false;

		return substr( $e->getMessage(), strlen( 'redirect:' ) );
	}

	$GLOBALS['wppa_test']['catch_redirect'] = false;

	return '';
};

$files_back_url = $file_back_redirect( 'files' );
$team_back_url  = $file_back_redirect( '' );

$_POST  = array();
$_FILES = array();

$file_checks['куратор: после файла остаётся в «Файлах»'] = false !== strpos( $files_back_url, 'wppa_tab=files' ) && false !== strpos( $files_back_url, 'wppa_team=' . (int) $no_stage_team );
$file_checks['куратор: карточка команды не открывается'] = false === strpos( $files_back_url, 'wppa_tab=team' );
$file_checks['форма в карточке: возврат на команду']     = false !== strpos( $team_back_url, 'wppa_tab=team' );
$file_checks['наставник без отметки: файл куратора виден'] = false !== strpos( $mentor_no_stage_html, 'куратор-файл.pdf' );

unset( $_GET['wppa_team'] );

WPPA_Teams::dissolve( $no_stage_team );

$GLOBALS['wppa_test']['current_user'] = 9;
$file_checks['раздел: карточка «Файлы от куратора»'] = false !== strpos( $mentor_files_html, 'Файлы от куратора' );
$file_checks['раздел: наставник видит файл']         = false !== strpos( $mentor_files_html, 'методичка.pdf' );
$file_checks['раздел: наставник грузит файлы']       = false !== strpos( $mentor_files_html, 'name="curator_file"' );
$file_checks['раздел: подпись для куратора']         = false !== strpos( $mentor_files_html, 'Прикрепить файл для куратора' );
$file_checks['раздел: форма multipart']              = false !== strpos( $mentor_files_html, 'enctype="multipart/form-data"' );

$file_checks['раздел: ссылка на скачивание']         = false !== strpos( $mentor_files_html, 'action=wppa_curator_file' );
$file_checks['раздел: название команды']             = false !== strpos( $mentor_files_html, 'Документы команды' );

// Вкладка «Файлы» есть у наставника и куратора, но не у участника и гостя.
$tabs_settings_backup = get_option( 'wppa_settings', array() );

update_option(
	'wppa_settings',
	array_merge( (array) $tabs_settings_backup, array( 'enabled_tabs' => array( 'dashboard', 'profile', 'team', 'files', 'security' ) ) )
);

$GLOBALS['wppa_test']['current_user'] = 8;
$file_checks['вкладка: у наставника есть'] = array_key_exists( 'files', WPPA_Template::tabs() );

// Куратор: вкладки «Обзор» и «Профиль» убраны, список команд и файлы остались.
$GLOBALS['wppa_test']['current_user'] = 12;
$curator_tabs = WPPA_Template::tabs();

$file_checks['вкладки куратора: без «Обзора»']    = ! array_key_exists( 'dashboard', $curator_tabs );
$file_checks['вкладки куратора: без «Профиля»']   = ! array_key_exists( 'profile', $curator_tabs );
$file_checks['вкладки куратора: команда на месте'] = array_key_exists( 'team', $curator_tabs );
$file_checks['вкладки куратора: файлы на месте']   = array_key_exists( 'files', $curator_tabs );
$file_checks['вкладки куратора: порядок']          = array( 'team', 'files', 'security' ) === array_keys( array_intersect_key( $curator_tabs, array_flip( array( 'team', 'files', 'security' ) ) ) );

$GLOBALS['wppa_test']['current_user'] = 7;

$file_checks['вкладки участника: «Обзор» есть']   = array_key_exists( 'dashboard', WPPA_Template::tabs() );
$file_checks['вкладки участника: «Профиль» есть'] = array_key_exists( 'profile', WPPA_Template::tabs() );

// Куратор в списке команд: вместо «Управлять» — «Добавить файлы».
$curator_team_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'team-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array( 'user' => get_userdata( 12 ) )
);

$file_checks['куратор: кнопка «Добавить файлы»'] = false !== strpos( $curator_team_html, 'Добавить файлы' );
$file_checks['куратор: кнопки «Управлять» нет']  = false === strpos( $curator_team_html, 'Управлять' );
$file_checks['куратор: ссылка на вкладку файлов'] = false !== strpos( $curator_team_html, 'wppa_tab=files' );
// Вместо капитана куратор видит данные наставника: имя, фамилию и email.
$mentor_row = array();

foreach ( WPPA_Teams::all_teams() as $wppa_team_row ) {
	if ( (int) $wppa_team_row['id'] === (int) $files_team ) {
		$mentor_row = $wppa_team_row;
	}
}

$mentor_user = get_userdata( 8 );

$file_checks['команда: наставник в данных']     = isset( $mentor_row['mentor'] ) && '' !== (string) $mentor_row['mentor'];
$file_checks['команда: email наставника']       = isset( $mentor_row['mentor_email'] ) && false !== strpos( (string) $mentor_row['mentor_email'], '@' );
$file_checks['команда: имя и фамилия']          = false !== strpos( (string) $mentor_row['mentor'], (string) $mentor_user->first_name );
$file_checks['куратор: колонка «Наставник»']    = false !== strpos( $curator_team_html, 'Наставник' );
$file_checks['куратор: колонки «Капитан» нет']  = false === strpos( $curator_team_html, 'Капитан' );
$file_checks['куратор: email в таблице']        = false !== strpos( $curator_team_html, (string) $mentor_row['mentor_email'] );
$file_checks['куратор: без счётчика участников'] = false === strpos( $curator_team_html, 'Участников' );
$file_checks['куратор: без счётчика наставников'] = false === strpos( $curator_team_html, 'Наставников' );
$file_checks['куратор: команда и состояние есть'] = false !== strpos( $curator_team_html, 'Состояние' ) && false !== strpos( $curator_team_html, 'Второй этап' );
$GLOBALS['wppa_test']['users'][90] = array( 'Только отображаемое имя', 'noname@example.com', 'first_name' => '', 'last_name' => '' );
$file_checks['имя: без имени и фамилии — display_name'] = 'Только отображаемое имя' === WPPA_Teams::full_name( new WP_User( 90 ) );
unset( $GLOBALS['wppa_test']['users'][90] );

$GLOBALS['wppa_test']['current_user'] = 12;
$file_checks['вкладка: у куратора есть']   = array_key_exists( 'files', WPPA_Template::tabs() );

$GLOBALS['wppa_test']['current_user'] = 9;
$file_checks['вкладка: у участника есть (куратор прислал)'] = array_key_exists( 'files', WPPA_Template::tabs() );

$GLOBALS['wppa_test']['current_user'] = 0;
$file_checks['вкладка: у гостя нет']       = ! array_key_exists( 'files', WPPA_Template::tabs() );

// Прямая ссылка тоже не открывает раздел участнику.
$_GET['wppa_tab'] = 'files';

$GLOBALS['wppa_test']['current_user'] = 9;
$file_checks['вкладка: участнику по ссылке можно'] = 'files' === WPPA_Account::current_tab();

$GLOBALS['wppa_test']['current_user'] = 8;
$file_checks['вкладка: наставнику по ссылке можно'] = 'files' === WPPA_Account::current_tab();

unset( $_GET['wppa_tab'] );

update_option( 'wppa_settings', $tabs_settings_backup );

$file_checks['вкладка: по умолчанию включена'] = in_array( 'files', (array) WPPA_Plugin::defaults()['enabled_tabs'], true );
$GLOBALS['wppa_test']['current_user'] = 0;
$file_checks['вкладка: гостю раздел закрыт'] = false === WPPA_Curator_Files::can_use_tab();

$file_checks['вкладка: права на раздел'] = true === WPPA_Curator_Files::can_use_tab( 8 )
	&& true === WPPA_Curator_Files::can_use_tab( 12 )
	&& true === WPPA_Curator_Files::can_use_tab( 9 )
	&& false === WPPA_Curator_Files::can_use_tab( 555 );

$file_checks['вкладка: шаблон есть']       = is_file( WPPA_PATH . 'templates/tab-files.php' );
$file_checks['вкладка: блок в конструкторе'] = array_key_exists( 'files-widget', WPPA_Builder::blocks() );
$file_checks['вкладка: блок в раскладке']   = isset( WPPA_Builder::layout()['files'][0]['id'] ) && 'files-widget' === WPPA_Builder::layout()['files'][0]['id'];

// В карточке команды у наставника осталась только ссылка на раздел.
$mentor_team_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'team-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array( 'user' => get_userdata( 8 ) )
);
$file_checks['команда: файлов больше нет'] = false === strpos( $mentor_team_html, 'action=wppa_curator_file' );
$file_checks['команда: ссылка на раздел']  = false !== strpos( $mentor_team_html, 'wppa_tab=files' );

// Участник и капитан видят файлы куратора прямо в карточке команды.
$member_team_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'team-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array( 'user' => get_userdata( 9 ) )
);
$owner_team_html = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'team-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array( 'user' => get_userdata( 7 ) )
);

$file_checks['участник: полка куратора в команде']  = false !== strpos( $member_team_html, 'Файлы от куратора' );
$file_checks['участник: файл куратора в команде']    = false !== strpos( $member_team_html, 'методичка.pdf' );
$file_checks['участник: файла наставника нет']      = false === strpos( $member_team_html, 'ответ.pdf' );
$file_checks['участник: без формы загрузки']        = false === strpos( $member_team_html, 'name="curator_file"' );
$file_checks['капитан: файл куратора в команде']    = false !== strpos( $owner_team_html, 'методичка.pdf' );
$file_checks['наставник: полки куратора в команде нет'] = false === strpos( $mentor_team_html, 'Файлы от куратора' );
$GLOBALS['wppa_test']['current_user'] = 12;

// Куратор: без выбранной команды — список команд, с выбранной — файлы и ссылка на управление.
$curator_files_tab = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'files-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array( 'user' => get_userdata( 12 ) )
);
$file_checks['раздел: куратор видит список команд'] = false !== strpos( $curator_files_tab, 'Открыть' );

$_GET['wppa_team'] = (int) $files_team;

$curator_files_team = WPPA_Builder::render_block(
	array( 'type' => 'block', 'id' => 'files-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
	array( 'user' => get_userdata( 12 ) )
);
$file_checks['раздел: куратор видит файлы команды'] = false !== strpos( $curator_files_team, 'методичка.pdf' );
$file_checks['раздел: куратор грузит файлы']         = false !== strpos( $curator_files_team, 'Прикрепить файл для наставника' );
$file_checks['раздел: куратор видит файл наставника'] = false !== strpos( $curator_files_team, 'Файлы от наставника' );

// Обработчик формы: кто что может удалять.
$GLOBALS['wppa_test']['catch_redirect'] = true;

$wppa_run_file_action = function ( $user_id, $post ) {
	$GLOBALS['wppa_test']['current_user'] = $user_id;
	$_POST                                = $post;
	$_GET                                 = array();

	try {
		WPPA_Curator_Files::handle_request();
	} catch ( RuntimeException $e ) {
		$url   = substr( $e->getMessage(), strlen( 'redirect:' ) );
		$query = (string) parse_url( $url, PHP_URL_QUERY );
		$args  = array();

		parse_str( $query, $args );

		return array(
			'code' => isset( $args['wppa_notice'] ) ? (string) $args['wppa_notice'] : '',
			'url'  => $url,
		);
	}

	return array( 'code' => '', 'url' => '' );
};

$file_action_member = $wppa_run_file_action(
	9,
	array( 'wppa_action' => 'curator_file', 'curator_file_action' => 'remove', 'team_id' => $files_team, 'file_key' => $mentor_file['key'] )
);
$file_checks['обработчик: участнику нельзя']      = 'file_forbidden' === $file_action_member['code'];

$file_action_foreign = $wppa_run_file_action(
	8,
	array( 'wppa_action' => 'curator_file', 'curator_file_action' => 'remove', 'team_id' => $files_team, 'file_key' => $stored['key'] )
);
$file_checks['обработчик: чужой файл не удалить']  = 'file_forbidden' === $file_action_foreign['code'];
$file_checks['обработчик: файл на месте']          = isset( WPPA_Curator_Files::files( $files_team )[ $stored['key'] ] );

$file_action_own = $wppa_run_file_action(
	8,
	array( 'wppa_action' => 'curator_file', 'curator_file_action' => 'remove', 'team_id' => $files_team, 'file_key' => $mentor_file['key'] )
);
$file_checks['обработчик: наставник удалил свой']  = 'file_removed' === $file_action_own['code'];
$file_checks['обработчик: возврат на «Файлы»']     = false !== strpos( $file_action_own['url'], 'wppa_tab=files' );
$file_checks['обработчик: файл исчез']             = ! isset( WPPA_Curator_Files::files( $files_team )[ $mentor_file['key'] ] );

$GLOBALS['wppa_test']['catch_redirect'] = false;
$_POST                                  = array();
$_GET                                   = array();

unset( $GLOBALS['wppa_test']['current_user'] );

// Автоматическая выгрузка файлов наставника в сетевую папку (папка на команду).
$export_checks  = array();
$export_dir     = sys_get_temp_dir() . '/wppa-export-test';
$export_backup  = get_option( 'wppa_settings', array() );
$export_team    = WPPA_Teams::create( 'Студия «Вектор»', 61 );

// Чистим папку от прошлых прогонов.
foreach ( (array) glob( $export_dir . '/*' ) as $export_stale ) {
	if ( is_file( $export_stale ) ) {
		@unlink( $export_stale );
	}
}

@mkdir( $export_dir, 0777, true );

update_option( 'wppa_settings', array_merge( (array) $export_backup, array( 'smb' => array( 'path' => $export_dir, 'user' => '', 'pass' => '' ) ) ) );

WPPA_Teams::add_member( $export_team, 62 );
WPPA_Teams::make_mentor( $export_team, 62 );
WPPA_Teams::set_curator( 63, true );

$export_checks['команда создана']   = $export_team && ! is_wp_error( $export_team );
$export_checks['выгрузка включена'] = true === WPPA_Team_Files_Export::enabled();
$export_checks['папка: только название команды'] = 'Студия «Вектор»' === WPPA_Team_Files_Export::folder_name( $export_team );
$export_checks['папка: без номера команды']      = false === strpos( WPPA_Team_Files_Export::folder_name( $export_team ), (string) (int) $export_team );
$export_checks['папка: общая папка «Команды»']    = 'Команды' === WPPA_Team_Files_Export::folder_root();
$export_checks['папка: путь в общей папке']       = 'Команды/Студия «Вектор»' === WPPA_Team_Files_Export::folder_path( $export_team );
$export_checks['папка: на диске в общей папке']   = $export_dir . DIRECTORY_SEPARATOR . 'Команды' . DIRECTORY_SEPARATOR . 'Студия «Вектор»' === WPPA_Team_Files_Export::local_dir( $export_team );
$export_checks['папка: служебные символы'] = false === strpos( WPPA_Team_Files_Export::folder_name( $export_team ), '/' );

// Одинаковые названия не сливаются: номер команды всегда в имени папки.
$export_dup_one = WPPA_Teams::create( 'Дубль', 68 );
$export_dup_two = WPPA_Teams::create( 'Дубль', 69 );

$export_checks['одноимённые: разные папки'] = WPPA_Team_Files_Export::folder_name( $export_dup_one ) !== WPPA_Team_Files_Export::folder_name( $export_dup_two );
$export_checks['одноимённые: первая без суффикса'] = 'Дубль' === WPPA_Team_Files_Export::folder_name( $export_dup_one );
$export_checks['одноимённые: вторая с « (2)»']     = 'Дубль (2)' === WPPA_Team_Files_Export::folder_name( $export_dup_two );

WPPA_Teams::dissolve( $export_dup_one );
WPPA_Teams::dissolve( $export_dup_two );
$export_checks['папка: путь на диске']     = '' !== WPPA_Team_Files_Export::local_dir( $export_team );

// Файл наставника уходит в папку команды.
WPPA_Teams::set_stage2( $export_team, true, 63 );

$export_file = WPPA_Curator_Files::store( $export_team, wppa_test_file( 'протокол.pdf', str_repeat( 'e', 80 ) ), 'протокол.pdf', 62 );

$export_checks['наставник прикрепил']   = is_array( $export_file ) && ! empty( $export_file['key'] );
$export_checks['выгрузка прошла']       = ! empty( $export_file['export']['ok'] );
$export_checks['файл в папке команды']  = is_file( WPPA_Team_Files_Export::local_dir( $export_team ) . '/протокол.pdf' );
$export_checks['папка создана']         = is_dir( WPPA_Team_Files_Export::local_dir( $export_team ) );
$export_checks['содержимое совпадает']  = str_repeat( 'e', 80 ) === file_get_contents( WPPA_Team_Files_Export::local_dir( $export_team ) . '/протокол.pdf' );
$export_checks['запись в мете']         = isset( WPPA_Team_Files_Export::record( $export_team )[ $export_file['key'] ] );

// Файл куратора не выгружается.
$export_curator = WPPA_Curator_Files::store( $export_team, wppa_test_file( 'письмо.pdf', 'данные' ), 'письмо.pdf', 63 );

$export_checks['куратор не выгружается'] = is_array( $export_curator ) && empty( $export_curator['export']['ok'] );
$export_checks['файла куратора нет в папке'] = ! is_file( WPPA_Team_Files_Export::local_dir( $export_team ) . '/письмо.pdf' );

// Одноимённый файл не перезаписывается.
$export_second = WPPA_Curator_Files::store( $export_team, wppa_test_file( 'протокол-2.pdf', str_repeat( 'f', 40 ) ), 'протокол.pdf', 62 );
$export_checks['одноимённый не перезаписал'] = ! empty( $export_second['export']['ok'] ) && is_file( WPPA_Team_Files_Export::local_dir( $export_team ) . '/протокол.pdf' );
$export_checks['второй файл под своим именем'] = is_file( WPPA_Team_Files_Export::local_dir( $export_team ) . '/' . ( WPPA_Team_Files_Export::record( $export_team )[ $export_second['key'] ] ) );

// Удаление файла убирает копию.
$export_copy = WPPA_Team_Files_Export::local_dir( $export_team ) . '/' . WPPA_Team_Files_Export::record( $export_team )[ $export_file['key'] ];

WPPA_Curator_Files::remove( $export_team, $export_file['key'], 63 );

$export_checks['копия удалена']       = ! is_file( $export_copy );
$export_checks['мета обновилась']     = ! isset( WPPA_Team_Files_Export::record( $export_team )[ $export_file['key'] ] );

// Роспуск команды убирает папку.
$export_team_dir = WPPA_Team_Files_Export::local_dir( $export_team );

if ( '' === $export_team_dir ) {
	$export_team_dir = $export_dir . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, WPPA_Team_Files_Export::folder_path( $export_team ) );
}

WPPA_Teams::dissolve( $export_team );

$export_checks['после роспуска папки нет'] = ! is_dir( $export_team_dir );

// Без настроенного пути выгрузки нет, но загрузка работает.
update_option( 'wppa_settings', array_merge( (array) $export_backup, array( 'smb' => array( 'path' => '', 'user' => '', 'pass' => '' ) ) ) );

$export_off_team = WPPA_Teams::create( 'Команда без папки', 64 );
WPPA_Teams::add_member( $export_off_team, 65 );
WPPA_Teams::make_mentor( $export_off_team, 65 );

WPPA_Teams::set_stage2( $export_off_team, true, 63 );

$export_off = WPPA_Curator_Files::store( $export_off_team, wppa_test_file( 'файл.pdf', 'данные' ), 'файл.pdf', 65 );

$export_checks['без пути выгрузка выключена'] = false === WPPA_Team_Files_Export::enabled();
$export_checks['файл всё равно прикреплён']   = is_array( $export_off ) && empty( $export_off['export']['ok'] );

WPPA_Teams::dissolve( $export_off_team );

// SMB-режим: путь собирается с папкой команды.
$export_smb = WPPA_SMB::write( 'протокол.pdf', 'данные', array( 'path' => '\\\\fileserver\\exchange\\reports', 'user' => 'u', 'pass' => 'p' ), 'Студия (12)' );
$export_checks['smb: папка команды в пути'] = is_array( $export_smb ) && false !== strpos( (string) $export_smb['file'], 'reports\Студия (12)\протокол.pdf' );

// smbclient: вложенные папки создаём по одной, файл не падает в корень шары.
$smb_dirs    = array();
$smb_files   = array();
$smb_deny    = false;
$smb_scripts = array();

$smb_fake = function ( $result, $command ) use ( &$smb_dirs, &$smb_files, &$smb_deny, &$smb_scripts ) {
	if ( false !== strpos( $command, 'command -v smbclient' ) ) {
		return array( 'code' => 0, 'output' => '/usr/bin/smbclient' );
	}

	if ( ! preg_match( "/-c '([^']*)'/", $command, $found ) ) {
		return array( 'code' => 0, 'output' => '' );
	}

	$script        = $found[1];
	$smb_scripts[] = $script;

	$current = '';
	$out     = array();
	$code    = 0;

	foreach ( array_filter( array_map( 'trim', explode( ';', $script ) ), 'strlen' ) as $step ) {
		if ( ! preg_match( '/^(mkdir|cd|put)\s+"([^"]*)"(?:\s+"([^"]*)")?$/', $step, $parts ) ) {
			continue;
		}

		$action = $parts[1];
		$arg    = $parts[2];

		// Как настоящий smbclient: путь считается от текущего каталога,
		// с ведущего «\» — от корня шары.
		$full   = 0 === strpos( $arg, '\\' ) ? ltrim( $arg, '\\' ) : ( '' !== $current ? $current . '\\' : '' ) . $arg;
		$exists = in_array( $full, $smb_dirs, true );

		if ( 'put' === $action ) {
			$smb_files[ ( '' !== $current ? $current . '\\' : '' ) . $parts[3] ] = true;
			$out[] = 'putting file ' . $arg . ' as \\' . $parts[3];
			continue;
		}

		if ( 'mkdir' === $action ) {
			$slash  = strrpos( $full, '\\' );
			$parent = false !== $slash ? substr( $full, 0, $slash ) : '';

			if ( $exists ) {
				$out[] = 'NT_STATUS_OBJECT_NAME_COLLISION making remote directory \\' . $full;
			} elseif ( $smb_deny || ( '' !== $parent && ! in_array( $parent, $smb_dirs, true ) ) ) {
				$out[] = 'NT_STATUS_OBJECT_PATH_NOT_FOUND making remote directory \\' . $full;
				$code  = 1;
			} else {
				$smb_dirs[] = $full;
			}

			continue;
		}

		if ( $exists ) {
			$current = $full;
		} else {
			$out[] = 'NT_STATUS_OBJECT_PATH_NOT_FOUND cd \\' . $full . '\\';
			$code  = 1;
		}
	}

	return array( 'code' => $code, 'output' => implode( "\n", $out ) );
};

add_filter( 'wppa_smb_runner', $smb_fake, 10, 2 );

$smb_conf  = array( 'path' => '\\\\fileserver\\kvant', 'user' => 'u', 'pass' => 'p' );
$smb_write = WPPA_SMB::write( 'протокол.pdf', 'данные', $smb_conf, 'olimp/Команды/1 (337)' );

$smb_checks = array();
$smb_checks['smbclient: запись прошла']          = is_array( $smb_write ) && true === $smb_write['ok'];
$smb_checks['smbclient: папки создаются по одной'] = in_array( 'olimp', $smb_dirs, true ) && in_array( 'olimp\\Команды', $smb_dirs, true ) && in_array( 'olimp\\Команды\\1 (337)', $smb_dirs, true );
$smb_checks['smbclient: файл в папке команды']    = isset( $smb_files['olimp\\Команды\\1 (337)\\протокол.pdf'] );
$smb_checks['smbclient: в корень шары не положил'] = ! isset( $smb_files['протокол.pdf'] );
$smb_ensure = isset( $smb_scripts[0] ) ? (string) $smb_scripts[0] : '';
$smb_checks['smbclient: mkdir по шагам']            = 3 === substr_count( $smb_ensure, 'mkdir ' );
$smb_checks['smbclient: файл кладём отдельным заходом'] = false === strpos( $smb_ensure, 'put ' );
$smb_checks['smbclient: путь не удваивается'] = ! in_array( 'olimp\\olimp', $smb_dirs, true );

// Папку создать нельзя: файл не кладём совсем, показываем причину.
$smb_dirs  = array();
$smb_files = array();
$smb_deny  = true;
$smb_write_denied = WPPA_SMB::write( 'протокол.pdf', 'данные', $smb_conf, 'olimp/Команды/1 (337)' );

$smb_checks['smbclient: без папки отказ']       = is_array( $smb_write_denied ) && false === $smb_write_denied['ok'];
$smb_checks['smbclient: в корень не положил (отказ)'] = empty( $smb_files );
$smb_checks['smbclient: причина про права']     = false !== strpos( (string) $smb_write_denied['message'], 'нет прав' );

$smb_checks['smbclient: коллизия не ошибка'] = false === WPPA_SMB::smbclient_fatal( 'NT_STATUS_OBJECT_NAME_COLLISION making remote directory' );
$smb_checks['smbclient: доступ закрыт — ошибка'] = true === WPPA_SMB::smbclient_fatal( 'NT_STATUS_ACCESS_DENIED listing \\*' );

remove_filter( 'wppa_smb_runner', $smb_fake, 10 );

foreach ( $smb_checks as $smb_name => $smb_ok ) {
	echo ( $smb_ok ? 'OK    ' : 'FAIL  ' ) . 'smbclient: ' . $smb_name . "\n";

	if ( ! $smb_ok ) {
		$fail++;
	}
}

update_option( 'wppa_settings', $export_backup );

// Итоговая уборка тестовой папки.
foreach ( (array) glob( $export_dir . '/*' ) as $export_stale ) {
	if ( is_file( $export_stale ) ) {
		@unlink( $export_stale );
	} elseif ( is_dir( $export_stale ) ) {
		foreach ( (array) glob( $export_stale . '/*' ) as $export_inner ) {
			@unlink( $export_inner );
		}

		@rmdir( $export_stale );
	}
}

foreach ( $export_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'team files export: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// Кнопка «Экспортировать»: помимо CSV выгружаются файлы наставников всех команд.
$bulk_checks = array();
$bulk_dir    = sys_get_temp_dir() . '/wppa-bulk-test';

@mkdir( $bulk_dir, 0777, true );

foreach ( (array) glob( $bulk_dir . '/*' ) as $bulk_stale ) {
	if ( is_dir( $bulk_stale ) ) {
		foreach ( (array) glob( $bulk_stale . '/*' ) as $bulk_inner ) {
			@unlink( $bulk_inner );
		}
		@rmdir( $bulk_stale );
	} elseif ( is_file( $bulk_stale ) ) {
		@unlink( $bulk_stale );
	}
}

$bulk_none = WPPA_Team_Files_Export::export_all();
$bulk_checks['без папки: выгрузки нет'] = false === $bulk_none['ok'] && '' !== (string) $bulk_none['message'];

update_option( 'wppa_settings', array_merge( (array) $export_backup, array( 'smb' => array( 'path' => $bulk_dir, 'user' => '', 'pass' => '' ) ) ) );

$bulk_team = WPPA_Teams::create( 'Команда выгрузки', 66 );
WPPA_Teams::add_member( $bulk_team, 67 );
WPPA_Teams::make_mentor( $bulk_team, 67 );

// Файл кладём, пока выгрузка выключена: копии в папке ещё нет.
update_option( 'wppa_settings', array_merge( (array) $export_backup, array( 'smb' => array( 'path' => '', 'user' => '', 'pass' => '' ) ) ) );

WPPA_Teams::set_stage2( $bulk_team, true, 63 );

$bulk_file = WPPA_Curator_Files::store( $bulk_team, wppa_test_file( 'bulk.pdf', str_repeat( 'g', 64 ) ), 'bulk.pdf', 67 );
$bulk_curator = WPPA_Curator_Files::store( $bulk_team, wppa_test_file( 'curator-bulk.pdf', 'note' ), 'curator-bulk.pdf', 63 );

update_option( 'wppa_settings', array_merge( (array) $export_backup, array( 'smb' => array( 'path' => $bulk_dir, 'user' => '', 'pass' => '' ) ) ) );

$bulk_checks['файл без копии'] = is_array( $bulk_file ) && ! is_file( WPPA_Team_Files_Export::local_dir( $bulk_team ) . '/bulk.pdf' );

// Проверка папки команд: пробная запись и уборка за собой.
$bulk_probe = WPPA_Team_Files_Export::probe();

$bulk_checks['проверка папки: запись работает']    = true === $bulk_probe['ok'];
$bulk_checks['проверка папки: путь в сообщении']   = false !== strpos( $bulk_probe['message'], 'Команды' );
$bulk_checks['проверка папки: режим в сообщении']  = false !== strpos( $bulk_probe['message'], 'filesystem' );
$bulk_checks['проверка папки: пробный файл убран'] = 0 === count( (array) glob( $bulk_dir . '/Команды/.wppa-probe-*' ) );

update_option( 'wppa_settings', array_merge( (array) $export_backup, array( 'smb' => array( 'path' => '', 'user' => '', 'pass' => '' ) ) ) );

$bulk_probe_none = WPPA_Team_Files_Export::probe();
$bulk_checks['проверка папки: без пути отказ'] = false === $bulk_probe_none['ok'] && false !== strpos( $bulk_probe_none['message'], 'Путь не задан' );

update_option( 'wppa_settings', array_merge( (array) $export_backup, array( 'smb' => array( 'path' => $bulk_dir, 'user' => '', 'pass' => '' ) ) ) );

$bulk_result = WPPA_Team_Files_Export::export_all();
$bulk_checks['кнопка: выгрузка прошла']    = true === $bulk_result['ok'];
$bulk_checks['кнопка: файл наставника посчитан'] = 1 === (int) $bulk_result['files'];
$bulk_checks['кнопка: команда в счёте']    = 1 === (int) $bulk_result['teams'];
$bulk_checks['кнопка: файл в папке команды'] = is_file( WPPA_Team_Files_Export::local_dir( $bulk_team ) . '/bulk.pdf' );
$bulk_checks['кнопка: содержимое совпадает'] = str_repeat( 'g', 64 ) === file_get_contents( WPPA_Team_Files_Export::local_dir( $bulk_team ) . '/bulk.pdf' );
$bulk_checks['кнопка: мета записана']       = isset( WPPA_Team_Files_Export::record( $bulk_team )[ $bulk_file['key'] ] );

$bulk_again = WPPA_Team_Files_Export::export_all();
$bulk_list  = (array) glob( WPPA_Team_Files_Export::local_dir( $bulk_team ) . '/*' );

$bulk_checks['повтор: без дублей']      = 1 === count( $bulk_list ) && is_file( WPPA_Team_Files_Export::local_dir( $bulk_team ) . '/bulk.pdf' );
$bulk_checks['повтор: имя не изменилось'] = 'bulk.pdf' === (string) WPPA_Team_Files_Export::record( $bulk_team )[ $bulk_file['key'] ];
$bulk_checks['повтор: счёт тот же']     = 1 === (int) $bulk_again['files'];

$bulk_checks['файл куратора не выгружен'] = ! is_file( WPPA_Team_Files_Export::local_dir( $bulk_team ) . '/curator-bulk.pdf' );

// Исходник пропал из загрузок: это не сбой, копия просто пропускается.
$bulk_missing_source = WPPA_Curator_Files::dir()['dir'] . '/' . WPPA_Curator_Files::files( $bulk_team )[ $bulk_file['key'] ]['file'];
$bulk_kept_copy      = WPPA_Team_Files_Export::local_dir( $bulk_team ) . '/bulk.pdf';

@rename( $bulk_missing_source, $bulk_missing_source . '.bak' );

$bulk_missing = WPPA_Team_Files_Export::export_all();
$bulk_checks['без исходника: не ошибка']        = true === $bulk_missing['ok'];
$bulk_checks['без исходника: пропуск посчитан'] = 1 === (int) $bulk_missing['skipped'];
$bulk_checks['без исходника: ошибок нет']       = 0 === (int) $bulk_missing['failed'];
$bulk_checks['без исходника: в сообщении']      = false !== strpos( $bulk_missing['message'], 'Пропущено' );
$bulk_checks['без исходника: причина в списке'] = false !== strpos( implode( ' ', $bulk_missing['errors'] ), 'исходного файла нет' );
$bulk_checks['без исходника: копия на месте']   = is_file( $bulk_kept_copy );

@rename( $bulk_missing_source . '.bak', $bulk_missing_source );

// Папка недоступна: ошибка с причиной, а не только «не полностью».
$bulk_bad_base = sys_get_temp_dir() . '/wppa-bulk-not-a-dir';

file_put_contents( $bulk_bad_base, 'x' );
update_option( 'wppa_settings', array_merge( (array) $export_backup, array( 'smb' => array( 'path' => $bulk_bad_base, 'user' => '', 'pass' => '' ) ) ) );

$bulk_failed = WPPA_Team_Files_Export::export_all();

$bulk_checks['плохая папка: ошибка есть']      = false === $bulk_failed['ok'] && (int) $bulk_failed['failed'] > 0;
$bulk_checks['плохая папка: причина в тексте'] = false !== strpos( $bulk_failed['message'], 'Первая:' );
$bulk_checks['плохая папка: подробности']      = false !== strpos( implode( ' ', $bulk_failed['errors'] ), 'bulk.pdf' );

@unlink( $bulk_bad_base );

update_option( 'wppa_settings', array_merge( (array) $export_backup, array( 'smb' => array( 'path' => $bulk_dir, 'user' => '', 'pass' => '' ) ) ) );

$bulk_empty = WPPA_Team_Files_Export::export_all();

if ( is_array( $bulk_curator ) ) {
	WPPA_Curator_Files::remove( $bulk_team, $bulk_curator['key'], 63 );
}

$bulk_team_dir = $bulk_dir . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, WPPA_Team_Files_Export::folder_path( $bulk_team ) );

WPPA_Teams::dissolve( $bulk_team );

$bulk_checks['после роспуска папки нет']        = ! is_dir( $bulk_team_dir );
$bulk_checks['после роспуска общей папки нет']  = ! is_dir( $bulk_dir . DIRECTORY_SEPARATOR . 'Команды' );

update_option( 'wppa_settings', $export_backup );

// Итог последней выгрузки запоминается для админки.
WPPA_Team_Files_Export::remember(
	array(
		'ok'       => false,
		'files'    => 3,
		'exported' => 2,
		'skipped'  => 0,
		'failed'   => 1,
		'message'  => 'Файлы наставников: выгружено 2 из 3, ошибок 1.',
		'errors'   => array( '«Команда» → файл.pdf: нет прав на запись' ),
	)
);
$bulk_last = WPPA_Team_Files_Export::last_result();
$bulk_checks['итог: сохранён']         = isset( $bulk_last['failed'] ) && 1 === (int) $bulk_last['failed'];
$bulk_checks['итог: причина на месте'] = false !== strpos( implode( ' ', (array) $bulk_last['errors'] ), 'нет прав' );
delete_option( WPPA_Team_Files_Export::LAST_OPTION );

foreach ( $bulk_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'team files bulk: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// Роспуск команды удаляет файлы.
$file_path = WPPA_Curator_Files::dir()['dir'] . '/' . WPPA_Curator_Files::files( $files_team )[ $stored['key'] ]['file'];

WPPA_Teams::dissolve( $files_team );

$file_checks['роспуск удалил файлы с диска'] = ! is_file( $file_path );
$file_checks['роспуск удалил список файлов'] = array() === WPPA_Curator_Files::files( $files_team );

WPPA_Teams::set_curator( 12, false );

$GLOBALS['wppa_test']['meta']  = $files_meta_backup;
$GLOBALS['wppa_test']['posts'] = $files_posts_backup;

foreach ( $file_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'curator files: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// Разделы и свои поля персональных данных (3.8.0).
$sections_checks = array();
$settings_backup = get_option( 'wppa_settings', array() );

$default_sections = WPPA_Fields::sections();
$main_schema      = WPPA_Fields::schema();

$sections_checks['разделы по умолчанию: 4']        = 4 === count( $default_sections );
$sections_checks['первый раздел — основной']       = 'main' === array_key_first( $default_sections );
$sections_checks['телефон лежит в «Контактах»']    = 'contacts' === $main_schema['phone']['section'];
$sections_checks['о компании — раздел «Работа»']   = 'work' === $main_schema['company']['section'];

/**
 * Сохранить только персональные данные, не трогая остальные настройки.
 *
 * @param array $input Поля формы.
 * @return array Санитизированные ключи.
 */
function wppa_test_save_fields( array $input, array $settings_backup ) {
	$sanitized = WPPA_Settings::sanitize( $input );

	update_option(
		'wppa_settings',
		array_merge(
			$settings_backup,
			array(
				'fields'         => $sanitized['fields'],
				'field_sections' => $sanitized['field_sections'],
			)
		)
	);

	return $sanitized;
}

$form_fields = array(
	'first_name' => array( 'enabled' => 1, 'required' => 1, 'label' => 'Имя участника', 'section' => 'main', 'order' => 10 ),
	'phone'      => array( 'enabled' => 1, 'required' => 0, 'section' => 'contacts', 'order' => 10 ),
	'city'       => array( 'enabled' => 1, 'section' => 'study', 'order' => 10 ),
);

$form_new_fields = array(
	array( 'key' => 'snils', 'label' => 'СНИЛС', 'type' => 'text', 'section' => 'study', 'order' => 20, 'enabled' => 1, 'required' => 1 ),
	array( 'key' => 'grade', 'label' => 'Класс', 'type' => 'select', 'section' => 'study', 'order' => 30, 'enabled' => 1 ),
	array( 'key' => '', 'label' => '' ),
);

$form_sections = array(
	'main'     => array( 'label' => 'Основные данные', 'order' => 10 ),
	'contacts' => array( 'label' => 'Контакты', 'order' => 20 ),
	'work'     => array( 'label' => 'Работа', 'order' => 30, 'delete' => 1 ),
	'about'    => array( 'label' => 'О себе', 'order' => 40 ),
);

$form_new_sections = array(
	array( 'key' => 'study', 'label' => 'Учёба', 'order' => 25 ),
	array( 'key' => '', 'label' => '' ),
);

$saved = wppa_test_save_fields(
	array(
		'fields'             => $form_fields,
		'fields_new'         => $form_new_fields,
		'field_sections'     => $form_sections,
		'field_sections_new' => $form_new_sections,
	),
	$settings_backup
);

$after_first   = WPPA_Fields::schema();
$after_all     = WPPA_Fields::all();
$after_section = WPPA_Fields::sections();

$sections_checks['своё поле сохранено']              = isset( $after_all['custom_snils'] );
$sections_checks['своё поле в схеме']                = isset( $after_first['custom_snils'] );
$sections_checks['своё поле: мета-ключ']             = '_wppa_custom_snils' === $after_all['custom_snils']['meta'];
$sections_checks['своё поле: раздел из формы']       = 'study' === $after_first['custom_snils']['section'];
$sections_checks['своё поле: обязательное']          = 1 === $after_first['custom_snils']['required'];
$sections_checks['пустая строка не создаёт поле']    = ! isset( $after_all['custom_'] ) && 2 === count( array_filter( array_keys( $after_all ), static function ( $id ) { return 0 === strpos( $id, 'custom_' ); } ) );
$sections_checks['подпись встроенного поля правится'] = 'Имя участника' === $after_first['first_name']['label'];
$sections_checks['новый раздел создан']              = isset( $after_section['study'] ) && 'Учёба' === $after_section['study']['label'];
$sections_checks['раздел удалён по галочке']         = ! isset( $after_section['work'] );
$sections_checks['поле перенесено в новый раздел']   = 'study' === $after_first['city']['section'];
$sections_checks['порядок разделов соблюдён']        = array( 'main', 'contacts', 'study', 'about' ) === array_slice( array_keys( $after_section ), 0, 4 );

$grouped = WPPA_Fields::grouped( 'register' );

$sections_checks['группировка: есть «Учёба»']        = isset( $grouped['study'] );
$sections_checks['группировка: город в «Учёбе»']     = isset( $grouped['study']['fields']['city'] );
$sections_checks['группировка: пустых разделов нет'] = ! isset( $grouped['about'] ) || ! empty( $grouped['about']['fields'] );

$custom_all       = WPPA_Fields::all();
$custom_def       = $custom_all['custom_grade'];
$custom_all_saved = $after_all;

$sections_checks['список без вариантов: значение не проходит'] = '' === WPPA_Fields::sanitize_value( 'custom_grade', '9' );

// Правим своё поле: варианты списка и тип «число».
$form_fields['custom_grade'] = array( 'enabled' => 1, 'required' => 0, 'label' => 'Класс', 'section' => 'study', 'order' => 30, 'type' => 'select', 'options' => "5\n6\n7" );
$form_fields['custom_snils'] = array( 'enabled' => 1, 'required' => 1, 'label' => 'СНИЛС', 'section' => 'study', 'order' => 20, 'type' => 'text' );

$form_new_fields_2 = array(
	array( 'key' => 'ball', 'label' => 'Балл', 'type' => 'number', 'section' => 'study', 'order' => 40, 'enabled' => 1 ),
	array( 'key' => 'agree', 'label' => 'Согласие', 'type' => 'checkbox', 'section' => 'study', 'order' => 50, 'enabled' => 1 ),
);

wppa_test_save_fields(
	array(
		'fields'             => $form_fields,
		'fields_new'         => $form_new_fields_2,
		'field_sections'     => $form_sections,
		'field_sections_new' => $form_new_sections,
	),
	$settings_backup
);

$sections_checks['список: вариант из настроек принимается'] = '6' === WPPA_Fields::sanitize_value( 'custom_grade', '6' );
$sections_checks['список: чужое значение отбрасывается']   = '' === WPPA_Fields::sanitize_value( 'custom_grade', '11' );
$sections_checks['число: запятая превращается в точку']    = '12.5' === WPPA_Fields::sanitize_value( 'custom_ball', '12,5' );
$sections_checks['число: буквы отбрасываются']             = '' === WPPA_Fields::sanitize_value( 'custom_ball', 'двенадцать' );
$sections_checks['число: пусто и целое']                   = '' === WPPA_Fields::sanitize_value( 'custom_ball', '' ) && '12' === WPPA_Fields::sanitize_value( 'custom_ball', '12' );
$sections_checks['флажок: снят и отмечен']                 = '' === WPPA_Fields::sanitize_value( 'custom_agree', '' ) && '1' === WPPA_Fields::sanitize_value( 'custom_agree', '1' );

$validation_values = array( 'custom_snils' => '123-456-789 00', 'custom_grade' => '5' );

$sections_checks['валидация: свои поля проходят']       = '' === WPPA_Fields::validate( $validation_values, 'profile' );
$sections_checks['валидация: обязательное своё поле']   = 'field_required' === WPPA_Fields::validate( array( 'custom_grade' => '5' ), 'profile' );
$sections_checks['валидация: список вне вариантов']     = 'field_invalid' === WPPA_Fields::validate( array( 'custom_snils' => '1', 'custom_grade' => '42' ), 'profile' );

// Значение своего поля сохраняется и читается.
WPPA_Fields::save_meta( 33, array( 'custom_snils' => '123-456-789 00', 'custom_grade' => '6' ) );

$sections_checks['своё поле: сохранение в мета'] = '123-456-789 00' === WPPA_Fields::get( 33, 'custom_snils' );
$sections_checks['своё поле: значение списка']   = '6' === WPPA_Fields::get( 33, 'custom_grade' );

// Своё поле попадает в выгрузку.
$export_keys = array_column( WPPA_Export::columns(), 'key' );

$sections_checks['выгрузка: колонка своего поля'] = in_array( 'field_custom_snils', $export_keys, true );
$personal_columns = array_filter(
	WPPA_Export::columns(),
	static function ( $column ) {
		return 0 === strpos( $column['key'], 'field_' );
	}
);

$sections_checks['выгрузка: личные поля в группе «Личные данные»'] = array() !== $personal_columns && array( 'personal' ) === array_values( array_unique( array_column( $personal_columns, 'group' ) ) );

// Раздел «Учёба» удаляем: его поля остаются и переезжают в основной раздел.
$form_sections_deleted = $form_sections;
unset( $form_sections_deleted['work'] );

$form_fields_delete = $form_fields;
$form_fields_delete['custom_ball'] = array( 'enabled' => 1, 'required' => 0, 'label' => 'Балл', 'section' => 'study', 'order' => 40, 'type' => 'number', 'delete' => '1' );

wppa_test_save_fields(
	array(
		'fields'             => $form_fields_delete,
		'fields_new'         => array(),
		'field_sections'     => array(
			'main'     => array( 'label' => 'Основные данные', 'order' => 10 ),
			'contacts' => array( 'label' => 'Контакты', 'order' => 20 ),
			'study'    => array( 'label' => 'Учёба', 'order' => 25, 'delete' => '1' ),
			'about'    => array( 'label' => 'О себе', 'order' => 40 ),
		),
		'field_sections_new' => array(),
	),
	$settings_backup
);

$after_delete    = WPPA_Fields::schema();
$sections_delete = WPPA_Fields::sections();

$sections_checks['своё поле удалено']                  = ! isset( $after_delete['custom_ball'] );
$sections_checks['свой раздел удалён']                 = ! isset( $sections_delete['study'] );
$sections_checks['поля удалённого раздела → основной'] = 'main' === $after_delete['custom_snils']['section'] && 'main' === $after_delete['city']['section'];
$sections_checks['основной раздел удалить нельзя']     = isset( $sections_delete['main'] );

// Форма профиля рисует разделы.
ob_start();
WPPA_Fields::render( 'profile', 33 );
$rendered = ob_get_clean();

$sections_checks['форма: заголовок раздела'] = false !== strpos( $rendered, 'wppa-section__title' );
$sections_checks['форма: подпись своего поля'] = false !== strpos( $rendered, 'СНИЛС' );
$sections_checks['форма: значение своего поля'] = false !== strpos( $rendered, '123-456-789 00' );

update_option( 'wppa_settings', $settings_backup );

foreach ( $sections_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'personal: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// Регресс 3.7.1: deprecation в fputcsv() и fatal error на wp_tempnam() во фронтенде.
$csv_checks = array();

$deprecations = array();
set_error_handler(
	static function ( $errno, $errstr ) use ( &$deprecations ) {
		$deprecations[] = $errstr;

		return true;
	},
	E_DEPRECATED | E_USER_DEPRECATED
);

$line = WPPA_Export::line( array( 'обычное', 'с ; точкой с запятой', 'кавычка "внутри"', 'слэш \\ и перенос
строки', 'ё' ) );

restore_error_handler();

$parsed = str_getcsv( rtrim( $line, "\r\n" ), ';', '"', '\\' );

$csv_checks['fputcsv без deprecation (PHP 8.4)'] = array() === $deprecations;
$csv_checks['строка CSV разбирается обратно']    = 5 === count( $parsed ) && 'кавычка "внутри"' === $parsed[2];
$csv_checks['разделитель не ломает поле']        = 'с ; точкой с запятой' === $parsed[1];
$csv_checks['кириллица цела']                    = 'ё' === $parsed[4];

$smb_source = (string) file_get_contents( WPPA_PATH . 'includes/class-wppa-smb.php' );

// wp_tempnam() живёт в wp-admin/includes/file.php: на фронтенде его нет.
// Вызов допустим только один — внутри обёртки temp_file(), после подключения файла.
$tempnam_calls = 0;

foreach ( explode( "\n", $smb_source ) as $smb_line ) {
	$code = trim( $smb_line );

	if ( '' === $code || 0 === strpos( $code, '*' ) || 0 === strpos( $code, '//' ) ) {
		continue;
	}

	if ( false === strpos( $code, 'wp_tempnam(' ) || false !== strpos( $code, 'function_exists' ) ) {
		continue;
	}

	$tempnam_calls++;
}

$csv_checks['прямых вызовов wp_tempnam не осталось'] = 1 === $tempnam_calls;
$csv_checks['есть обёртка WPPA_SMB::temp_file()']    = false !== strpos( $smb_source, 'public static function temp_file(' );
$csv_checks['wp-admin/includes/file.php подключается'] = false !== strpos( $smb_source, "'wp-admin/includes/file.php'" );

$temp = WPPA_SMB::temp_file( 'wppa-regress-' . getmypid() );
$csv_checks['temp_file() создаёт файл'] = is_string( $temp ) && file_exists( $temp );

if ( $temp ) {
	@unlink( $temp );
}

$creds = WPPA_SMB::credentials_file( array( 'user' => 'домен\\юзер', 'pass' => 'секрет' ), array() );

if ( is_string( $creds ) && file_exists( $creds ) ) {
	$content = (string) file_get_contents( $creds );

	$csv_checks['credentials_file() создаёт файл']   = true;
	$csv_checks['в файле учётных данных есть логин'] = false !== strpos( $content, 'username = юзер' );
	$csv_checks['права на файл 0600']               = 0600 === ( fileperms( $creds ) & 0777 );

	@unlink( $creds );
} else {
	$csv_checks['credentials_file() создаёт файл']   = false;
	$csv_checks['в файле учётных данных есть логин'] = false;
	$csv_checks['права на файл 0600']               = false;
}

// Выгрузка по изменению выполняется внутри запроса: любая ошибка — в журнал, не в белый экран.
$folder_source = (string) file_get_contents( WPPA_PATH . 'includes/class-wppa-export-folder.php' );
$run_change    = preg_match( '/function run_change\(.*?function \w+/s', $folder_source, $run_match ) ? $run_match[0] : '';

$csv_checks['run_change() ловит Throwable']          = false !== strpos( $run_change, 'catch ( \Throwable $e )' );
$csv_checks['SMB-ветка write() ловит Throwable']     = false !== strpos( $folder_source, "if ( 'smb' === \$args['mode'] ) {\n\t\t\ttry {" );

foreach ( $csv_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'csv/smb: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// Рассылка участникам команд второго этапа: шаблон, очередь, задержка, отмена.
$mail_checks = array();

// Включённые вкладки: у существующих установок «Рассылка» появляется в обновлении.
$mail_settings_backup = get_option( 'wppa_settings', array() );
$mail_settings_tabs   = (array) ( $mail_settings_backup['enabled_tabs'] ?? array() );
$mail_settings_tabs[] = 'mailing';

update_option( 'wppa_settings', array_merge( (array) $mail_settings_backup, array( 'enabled_tabs' => $mail_settings_tabs ) ) );
$mail_checks = array();
$mail_snapshot = array(
	'meta'   => isset( $GLOBALS['wppa_test']['meta'] ) ? $GLOBALS['wppa_test']['meta'] : array(),
	'users'  => isset( $GLOBALS['wppa_test']['users'] ) ? $GLOBALS['wppa_test']['users'] : array(),
	'mail'   => isset( $GLOBALS['wppa_test']['mail'] ) ? $GLOBALS['wppa_test']['mail'] : array(),
);

$GLOBALS['wppa_test']['users'][71]['email'] = 'captain@example.com';
$GLOBALS['wppa_test']['users'][72]['email'] = 'mentor@example.com';
$GLOBALS['wppa_test']['users'][73]['email'] = 'member@example.com';
$GLOBALS['wppa_test']['users'][75]['email'] = 'member2@example.com';
$GLOBALS['wppa_test']['users'][76]['email'] = 'member3@example.com';
$GLOBALS['wppa_test']['users'][77]['email'] = 'member4@example.com';
$GLOBALS['wppa_test']['users'][79]['email'] = 'second50@example.com';
$GLOBALS['wppa_test']['mail']               = array();

delete_option( WPPA_Mailing::OPTION );
delete_option( WPPA_Mailing::QUEUE );
delete_option( WPPA_Mailing::LOG );
delete_user_meta( 71, WPPA_Mailing::META_SENT );
delete_user_meta( 72, WPPA_Mailing::META_SENT );
delete_user_meta( 73, WPPA_Mailing::META_SENT );
delete_user_meta( 75, WPPA_Mailing::META_SENT );
delete_user_meta( 76, WPPA_Mailing::META_SENT );
delete_user_meta( 77, WPPA_Mailing::META_SENT );

$mail_defaults = WPPA_Mailing::defaults();
$mail_checks['шаблон: тема по умолчанию']  = '' !== (string) $mail_defaults['subject'];
$mail_checks['шаблон: текст по умолчанию'] = false !== strpos( (string) $mail_defaults['body'], '{имя}' );

$mail_saved = WPPA_Mailing::save(
	array(
		'subject'   => 'Второй этап: {команда}',
		'body'      => "Привет, {имя}!\n\nКоманда «{команда}» прошла во второй этап {дата_отметки}.\n\nПисьмо: {email}.",
		'interval'  => 3,
		'batch'     => 2,
		'skip_sent' => 0,
	)
);
$mail_checks['шаблон: тема сохранена']     = 'Второй этап: {команда}' === $mail_saved['subject'];
$mail_checks['шаблон: интервал сохранён']  = 3 === (int) $mail_saved['interval'];
$mail_checks['шаблон: пачка сохранена']    = 2 === (int) $mail_saved['batch'];
$mail_checks['шаблон: повтор разрешён']    = 0 === (int) $mail_saved['skip_sent'];

$mail_clamped = WPPA_Mailing::save(
	array(
		'subject'   => 'Второй этап: {команда}',
		'body'      => 'Текст {имя}',
		'interval'  => 99999,
		'batch'     => 999,
		'skip_sent' => 1,
	)
);
$mail_checks['настройки: интервал ограничен'] = 1440 === (int) $mail_clamped['interval'];
$mail_checks['настройки: пачка ограничена']   = 50 === (int) $mail_clamped['batch'];

// Своя команда второго этапа: капитан 71, наставник 72, участник 73.
$mail_team = WPPA_Teams::create( 'Команда рассылки', 71 );

if ( is_wp_error( $mail_team ) ) {
	$mail_checks['команда рассылки создана'] = false;
} else {
	$mail_checks['команда рассылки создана'] = true;

	WPPA_Teams::add_member( $mail_team, 72 );
	WPPA_Teams::make_mentor( $mail_team, 72 );
	WPPA_Teams::add_member( $mail_team, 73 );
	WPPA_Teams::add_member( $mail_team, 75 );
	WPPA_Teams::add_member( $mail_team, 76 );
	WPPA_Teams::add_member( $mail_team, 77 );

	$mail_checks['получатели: без отметки никого'] = 0 === count(
		array_filter(
			WPPA_Mailing::recipients(),
			static function ( $row ) use ( $mail_team ) {
				return (int) $row['team_id'] === (int) $mail_team;
			}
		)
	);

	WPPA_Teams::set_curator( 74, true );
	WPPA_Teams::set_stage2( $mail_team, true, 74 );

	$mail_recipients = array_values(
		array_filter(
			WPPA_Mailing::recipients(),
			static function ( $row ) use ( $mail_team ) {
				return (int) $row['team_id'] === (int) $mail_team;
			}
		)
	);

	$mail_checks['получатели: шесть адресов']     = 6 === count( $mail_recipients );
	$mail_checks['получатели: капитан в списке'] = in_array( 'captain@example.com', array_column( $mail_recipients, 'email' ), true );
	$mail_checks['получатели: наставник в списке'] = in_array( 'mentor@example.com', array_column( $mail_recipients, 'email' ), true );
	$mail_checks['получатели: участник в списке']  = in_array( 'member@example.com', array_column( $mail_recipients, 'email' ), true );

	$mail_recipients = array_values(
		array_map(
			static function ( $row ) {
				$row['first'] = 'Иван';
				return $row;
			},
			$mail_recipients
		)
	);

	$mail_preview = WPPA_Mailing::render( 'Привет, {имя}! Команда «{команда}»', $mail_recipients[0] );
	$mail_checks['подстановки: имя']    = false !== strpos( $mail_preview, 'Иван' );
	$mail_checks['подстановки: команда'] = false !== strpos( $mail_preview, 'Команда рассылки' );

	$mail_html = WPPA_Mailing::to_html( "Строка один\n\nСтрока два" );
	$mail_checks['письмо: абзацы в HTML'] = 2 === substr_count( $mail_html, '<p>' );

	$mail_options = get_option( WPPA_Mailing::OPTION, array() );
	$mail_options['skip_sent'] = 0;
	update_option( WPPA_Mailing::OPTION, $mail_options );

	$mail_queued = WPPA_Mailing::start();
	$mail_checks['очередь: письма поставлены'] = 6 === $mail_queued;

	$mail_run = WPPA_Mailing::run_state();
	$mail_checks['очередь: шаг между письмами'] = isset( $mail_run['items'][1]['due'], $mail_run['items'][0]['due'] )
		&& ( (int) $mail_run['items'][1]['due'] - (int) $mail_run['items'][0]['due'] ) === ( (int) $mail_run['interval'] * 60 );
	$mail_checks['очередь: первое письмо сразу'] = (int) $mail_run['items'][0]['due'] <= time();

	$mail_stats = WPPA_Mailing::stats();
	$mail_checks['очередь: сводка']        = 6 === (int) $mail_stats['total'] && 6 === (int) $mail_stats['pending'];
	$mail_checks['очередь: есть следующее'] = (int) $mail_stats['next'] > 0;

	// Один запуск WP-Cron — только письма, которым пришло время.
	$mail_sent = WPPA_Mailing::run_cron();
	$mail_checks['отправка: одно письмо за раз'] = 1 === (int) $mail_sent;
	$mail_checks['отправка: wp_mail вызван']     = 1 === count( (array) $GLOBALS['wppa_test']['mail'] );

	$mail_letter = isset( $GLOBALS['wppa_test']['mail'][0] ) ? $GLOBALS['wppa_test']['mail'][0] : array();
	$mail_checks['письмо: тема с подстановкой'] = false !== strpos( (string) $mail_letter['subject'], 'Команда рассылки' );
	$mail_checks['письмо: HTML-тело']           = false !== strpos( (string) $mail_letter['message'], '<p>' );
	$mail_checks['письмо: заголовок HTML']      = in_array( 'Content-Type: text/html; charset=UTF-8', (array) $mail_letter['headers'], true );

	$mail_stats = WPPA_Mailing::stats();
	$mail_checks['отправка: сводка обновилась'] = 1 === (int) $mail_stats['sent'] && 5 === (int) $mail_stats['pending'];

	$mail_first_id = 0;
	foreach ( $mail_run['items'] as $mail_item ) {
		if ( (int) get_user_meta( (int) $mail_item['user_id'], WPPA_Mailing::META_SENT, true ) > 0 ) {
			$mail_first_id = (int) $mail_item['user_id'];
			break;
		}
	}
	$mail_checks['отправка: отметка у получателя'] = $mail_first_id > 0;

	// Отмена: очередь очищается, расписание снимается.
	$mail_checks['отмена: очередь была'] = true === WPPA_Mailing::cancel();
	$mail_checks['отмена: очередь пуста'] = false === WPPA_Mailing::stats()['active'];
	$mail_checks['отмена: повторно нечего'] = false === WPPA_Mailing::cancel();

	// Ошибки отправки: пауза после пяти неудач подряд.
	$GLOBALS['wppa_test']['mail_fail'] = true;
	$mail_options                      = get_option( WPPA_Mailing::OPTION, array() );
	$mail_options['batch']             = 50;
	update_option( WPPA_Mailing::OPTION, $mail_options );

	$mail_checks['ошибки: очередь поставлена'] = 6 === WPPA_Mailing::start();
	$mail_failed_sent                          = WPPA_Mailing::send_all();
	$mail_stats                                = WPPA_Mailing::stats();
	$mail_checks['ошибки: писем не отправлено'] = 0 === (int) $mail_failed_sent;
	$mail_checks['ошибки: рассылка на паузе']   = '' !== (string) $mail_stats['paused'];
	$mail_checks['ошибки: ошибки посчитаны']    = 5 === (int) $mail_stats['failed'];
	$mail_checks['ошибки: журнал пишется']      = count( WPPA_Mailing::logs() ) > 0;

	$GLOBALS['wppa_test']['mail_fail'] = false;
	$mail_checks['ошибки: пауза снимается']     = true === WPPA_Mailing::resume();

	// Кабинет: куратор может отменить рассылку, участник — нет.
	$mail_redirects = function ( $user_id ) {
		$GLOBALS['wppa_test']['catch_redirect'] = true;
		$GLOBALS['wppa_test']['current_user']   = $user_id;
		$_POST                                  = array(
			'wppa_action'         => 'mailing',
			'wppa_mailing_action' => 'cancel',
			'wppa_nonce'          => wp_create_nonce( 'wppa_mailing' ),
		);

		try {
			WPPA_Mailing::handle_request();
		} catch ( RuntimeException $e ) {
			$GLOBALS['wppa_test']['catch_redirect'] = false;
			$query = (string) parse_url( substr( $e->getMessage(), strlen( 'redirect:' ) ), PHP_URL_QUERY );
			$args  = array();
			parse_str( $query, $args );

			return isset( $args['wppa_notice'] ) ? (string) $args['wppa_notice'] : '';
		}

		$GLOBALS['wppa_test']['catch_redirect'] = false;

		return '';
	};

	$mail_checks['кабинет: участнику нельзя']  = 'mailing_denied' === $mail_redirects( 73 );
	$mail_checks['кабинет: куратор отменяет']  = 'mailing_cancelled' === $mail_redirects( 74 );
	$mail_checks['кабинет: повторно пусто']    = 'mailing_empty' === $mail_redirects( 74 );

	$_POST = array();

	// Раздел «Рассылка» в кабинете куратора: вкладка, форма, шаблон, очередь.
	$mail_post = function ( $user_id, $action, $extra = array() ) {
		$GLOBALS['wppa_test']['catch_redirect'] = true;
		$GLOBALS['wppa_test']['current_user']   = $user_id;
		$_POST                                  = array_merge(
			array(
				'wppa_action'         => 'mailing',
				'wppa_mailing_action' => $action,
				'wppa_nonce'          => wp_create_nonce( 'wppa_mailing' ),
			),
			$extra
		);

		try {
			WPPA_Mailing::handle_request();
		} catch ( RuntimeException $e ) {
			$GLOBALS['wppa_test']['catch_redirect'] = false;
			$url   = substr( $e->getMessage(), strlen( 'redirect:' ) );
			$args  = array();
			parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $args );

			return array(
				'code' => isset( $args['wppa_notice'] ) ? (string) $args['wppa_notice'] : '',
				'url'  => $url,
			);
		}

		$GLOBALS['wppa_test']['catch_redirect'] = false;

		return array( 'code' => '', 'url' => '' );
	};

	$GLOBALS['wppa_test']['current_user'] = 74;
	$mail_curator_tabs                    = WPPA_Template::tabs();

	$mail_checks['вкладка: у куратора есть']  = array_key_exists( 'mailing', $mail_curator_tabs );
	$mail_checks['вкладка: подпись']          = isset( $mail_curator_tabs['mailing'] ) && 'Рассылка' === $mail_curator_tabs['mailing'];

	$GLOBALS['wppa_test']['current_user'] = 73;
	$mail_checks['вкладка: участнику нет']    = ! array_key_exists( 'mailing', WPPA_Template::tabs() );

	$GLOBALS['wppa_test']['current_user'] = 74;

	$mail_block = array( 'type' => 'block', 'id' => 'mailing-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' );
	$mail_html_curator = WPPA_Builder::render_block( $mail_block, array( 'user' => get_userdata( 74 ) ) );
	$mail_html_member  = WPPA_Builder::render_block( $mail_block, array( 'user' => get_userdata( 73 ) ) );

	$mail_checks['блок: у куратора форма']      = false !== strpos( $mail_html_curator, 'Шаблон письма' ) && false !== strpos( $mail_html_curator, 'wppa_mailing_action' );
	$mail_checks['блок: кнопка очереди']        = false !== strpos( $mail_html_curator, 'Поставить в очередь' );
	$mail_checks['блок: журнал']                = false !== strpos( $mail_html_curator, 'Журнал рассылки' );
	$mail_checks['блок: участнику пусто']       = '' === trim( $mail_html_member );
	$mail_checks['блок: в конструкторе есть']   = array_key_exists( 'mailing-widget', WPPA_Builder::blocks() );

	$mail_saved = $mail_post(
		74,
		'save',
		array(
			'subject'  => 'Письмо от куратора',
			'body'     => 'Текст письма {имя}',
			'interval' => 3,
			'batch'    => 2,
		)
	);

	$mail_checks['кабинет: шаблон сохраняется'] = 'mailing_saved' === $mail_saved['code'];
	$mail_checks['кабинет: тема сохранена']    = 'Письмо от куратора' === WPPA_Mailing::settings()['subject'];
	$mail_checks['кабинет: возврат на вкладку'] = false !== strpos( $mail_saved['url'], 'wppa_tab=mailing' );

	$mail_started = $mail_post( 74, 'start' );
	$mail_checks['кабинет: очередь ставится']  = 'mailing_started' === $mail_started['code'] && ! empty( WPPA_Mailing::stats()['active'] );

	$mail_send = $mail_post( 74, 'send_now' );
	$mail_checks['кабинет: отправка остатка']  = in_array( $mail_send['code'], array( 'mailing_sent_now', 'mailing_send_failed' ), true );

	WPPA_Mailing::log( 'Проверка журнала' );

	$mail_clear_log = $mail_post( 74, 'clear_log' );
	$mail_checks['кабинет: журнал чистится']   = 'mailing_log_cleared' === $mail_clear_log['code'] && array() === WPPA_Mailing::logs();

	$mail_checks['кабинет: участнику не сохранить'] = 'mailing_denied' === $mail_post( 73, 'save', array( 'subject' => 'Взлом' ) )['code'];

	$_POST = array();

	// Карточка состояния на главной видна только куратору.
	WPPA_Mailing::start();

	$GLOBALS['wppa_test']['current_user'] = 74;
	$mail_dash_curator                    = WPPA_Template::get( 'tab-dashboard', array( 'user' => get_userdata( 74 ), 'tabs' => WPPA_Template::tabs() ) );
	$mail_checks['главная: куратор видит очередь'] = false !== strpos( $mail_dash_curator, 'Рассылка участникам второго этапа' );

	$mail_dash_member = WPPA_Template::get( 'tab-dashboard', array( 'user' => get_userdata( 73 ), 'tabs' => WPPA_Template::tabs() ) );
	$mail_checks['главная: участник не видит']    = false === strpos( $mail_dash_member, 'Рассылка участникам второго этапа' );

	$GLOBALS['wppa_test']['current_user'] = 73;

	WPPA_Mailing::cancel();
	WPPA_Teams::set_stage2( $mail_team, false, 74 );
	WPPA_Teams::dissolve( $mail_team );
}

WPPA_Teams::set_curator( 74, false );

delete_option( WPPA_Mailing::OPTION );
delete_option( WPPA_Mailing::QUEUE );
delete_option( WPPA_Mailing::LOG );

foreach ( array( 71, 72, 73, 75, 76, 77, 79 ) as $mail_reset_user ) {
	delete_user_meta( $mail_reset_user, WPPA_Mailing::META_SENT );
}

$GLOBALS['wppa_test']['meta'] = $mail_snapshot['meta'];
$GLOBALS['wppa_test']['mail'] = $mail_snapshot['mail'];

update_option( 'wppa_settings', $mail_settings_backup );

foreach ( $mail_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'mailing: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// Снятие отметки второго этапа: письма команде не отправляются, окно «случайного нажатия» — минута.
$drop_checks   = array();
$drop_snapshot = array(
	'meta'   => isset( $GLOBALS['wppa_test']['meta'] ) ? $GLOBALS['wppa_test']['meta'] : array(),
	'mail'   => isset( $GLOBALS['wppa_test']['mail'] ) ? $GLOBALS['wppa_test']['mail'] : array(),
);

$GLOBALS['wppa_test']['users'][81]['email'] = 'drop1@example.com';
$GLOBALS['wppa_test']['users'][82]['email'] = 'drop2@example.com';

delete_user_meta( 81, WPPA_Teams::META_TEAM );
delete_user_meta( 82, WPPA_Teams::META_TEAM );
delete_option( WPPA_Mailing::OPTION );
delete_option( WPPA_Mailing::QUEUE );
delete_option( WPPA_Mailing::LOG );
delete_user_meta( 81, WPPA_Mailing::META_SENT );
delete_user_meta( 82, WPPA_Mailing::META_SENT );

$drop_team = WPPA_Teams::create( 'Команда отмены', 81 );

if ( is_wp_error( $drop_team ) ) {
	$drop_checks['команда создана'] = false;
} else {
	$drop_checks['команда создана'] = true;

	WPPA_Teams::add_member( $drop_team, 82 );
	WPPA_Teams::set_curator( 74, true );
	WPPA_Mailing::save(
		array(
			'subject'   => 'Второй этап: {команда}',
			'body'      => 'Привет, {имя}!',
			'interval'  => 10,
			'batch'     => 10,
			'skip_sent' => 0,
		)
	);

	// 1. Отметка стоит давно: снятие убирает письма из очереди.
	WPPA_Teams::set_stage2( $drop_team, true, 74 );
	update_post_meta( $drop_team, WPPA_Teams::META_STAGE2, array( 'time' => time() - 600, 'user' => 74 ) );

	$drop_log_has = function ( $needle ) {
		foreach ( WPPA_Mailing::logs() as $drop_log ) {
			if ( false !== strpos( (string) $drop_log['text'], $needle ) ) {
				return true;
			}
		}

		return false;
	};

	$drop_mail_before = count( (array) $GLOBALS['wppa_test']['mail'] );
	$drop_checks['очередь: два письма'] = 2 === WPPA_Mailing::start();
	$drop_checks['очередь: письма ждут'] = 2 === (int) WPPA_Mailing::stats()['pending'];

	WPPA_Teams::set_stage2( $drop_team, false, 74 );

	$drop_last = WPPA_Mailing::last_drop();
	$drop_checks['снятие: письма убраны из очереди'] = 2 === (int) $drop_last['count'];
	$drop_checks['снятие: очередь очищена']          = false === WPPA_Mailing::stats()['active'];
	$drop_checks['снятие: это не случайное нажатие'] = false === $drop_last['grace'];
	$drop_checks['снятие: письма не ушли']           = $drop_mail_before === count( (array) $GLOBALS['wppa_test']['mail'] );
	$drop_checks['снятие: запись в журнале']         = $drop_log_has( 'убрано писем' );

	// 2. Отметку сняли сразу после установки — окно «случайного нажатия».
	WPPA_Teams::set_stage2( $drop_team, true, 74 );
	$drop_checks['окно: очередь поставлена'] = 2 === WPPA_Mailing::start();
	WPPA_Teams::set_stage2( $drop_team, false, 74 );

	$drop_last = WPPA_Mailing::last_drop();
	$drop_checks['окно: снятие в течение минуты']  = true === $drop_last['grace'];
	$drop_checks['окно: письма убраны']            = 2 === (int) $drop_last['count'];
	$drop_checks['окно: пометка в журнале']        = $drop_log_has( 'случайного нажатия' );
	$drop_checks['окно: письма не ушли']           = $drop_mail_before === count( (array) $GLOBALS['wppa_test']['mail'] );

	// 3. Отметку сняли в обход очереди — письма всё равно не отправляются.
	WPPA_Teams::set_stage2( $drop_team, true, 74 );
	WPPA_Mailing::start();
	delete_post_meta( $drop_team, WPPA_Teams::META_STAGE2 );

	$drop_checks['защита: ничего не отправлено'] = 0 === (int) WPPA_Mailing::process_due( true );
	$drop_checks['защита: письма помечены пропущенными'] = 2 === (int) WPPA_Mailing::stats()['skipped'];
	$drop_checks['защита: wp_mail не вызывался'] = $drop_mail_before === count( (array) $GLOBALS['wppa_test']['mail'] );
	$drop_checks['защита: запись в журнале']     = $drop_log_has( 'Пропущено писем без отправки' );

	WPPA_Mailing::cancel();

	// 4. Уведомления обработчика: обычное снятие, снятие с очередью, случайное нажатие.
	$drop_notice = function ( $mode ) use ( $drop_team ) {
		if ( 'with_queue' === $mode ) {
			WPPA_Teams::set_stage2( $drop_team, true, 74 );
			update_post_meta( $drop_team, WPPA_Teams::META_STAGE2, array( 'time' => time() - 600, 'user' => 74 ) );
			WPPA_Mailing::start();
		} elseif ( 'grace' === $mode ) {
			WPPA_Teams::set_stage2( $drop_team, true, 74 );
			WPPA_Mailing::start();
		} else {
			WPPA_Teams::set_stage2( $drop_team, true, 74 );
			update_post_meta( $drop_team, WPPA_Teams::META_STAGE2, array( 'time' => time() - 600, 'user' => 74 ) );
			WPPA_Mailing::cancel();
		}

		$GLOBALS['wppa_test']['catch_redirect'] = true;
		$GLOBALS['wppa_test']['current_user']   = 74;
		$_POST                                  = array(
			'wppa_action'      => 'team',
			'wppa_team_action' => 'stage2_remove',
			'team_id'          => $drop_team,
		);
		$_GET                                   = array();

		try {
			WPPA_Teams::handle_request();
		} catch ( RuntimeException $e ) {
			$GLOBALS['wppa_test']['catch_redirect'] = false;
			$query = (string) parse_url( substr( $e->getMessage(), strlen( 'redirect:' ) ), PHP_URL_QUERY );
			$args  = array();
			parse_str( $query, $args );

			return isset( $args['wppa_notice'] ) ? (string) $args['wppa_notice'] : '';
		}

		$GLOBALS['wppa_test']['catch_redirect'] = false;

		return '';
	};

	$drop_checks['уведомление: обычное снятие'] = 'stage2_removed' === $drop_notice( 'plain' );
	$drop_checks['уведомление: письма убраны']  = 'stage2_removed_mail' === $drop_notice( 'with_queue' );
	$drop_checks['уведомление: случайное нажатие'] = 'stage2_removed_grace' === $drop_notice( 'grace' );

	// Отметка из списка команд не должна открывать карточку команды.
	WPPA_Teams::set_stage2( $drop_team, false, 74 );
	WPPA_Mailing::cancel();

	$drop_url = function ( $extra ) use ( $drop_team ) {
		$GLOBALS['wppa_test']['catch_redirect'] = true;
		$GLOBALS['wppa_test']['current_user']   = 74;
		$_POST                                  = array_merge(
			array(
				'wppa_action'      => 'team',
				'wppa_team_action' => 'stage2_add',
				'team_id'          => $drop_team,
			),
			$extra
		);
		$_GET                                   = array();

		try {
			WPPA_Teams::handle_request();
		} catch ( RuntimeException $e ) {
			$GLOBALS['wppa_test']['catch_redirect'] = false;

			return substr( $e->getMessage(), strlen( 'redirect:' ) );
		}

		$GLOBALS['wppa_test']['catch_redirect'] = false;

		return '';
	};

	$drop_list_url = $drop_url( array( 'wppa_stay' => 'list' ) );
	$drop_card_url = $drop_url( array() );

	$drop_checks['список: остаёмся в списке']   = false !== strpos( $drop_list_url, 'wppa_notice=stage2_added' ) && false === strpos( $drop_list_url, 'wppa_team=' );
	$drop_checks['список: команда отмечена']    = true === WPPA_Teams::is_stage2( $drop_team );
	$drop_checks['карточка: команда в адресе']  = false !== strpos( $drop_card_url, 'wppa_team=' . (int) $drop_team );

	// В разметке списка у каждой кнопки отметки есть признак «остаться в списке».
	$GLOBALS['wppa_test']['current_user'] = 74;
	unset( $_GET['wppa_team'] );

	$drop_widget = WPPA_Builder::render_block(
		array( 'type' => 'block', 'id' => 'team-widget', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
		array( 'user' => get_userdata( 74 ) )
	);

	$drop_stay   = substr_count( $drop_widget, 'name="wppa_stay" value="list"' );
	$drop_marks  = substr_count( $drop_widget, 'value="stage2_add"' ) + substr_count( $drop_widget, 'value="stage2_remove"' );

	$drop_checks['разметка: список не открывает карточку'] = $drop_stay > 0 && $drop_stay === $drop_marks;

	$_POST = array();

	WPPA_Mailing::cancel();
	WPPA_Teams::set_stage2( $drop_team, false, 74 );
	WPPA_Teams::dissolve( $drop_team );
}

WPPA_Teams::set_curator( 74, false );

delete_option( WPPA_Mailing::OPTION );
delete_option( WPPA_Mailing::QUEUE );
delete_option( WPPA_Mailing::LOG );
delete_user_meta( 81, WPPA_Mailing::META_SENT );
delete_user_meta( 82, WPPA_Mailing::META_SENT );

$GLOBALS['wppa_test']['meta'] = $drop_snapshot['meta'];
$GLOBALS['wppa_test']['mail'] = $drop_snapshot['mail'];

foreach ( $drop_checks as $check_name => $ok ) {
	echo ( $ok ? 'OK    ' : 'FAIL  ' ) . 'stage2-undo: ' . $check_name . "\n";
	if ( ! $ok ) {
		$fail++;
	}
}

// Проверка утилит.
echo "\nВкладки: " . implode( ', ', array_keys( WPPA_Template::tabs() ) ) . "\n";
echo 'URL вкладки: ' . WPPA_Plugin::tab_url( 'security' ) . "\n";
echo 'Сообщение: ' . print_r( WPPA_Notices::registry()['profile_saved'], true );

echo $fail ? "\nПРОБЛЕМ: {$fail}\n" : "\nВсе шаблоны отрисованы без ошибок\n";
exit( $fail ? 1 : 0 );
