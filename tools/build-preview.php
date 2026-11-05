<?php
/**
 * Генератор статического превью кабинета (демо-данные, без WordPress).
 *
 * Запуск: php tools/build-preview.php
 *
 * @package WP_Personal_Account
 */

require __DIR__ . '/wp-stubs.php';

$base = dirname( __DIR__ ) . '/';

define( 'WPPA_VERSION', '2.0.0' );
define( 'WPPA_FILE', $base . 'wp-personal-account.php' );
define( 'WPPA_PATH', $base );
define( 'WPPA_URL', 'https://example.com/wp-content/plugins/wp-personal-account/' );

require $base . 'includes/class-wppa-time.php';
require $base . 'includes/class-wppa-plugin.php';
require $base . 'includes/class-wppa-fields.php';
require $base . 'includes/class-wppa-password.php';
require $base . 'includes/class-wppa-export.php';
require $base . 'includes/class-wppa-export-folder.php';
require $base . 'includes/class-wppa-smb.php';
require $base . 'includes/class-wppa-curator-files.php';
require $base . 'includes/class-wppa-team-files-export.php';
require $base . 'includes/class-wppa-mailing.php';
require $base . 'includes/class-wppa-mailing-files.php';
require $base . 'includes/class-wppa-olympiad.php';
require $base . 'includes/class-wppa-reset.php';
require $base . 'includes/class-wppa-link-button.php';
require $base . 'includes/class-wppa-template.php';
require $base . 'includes/class-wppa-notices.php';
require $base . 'includes/class-wppa-account.php';
require $base . 'includes/class-wppa-profile.php';
require $base . 'includes/class-wppa-teams.php';
require $base . 'includes/class-wppa-builder.php';
require $base . 'includes/class-wppa-shortcodes.php';
require $base . 'includes/class-wppa-widget.php';
require $base . 'includes/class-wppa-blocks.php';

$GLOBALS['wppa_test']['users'] = array(
	7 => array( 'Иван Петров', 'email' => 'ivan@example.com' ),
	8 => array( 'Мария Соколова', 'email' => 'maria@example.com' ),
	9 => array( 'Пётр Сидоров', 'email' => 'petr@example.com' ),
	12 => array( 'Ольга Куратова', 'email' => 'olga@example.com' ),
);

// Демо-персональные данные пользователя.
update_user_meta( 7, '_wppa_phone', '+7 913 555-21-08' );
update_user_meta( 7, '_wppa_city', 'Красноярск' );
update_user_meta( 7, '_wppa_company', 'ООО «Вектор»' );
update_user_meta( 7, '_wppa_position', 'Руководитель проектов' );
update_user_meta( 7, '_wppa_patronymic', 'Сергеевич' );
update_user_meta( 7, '_wppa_birthdate', '1990-04-12' );
update_user_meta( 7, '_wppa_telegram', '@ivan_petrov' );

$user = new WP_User();
$tabs = WPPA_Template::all_tabs();

// Демо-команда: капитан — Иван, участники — Мария и Пётр.
$demo_team = WPPA_Teams::create( 'Студия «Вектор»', 7 );
if ( ! is_wp_error( $demo_team ) ) {
	// Мария — наставник команды.
	update_user_meta( 8, WPPA_Teams::META_TEAM, $demo_team );
	update_user_meta( 9, WPPA_Teams::META_TEAM, $demo_team );
	update_user_meta( 8, WPPA_Teams::META_ROLE, 'mentor' );
	update_user_meta( 9, WPPA_Teams::META_ROLE, 'member' );
}

// Отметка «прошла во второй этап»: ставит куратор, видят все участники команды.
WPPA_Teams::set_curator( 12, true );
WPPA_Teams::set_stage2( $demo_team, true, 12 );
WPPA_Teams::set_curator( 12, false );

function demo_section( $title, $note, $html ) {
	return '<section class="demo-block"><div class="demo-block__head"><h2>' . htmlspecialchars( $title ) . '</h2>'
		. ( $note ? '<p>' . htmlspecialchars( $note ) . '</p>' : '' ) . '</div>' . $html . '</section>';
}

// Демонстрация конструктора: свой HTML-блок в «Обзоре».
update_option(
	WPPA_Builder::OPTION,
	array(
		'dashboard' => array(
			array( 'type' => 'block', 'id' => 'greeting', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
			array( 'type' => 'block', 'id' => 'profile-summary', 'enabled' => 1, 'title' => '', 'audience' => 'all', 'html' => '' ),
			array( 'type' => 'custom', 'id' => 'custom-0', 'enabled' => 1, 'title' => 'Памятка', 'audience' => 'all', 'html' => '<p>Профиль и команда заполнены — значит, заказы и документы придут на верный адрес.</p><ul><li>Проверьте email в разделе «Безопасность»</li><li>Пригласите коллег во вкладке «Команда»</li></ul>' ),
			array( 'type' => 'block', 'id' => 'link-button', 'enabled' => 1, 'title' => 'Трансляция', 'audience' => 'all', 'html' => '' ),
			array( 'type' => 'block', 'id' => 'profile-summary', 'enabled' => 0, 'title' => '', 'audience' => 'all', 'html' => '' ),
		),
	)
);

// Демонстрация интеграции с плагином олимпиад: поддельные строки попыток.
global $wpdb;

if ( $wpdb instanceof WPPA_Test_WPDB ) {
	$wpdb->data = array(
		'var'      => $wpdb->prefix . 'olympiad_attempts',
		'attempts' => array(
			(object) array( 'id' => 1, 'quiz_id' => 10, 'score' => 8, 'max_score' => 10, 'percentage' => '80.00', 'status' => 'completed', 'submitted_at' => '2026-10-28 13:51:04', 'title' => 'Математика' ),
			(object) array( 'id' => 2, 'quiz_id' => 11, 'score' => 15, 'max_score' => 20, 'percentage' => '75.00', 'status' => 'completed', 'submitted_at' => '2026-10-29 10:00:00', 'title' => 'Физика' ),
			(object) array( 'id' => 3, 'quiz_id' => 13, 'score' => 4, 'max_score' => 10, 'percentage' => '40.00', 'status' => 'needs_review', 'submitted_at' => '2026-10-30 09:00:00', 'title' => 'Химия' ),
			(object) array( 'id' => 4, 'quiz_id' => 14, 'score' => 0, 'max_score' => 10, 'percentage' => '0.00', 'status' => 'expired', 'submitted_at' => '2026-10-30 10:00:00', 'title' => 'История' ),
		),
	);

	WPPA_Olympiad::flush();
}

$sections  = demo_section(
	'Кабинет целиком (активна вкладка «Обзор»)',
	'Шапка с аватаром и кнопкой выхода, навигация по вкладкам, контент активной вкладки',
	WPPA_Template::get( 'account', array( 'user' => $user, 'tabs' => $tabs, 'current_tab' => 'dashboard' ) )
);

$sections .= demo_section(
	'Профиль',
	'Вкладка profile: имя, фамилия, отображаемое имя, «о себе»',
	WPPA_Template::get( 'tab-profile', array( 'user' => $user, 'tabs' => $tabs ) )
		. WPPA_Template::get( 'notice', array( 'type' => 'success', 'text' => 'Профиль обновлён.' ) )
);

$sections .= demo_section(
	'Команда (капитан)',
	'Вкладка team: состав с ролями, назначение наставника, ссылка-приглашение, приглашение по email, переименование, роспуск',
	WPPA_Template::get( 'tab-team', array( 'user' => $user, 'tabs' => $tabs ) )
);

// Вид глазами наставника: приглашения есть, переименования и роспуска нет.
$mentor_user = get_userdata( 8 );
$sections   .= demo_section(
	'Команда (наставник)',
	'Так команду видит наставник: может приглашать и исключать участников, но не переименовать и не распустить команду',
	WPPA_Template::get( 'tab-team', array( 'user' => $mentor_user, 'tabs' => $tabs ) )
);

// Команда без наставника: команда считается несформированной.
$wppa_mentor_snapshot = isset( $GLOBALS['wppa_test']['meta'] ) ? $GLOBALS['wppa_test']['meta'] : array();

foreach ( (array) $wppa_mentor_snapshot as $wppa_uid => $wppa_meta ) {
	if ( isset( $wppa_meta['_wppa_team_role'] ) && 'mentor' === $wppa_meta['_wppa_team_role'] ) {
		$GLOBALS['wppa_test']['meta'][ $wppa_uid ]['_wppa_team_role'] = 'member';
	}
}

$sections .= demo_section(
	'Команда без наставника',
	'Настройка «Требовать наставника» включена: минимум набран, но команда считается несформированной',
	WPPA_Template::get( 'tab-team', array( 'user' => $user, 'tabs' => $tabs ) )
);

$GLOBALS['wppa_test']['meta'] = $wppa_mentor_snapshot;

// Куратор: список всех команд и управление чужой командой.
// Куратор не состоит в командах: его дело — надзор, а не участие.
$curator_user = get_userdata( 12 );
WPPA_Teams::set_curator( 12, true );

$sections .= demo_section(
	'Куратор: все команды',
	'Так «Команду» видит куратор — статус на уровне сайта: список всех команд с составом и состоянием, у каждой кнопка «Добавить файлы» — она открывает файлы команды на вкладке «Файлы»',
	WPPA_Template::get( 'tab-team', array( 'user' => $curator_user, 'tabs' => $tabs ) )
);

$_GET['wppa_team'] = (int) $demo_team;

// Демо-файлы куратора: два документа, чтобы карточка была не пустой.
$wppa_demo_uploads = array(
	array( 'Положение об олимпиаде.pdf', str_repeat( 'a', 240 ) ),
	array( 'Критерии оценивания.docx', str_repeat( 'b', 96 ) ),
);

foreach ( $wppa_demo_uploads as $wppa_demo_upload ) {
	$wppa_demo_path = sys_get_temp_dir() . '/wppa-preview-' . md5( $wppa_demo_upload[0] );

	file_put_contents( $wppa_demo_path, $wppa_demo_upload[1] );
	WPPA_Curator_Files::store( $demo_team, $wppa_demo_path, $wppa_demo_upload[0], 12 );
}

// Встречный файл от наставника — чтобы в разделе «Файлы» были обе полки.
$wppa_mentor_upload = sys_get_temp_dir() . '/wppa-preview-mentor';

file_put_contents( $wppa_mentor_upload, str_repeat( 'c', 128 ) );
WPPA_Curator_Files::store( $demo_team, $wppa_mentor_upload, 'Список команды.pdf', 8 );

$sections .= demo_section(
	'Куратор: открытая команда',
	'Куратор открыл команду «Студия «Вектор»»: карточка команды со счётчиками и файлы для наставника. Состава, приглашений, переименования, роспуска и выхода у куратора нет — это права капитана и наставника команды',
	WPPA_Template::get( 'tab-team', array( 'user' => $curator_user, 'tabs' => $tabs ) )
);

unset( $_GET['wppa_team'] );

$sections .= demo_section(
	'Куратор: раздел «Файлы»',
	'Отдельная вкладка «Файлы» у куратора: список команд и число файлов, кнопка «Открыть» ведёт к документам выбранной команды',
	WPPA_Template::get( 'tab-files', array( 'user' => $curator_user, 'tabs' => $tabs ) )
);

$_GET['wppa_team'] = (int) $demo_team;

$sections .= demo_section(
	'Куратор: файлы выбранной команды',
	'Та же вкладка с открытой командой: две полки — файлы от куратора и файлы от наставника; куратор прикрепляет документы прямо здесь и удаляет любые',
	WPPA_Template::get( 'tab-files', array( 'user' => $curator_user, 'tabs' => $tabs ) )
);

unset( $_GET['wppa_team'] );

// Участник команды: вкладка «Файлы» появляется, когда куратор что-то прислал.
$member_user = get_userdata( 7 );

$sections .= demo_section(
	'Участник: файлы от куратора',
	'Участник команды видит вкладку «Файлы» только тогда, когда куратор прислал его команде документы. Внутри — одна полка «Файлы от куратора» со ссылками на скачивание: без формы загрузки и без файлов наставника',
	WPPA_Template::get( 'tab-files', array( 'user' => $member_user, 'tabs' => $tabs ) )
);

$sections .= demo_section(
	'Участник: файлы куратора в карточке команды',
	'То же самое видно и на вкладке «Команда»: карточка «Файлы от куратора» со ссылками на скачивание — участнику не нужно искать отдельный раздел, файлы видят все участники команды',
	WPPA_Template::get( 'tab-team', array( 'user' => $member_user, 'tabs' => $tabs ) )
);

$sections .= demo_section(
	'Наставник: раздел «Файлы»',
	'Отдельная вкладка «Файлы» у наставника команды: документы от куратора со ссылками на скачивание и своя полка — наставник прикрепляет файлы для куратора и может удалить только свой',
	WPPA_Template::get( 'tab-files', array( 'user' => $mentor_user, 'tabs' => $tabs ) )
);

unset( $_GET['wppa_team'] );

// Наставник до второго этапа: блока файлов наставника нет вовсе.
WPPA_Teams::set_curator( 12, true );
WPPA_Teams::set_stage2( $demo_team, false, 12 );

$sections .= demo_section(
	'Наставник: файлы до второго этапа',
	'Пока команда не прошла во второй этап, блока «Файлы от наставника» нет совсем: наставник видит и скачивает документы куратора, а форма прикрепления и подсказка не показываются',
	WPPA_Template::get( 'tab-files', array( 'user' => $mentor_user, 'tabs' => $tabs ) )
);

WPPA_Teams::set_stage2( $demo_team, true, 12 );

// Куратор: главная страница с очередью рассылки участникам второго этапа.
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

// Вторая рассылка — о прикреплённых файлах: те же люди, отдельный шаблон и очередь.
WPPA_Mailing_Files::save(
	array(
		'subject'  => 'Для команды «{команда}» прикреплены файлы',
		'body'     => "Здравствуйте, {имя}!\n\nКуратор прикрепил документы для команды «{команда}» — посмотреть и скачать их можно в личном кабинете, во вкладке «Файлы».\n\n— {сайт}",
		'interval' => 10,
		'batch'    => 2,
	)
);
WPPA_Mailing_Files::start();
WPPA_Mailing_Files::process_due();

$sections .= demo_section(
	'Куратор: главная страница и рассылки',
	'Куратор видит состояние очередей обеих рассылок — «второй этап» и «прикреплённые файлы»: сколько отправлено, сколько осталось, когда следующее письмо, и ссылку на раздел «Рассылка»',
	WPPA_Template::get( 'tab-dashboard', array( 'user' => $curator_user, 'tabs' => $tabs ) )
);

// Новая вкладка «Рассылка»: куратор управляет письмом сам, без админки.
$GLOBALS['wppa_test']['current_user'] = 12;

$sections .= demo_section(
	'Куратор: раздел «Рассылка»',
	'Отдельная вкладка у куратора: тема и текст письма с подстановками, предпросмотр, интервал и порция писем, постановка в очередь, досрочная отправка остатка, отмена рассылки и журнал',
	WPPA_Template::get( 'tab-mailing', array( 'user' => $curator_user, 'tabs' => WPPA_Template::tabs() ) )
);

WPPA_Mailing::cancel();
WPPA_Mailing_Files::cancel();

// Снятие отметки: письма уходят только тем командам, у которых отметка осталась.
$wppa_notice_registry = WPPA_Notices::registry();

$sections .= demo_section(
	'Снятие отметки второго этапа',
	'Снятие отметки убирает письма команды из очереди рассылки. Если отметку сняли в течение минуты после установки, плагин считает нажатие случайным и отдельно сообщает, что письма не отправлялись',
	WPPA_Template::get(
		'notice',
		array(
			'type' => $wppa_notice_registry['stage2_removed_grace'][0],
			'text' => $wppa_notice_registry['stage2_removed_grace'][1],
		)
	)
	. WPPA_Template::get(
		'notice',
		array(
			'type' => $wppa_notice_registry['stage2_removed_mail'][0],
			'text' => $wppa_notice_registry['stage2_removed_mail'][1],
		)
	)
);

WPPA_Teams::set_curator( 12, false );


$sections .= demo_section(
	'Безопасность',
	'Вкладка security: смена пароля, смена email с подтверждением, выход',
	WPPA_Template::get( 'tab-security', array( 'user' => $user, 'tabs' => $tabs ) )
);

// Виджет для главной страницы.
$GLOBALS['wppa_test']['logged_in'] = false;

ob_start();
$demo_widget = new WPPA_Auth_Widget();
$demo_widget->widget(
	array(
		'before_widget' => '<div class="widget widget_wppa_auth">',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
		'after_widget'  => '</div>',
	),
	array( 'title' => 'Личный кабинет', 'mode' => 'both' )
);
$widget_demo = ob_get_clean();
$GLOBALS['wppa_test']['logged_in'] = true;

$GLOBALS['wppa_test']['logged_in'] = false;

$sections .= demo_section(
	'Отдельные блоки: «Войти» и «Регистрация»',
	'Блоки wppa/login и wppa/register: кнопки (display="button") для шапки и формы — независимо друг от друга',
	'<div class="demo-two">'
		. '<div>' . WPPA_Blocks::render( array( 'mode' => 'login', 'display' => 'button' ) )
		. WPPA_Blocks::render( array( 'mode' => 'login', 'showTitle' => true, 'title' => 'Вход', 'hideOther' => true ) ) . '</div>'
		. '<div>' . WPPA_Blocks::render( array( 'mode' => 'register', 'display' => 'button', 'buttonText' => 'Создать аккаунт' ) )
		. WPPA_Blocks::render( array( 'mode' => 'register', 'showTitle' => true, 'title' => 'Регистрация', 'hideOther' => true ) ) . '</div>'
		. '</div>'
);

$sections .= demo_section(
	'Настройка внешнего вида блока',
	'Панель «Внешний вид»: оформление (без оформления / карточка / рамка), ширина, размер кнопок, акцент и радиус',
	'<div class="demo-three">'
		. '<div>' . WPPA_Blocks::render( array( 'mode' => 'login', 'appearance' => 'card', 'maxWidth' => 'small', 'showTitle' => true, 'title' => 'Карточка', 'hideOther' => true ) ) . '</div>'
		. '<div>' . WPPA_Blocks::render( array( 'mode' => 'login', 'appearance' => 'bordered', 'maxWidth' => 'small', 'showTitle' => true, 'title' => 'Рамка', 'hideOther' => true, 'accentColor' => '#16a34a' ) ) . '</div>'
		. '<div>' . WPPA_Blocks::render( array( 'mode' => 'login', 'appearance' => 'plain', 'maxWidth' => 'small', 'showTitle' => true, 'title' => 'Без оформления', 'hideOther' => true, 'buttonFullWidth' => true, 'buttonSize' => 'lg' ) ) . '</div>'
		. '</div>'
		. '<div class="demo-two" style="margin-top:24px;">'
		. '<div>' . WPPA_Blocks::render( array( 'mode' => 'login', 'display' => 'button', 'buttonSize' => 'lg', 'buttonText' => 'Кнопка: крупная' ) ) . '</div>'
		. '<div>' . WPPA_Blocks::render( array( 'mode' => 'register', 'display' => 'button', 'buttonStyle' => 'ghost', 'buttonSize' => 'lg', 'buttonText' => 'Кнопка: обводка' ) ) . '</div>'
		. '</div>'
);

$GLOBALS['wppa_test']['logged_in'] = true;

$sections .= demo_section(
	'Виджет на главной странице',
	'Виджет «Личный кабинет: вход и регистрация»: вкладки «Вход» и «Регистрация» в одном компактном блоке',
	'<div class="demo-sidebar"><div class="widget-title" style="margin:0 0 10px;font-size:1rem;font-weight:650;">Личный кабинет</div>'
		. str_replace( array( '<h2 class="widget-title">Личный кабинет</h2>' ), '', $widget_demo )
		. '</div>'
);

$sections .= demo_section(
	'Экран гостя',
	'Страница кабинета для неавторизованных: кнопка «Регистрация» под формой входа',
	'<div class="demo-two">'
		. '<div>' . WPPA_Template::get( 'guest', array( 'view' => 'login', 'registration' => true ) ) . '</div>'
		. '<div>' . WPPA_Template::get( 'guest', array( 'view' => 'register', 'registration' => true ) ) . '</div>'
		. '</div>'
);

$sections .= demo_section(
	'Вход и регистрация',
	'Формы [wppa_login] и [wppa_register] с кнопками перехода',
	'<div class="demo-two">'
		. '<div>' . WPPA_Template::get( 'login', array( 'redirect' => '', 'title' => '' ) ) . '</div>'
		. '<div>' . WPPA_Template::get( 'register', array( 'title' => '' ) ) . '</div>'
		. '</div>'
);

// Кнопка со ссылкой: четыре состояния в одном ряду.
$link_states   = '';
$link_variants = array(
	'Ссылка открыта'      => array( 'url' => 'https://example.com/stream', 'text' => 'Перейти к трансляции', 'start' => '', 'end' => '', 'new_tab' => 1, 'require_team' => 0, 'note' => "Вход по логину и паролю от платформы.\nПароль выдаёт наставник." ),
	'Ждём состав команды' => array( 'url' => 'https://example.com/stream', 'text' => 'Перейти к трансляции', 'start' => '', 'end' => '', 'new_tab' => 1, 'require_team' => 1 ),
	'До открытия'         => array( 'url' => 'https://example.com/stream', 'text' => 'Перейти к трансляции', 'start' => gmdate( 'Y-m-d\TH:i', time() + 7200 ), 'end' => '', 'new_tab' => 1, 'require_team' => 0 ),
	'Срок истёк'          => array( 'url' => 'https://example.com/stream', 'text' => 'Перейти к трансляции', 'start' => '', 'end' => gmdate( 'Y-m-d\TH:i', time() - 3600 ), 'new_tab' => 1, 'require_team' => 0, 'note' => 'Ссылка работала до окончания приёма работ.' ),
);

$wppa_settings_snapshot = get_option( 'wppa_settings', array() );
$wppa_meta_snapshot     = isset( $GLOBALS['wppa_test']['meta'] ) ? $GLOBALS['wppa_test']['meta'] : array();
$wppa_posts_snapshot    = isset( $GLOBALS['wppa_test']['posts'] ) ? $GLOBALS['wppa_test']['posts'] : array();
$wppa_postmeta_snapshot = isset( $GLOBALS['wppa_test']['postmeta'] ) ? $GLOBALS['wppa_test']['postmeta'] : array();
$wppa_user_id           = get_current_user_id();

foreach ( $link_variants as $link_label => $link_config ) {
	update_option( 'wppa_settings', array_merge( (array) $wppa_settings_snapshot, array( 'link_button' => $link_config ) ) );

	// Для состояния «ждём состав» показываем команду из одного человека.
	$GLOBALS['wppa_test']['meta']     = $wppa_meta_snapshot;
	$GLOBALS['wppa_test']['posts']    = $wppa_posts_snapshot;
	$GLOBALS['wppa_test']['postmeta'] = $wppa_postmeta_snapshot;

	if ( ! empty( $link_config['require_team'] ) ) {
		// Команда из одного человека при минимуме 2 — «ждём состав».
		$GLOBALS['wppa_test']['meta'] = array(
			$wppa_user_id => array(
				'_wppa_team_id'   => 777,
				'_wppa_team_role' => 'owner',
			),
		);

		$GLOBALS['wppa_test']['posts'][777]                        = new WP_Post(
			array(
				'ID'        => 777,
				'post_type' => WPPA_Teams::CPT,
				'post_title' => 'Команда «Вектор»',
			)
		);
		$GLOBALS['wppa_test']['postmeta'][777]['_wppa_team_min']   = 2;
		$GLOBALS['wppa_test']['postmeta'][777]['_wppa_team_limit'] = 10;
	}

	$link_states .= '<div><p class="demo-label">' . htmlspecialchars( $link_label ) . '</p>'
		. WPPA_Builder::render_block(
			array( 'type' => 'block', 'id' => 'link-button', 'enabled' => 1, 'title' => 'Трансляция', 'audience' => 'all', 'html' => '' ),
			array()
		)
		. '</div>';
}

update_option( 'wppa_settings', $wppa_settings_snapshot );
$GLOBALS['wppa_test']['meta']     = $wppa_meta_snapshot;
$GLOBALS['wppa_test']['posts']    = $wppa_posts_snapshot;
$GLOBALS['wppa_test']['postmeta'] = $wppa_postmeta_snapshot;

$sections .= demo_section(
	'Кнопка со ссылкой',
	'Внешняя ссылка с окном дат и условием «команда сформирована»: активна, ждёт состав, ждёт открытия с таймером, закрыта',
	'<div class="demo-three">' . $link_states . '</div>'
);

// Регистрация по таймеру: форма закрыта, кнопка неактивна с отсчётом.
$wppa_settings_snapshot = get_option( 'wppa_settings', array() );

update_option(
	'wppa_settings',
	array_merge(
		(array) $wppa_settings_snapshot,
		array(
			'registration_enabled' => 1,
			'registration_start'   => gmdate( 'Y-m-d\TH:i', time() + 7200 ),
			'registration_end'     => '',
		)
	)
);

$sections .= demo_section(
	'Регистрация по таймеру',
	'Окно приёма заявок ещё не открыто: вместо формы — заглушка с датой и отсчётом, кнопка «Регистрация» неактивна',
	'<div class="demo-two">'
		. '<div>' . WPPA_Template::get( 'register', array( 'title' => '' ) ) . '</div>'
		. '<div>'
			. WPPA_Template::get( 'login', array( 'title' => '', 'redirect' => '' ) )
			. '<div style="margin-top:18px">' . WPPA_Blocks::render( array( 'mode' => 'register', 'display' => 'button' ) ) . '</div>'
		. '</div>'
		. '</div>'
);

update_option( 'wppa_settings', $wppa_settings_snapshot );

// Восстановление пароля: заявка и установка нового пароля.
$sections .= demo_section(
	'Восстановление пароля',
	'Свои формы вместо служебной страницы WordPress: заявка по логину или email и установка нового пароля по ссылке из письма',
	'<div class="demo-two">'
		. '<div>' . WPPA_Template::get( 'lost-password', array() ) . '</div>'
		. '<div>' . WPPA_Template::get( 'reset-password', array( 'key' => 'test-reset-key', 'login' => 'ivan' ) ) . '</div>'
		. '</div>'
);

$css = file_get_contents( $base . 'assets/css/account.css' );

$html = '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>WP Personal Account — превью интерфейса</title>
<style>
' . $css . '
body {
	margin: 0;
	padding: 32px 20px 64px;
	background: #eef2f7;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	color: #0f172a;
}
.demo-top { max-width: 960px; margin: 0 auto 28px; }
.demo-top h1 { margin: 0 0 8px; font-size: 1.6rem; }
.demo-top p { margin: 0; color: #64748b; }
.demo-block { max-width: 960px; margin: 0 auto 40px; }
.demo-block__head { margin-bottom: 16px; }
.demo-block__head h2 { margin: 0 0 4px; font-size: 1.1rem; text-transform: uppercase; letter-spacing: .06em; color: #475569; }
.demo-block__head p { margin: 0; font-size: .88rem; color: #7c8798; }
.demo-block > .wppa, .demo-block > .demo-two { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 22px; box-shadow: 0 10px 30px rgba(15,23,42,.06); }
.demo-two { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 28px; }
.demo-block > .demo-three { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 22px; box-shadow: 0 10px 30px rgba(15,23,42,.06); }
.demo-three { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; align-items: start; }
.demo-sidebar { max-width: 340px; margin: 0 auto; }
.demo-label { margin: 0 0 8px; font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; }
.demo-block > .demo-sidebar { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 22px; box-shadow: 0 10px 30px rgba(15,23,42,.06); }
</style>
</head>
<body>
<div class="demo-top">
	<h1>Личный кабинет — превью интерфейса</h1>
	<p>Демонстрация шаблонов плагина WP Personal Account: обзор, профиль, безопасность, вход и регистрация.</p>
</div>
' . $sections . '
</body>
</html>';

file_put_contents( $base . 'preview.html', $html );
echo 'preview.html: ' . strlen( $html ) . " байт\n";
