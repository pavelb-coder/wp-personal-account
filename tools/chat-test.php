<?php
/** Проверки чата на заглушках: это не замена интеграционному тесту WordPress. */
require __DIR__ . '/wp-stubs.php';
$base = dirname( __DIR__ ) . '/';
define( 'WPPA_VERSION', 'test' );
define( 'WPPA_FILE', $base . 'wp-personal-account.php' );
define( 'WPPA_PATH', $base );
define( 'WPPA_URL', 'https://example.com/wp-content/plugins/wp-personal-account/' );
foreach ( array( 'plugin', 'template', 'theme', 'notices', 'teams', 'chat', 'builder' ) as $class ) { require $base . 'includes/class-wppa-' . $class . '.php'; }
$fail = 0; $count = 0;
function chat_assert( $condition, $label ) { global $fail, $count; ++$count; echo ( $condition ? 'OK   ' : 'FAIL ' ) . $label . "\n"; if ( ! $condition ) { ++$fail; } }
function chat_ajax( $method, $user, $mentor, $nonce, $text = '' ) {
	$GLOBALS['wppa_test']['current_user'] = $user; $GLOBALS['wppa_test']['logged_in'] = $user > 0;
	$_POST = array( 'mentor_id' => $mentor, 'nonce' => $nonce, 'message' => $text );
	try { call_user_func( array( 'WPPA_Chat', $method ) ); } catch ( WPPA_Stub_Json $e ) { return $e; }
	throw new RuntimeException( 'AJAX handler did not return JSON' );
}
function chat_html( $id, $mentor = 0, $page = 1 ) {
	$GLOBALS['wppa_test']['current_user'] = $id; $GLOBALS['wppa_test']['logged_in'] = $id > 0;
	$_GET = array( 'wppa_chat_mentor' => $mentor, 'wppa_chat_page' => $page, 'wppa_chat_view_nonce' => wp_create_nonce( 'wppa_chat_view_' . (int) $mentor ) );
	$user = new WP_User( $id ); $block_title = ''; ob_start(); include WPPA_PATH . 'templates/blocks/chat-widget.php'; return ob_get_clean();
}
$GLOBALS['wppa_test']['users'] = array( 8 => array( 'Наставник один', 'email' => 'one@example.com' ), 9 => array( 'Наставник два', 'email' => 'two@example.com' ), 11 => array( 'Участник', 'email' => 'member@example.com' ), 12 => array( 'Куратор один', 'email' => 'curator@example.com' ), 13 => array( 'Куратор два', 'email' => 'curator2@example.com' ) );
$team1 = wp_insert_post( array( 'post_type' => WPPA_Teams::CPT ) );
$team2 = wp_insert_post( array( 'post_type' => WPPA_Teams::CPT ) );
foreach ( array( 8, 9 ) as $i => $id ) { update_user_meta( $id, WPPA_Teams::META_TEAM, $i ? $team2 : $team1 ); update_user_meta( $id, WPPA_Teams::META_ROLE, 'mentor' ); }
update_user_meta( 11, WPPA_Teams::META_TEAM, $team1 ); update_user_meta( 11, WPPA_Teams::META_ROLE, 'member' );
WPPA_Teams::set_curator( 12, true ); WPPA_Teams::set_curator( 13, true );
WPPA_Chat::register_post_type();
chat_assert( empty( $GLOBALS['wppa_test']['cpt'][ WPPA_Chat::CPT ]['public'] ) && empty( $GLOBALS['wppa_test']['cpt'][ WPPA_Chat::CPT ]['show_in_rest'] ) && empty( $GLOBALS['wppa_test']['cpt'][ WPPA_Chat::CPT ]['show_ui'] ) && empty( $GLOBALS['wppa_test']['cpt'][ WPPA_Chat::CPT ]['can_export'] ) && 'wppa_chat_message' === $GLOBALS['wppa_test']['cpt'][ WPPA_Chat::CPT ]['capability_type'][0], 'скрытый CPT без REST и интерфейса' );
chat_assert( WPPA_Chat::can_view( 8, 8 ) && WPPA_Chat::can_view( 8, 12 ) && WPPA_Chat::can_view( 8, 13 ), 'наставник и оба куратора имеют доступ' );
chat_assert( ! WPPA_Chat::can_view( 8, 9 ) && ! WPPA_Chat::can_view( 8, 11 ) && ! WPPA_Chat::can_view( 8, 0 ) && ! WPPA_Chat::can_view( 9000, 12 ), 'посторонние и вымышленные наставники отклоняются' );
$nonce = wp_create_nonce( 'wppa_chat_ajax' );
$GLOBALS['wppa_test']['mail'] = array();
chat_assert( 'chat_sent' === WPPA_Chat::send( 8, 8, "Вопрос: \\\"цитата\\\" \\ путь\nвторая строка" ), 'сообщение наставника' );
chat_assert( 'chat_sent' === WPPA_Chat::send( 8, 12, 'Ответ первого куратора' ) && 'chat_sent' === WPPA_Chat::send( 8, 13, 'Ответ второго куратора' ), 'каждый куратор отвечает независимо' );
chat_assert( 'chat_sent' === WPPA_Chat::send( 9, 9, 'секрет второго' ), 'отдельный чат другого наставника' );
// Счётчики независимы для кураторов, учитывают только реплики наставника.
chat_assert( WPPA_Chat::unread( 12 ) === array( 8 => 1, 9 => 1 ) && WPPA_Chat::unread( 13 ) === array( 8 => 1, 9 => 1 ), 'каждый куратор видит новые сообщения каждого наставника' );
$GLOBALS['wppa_test']['current_user'] = 12; $_GET = array();
$account = WPPA_Template::get( 'account', array( 'user' => new WP_User( 12 ), 'tabs' => array( 'messages' => 'Сообщения' ), 'current_tab' => 'messages' ) );
chat_assert( false !== strpos( $account, 'data-wppa-unread-badge' ) && false !== strpos( $account, 'Непрочитанных сообщений: 2' ), 'куратор видит количество у вкладки кабинета' );
$index = chat_html( 12 );
chat_assert( false !== strpos( $index, 'Новые сообщения' ) && false !== strpos( $index, 'Наставник один' ) && false !== strpos( $index, 'Наставник два' ) && false === strpos( $index, 'секрет второго' ), 'список непрочитанных содержит ссылки без текста' );
$r = chat_ajax( 'ajax_unread', 11, 8, $nonce ); chat_assert( ! $r->payload['success'] && 403 === $r->status, 'участник не может получать список' );
$r = chat_ajax( 'ajax_unread', 12, 8, 'bad' ); chat_assert( ! $r->payload['success'] && 403 === $r->status, 'nonce списка проверяется' );
$r = chat_ajax( 'ajax_unread', 12, 8, $nonce ); chat_assert( $r->payload['success'] && 2 === $r->payload['data']['total'], 'AJAX возвращает общий счётчик куратора' );
$snapshot = WPPA_Chat::messages( 8, 12 )['items'];
WPPA_Chat::mark_read( 8, 12, $snapshot );
chat_assert( ! isset( WPPA_Chat::unread( 12 )[8] ) && isset( WPPA_Chat::unread( 13 )[8] ), 'прочтение одним куратором не затрагивает другого' );
$GLOBALS['wppa_test']['blog_id'] = 2; chat_assert( isset( WPPA_Chat::unread( 12 )[8] ), 'отметки разных сайтов multisite не смешиваются' ); $GLOBALS['wppa_test']['blog_id'] = 1;
WPPA_Chat::send( 8, 8, 'новое после открытия' );
$last_ids = array_keys( $GLOBALS['wppa_test']['posts'] ); $new_id = end( $last_ids );
WPPA_Chat::mark_read( 8, 12, $snapshot );
chat_assert( 1 === WPPA_Chat::unread( 12 )[8], 'новое сообщение после снимка не отмечается прочитанным' );
chat_html( 12, 8, 2 ); chat_assert( 1 === WPPA_Chat::unread( 12 )[8], 'просмотр старой страницы не скрывает новые сообщения' );
$GLOBALS['wppa_test']['current_user'] = 12; $_GET = array( 'wppa_chat_mentor' => 8, 'wppa_chat_view_nonce' => 'bad' );
$user = new WP_User( 12 ); $block_title = ''; ob_start(); include WPPA_PATH . 'templates/blocks/chat-widget.php'; ob_end_clean();
chat_assert( 1 === WPPA_Chat::unread( 12 )[8], 'поддельный переход не отмечает переписку прочитанной' );
chat_html( 12, 8, 1 ); chat_assert( ! isset( WPPA_Chat::unread( 12 )[8] ) && isset( WPPA_Chat::unread( 13 )[8] ), 'открытие последней страницы отмечает её для конкретного куратора' );
WPPA_Chat::mark_read( 8, 12, $snapshot ); chat_assert( ! isset( WPPA_Chat::unread( 12 )[8] ), 'запаздывающий запрос не откатывает отметку прочтения' );
wp_delete_post( $new_id, true );
$r = chat_ajax( 'ajax_read', 13, 8, $nonce ); chat_assert( $r->payload['success'] && ! isset( WPPA_Chat::unread( 13 )[8] ), 'автообновление открытого чата отмечает сообщения прочитанными' );
chat_assert( ! WPPA_Chat::unread( 11 ), 'посторонним счётчики недоступны' );
// После начальной переписки письма раздельно отправлены всем кураторам и наставнику.
$mail = $GLOBALS['wppa_test']['mail'];
chat_assert( count( $mail ) >= 6 && 'curator@example.com' === $mail[0]['to'] && 'curator2@example.com' === $mail[1]['to'] && 'one@example.com' === $mail[2]['to'] && 'one@example.com' === $mail[3]['to'], 'email: наставнику и каждому куратору индивидуально' );
chat_assert( false === strpos( $mail[0]['message'], 'Вопрос:' ) && false === strpos( $mail[2]['message'], 'Ответ первого' ) && false !== strpos( $mail[0]['message'], 'wppa_chat_mentor=8' ), 'email: без текста переписки, ссылка в правильный диалог' );
$one = WPPA_Chat::messages( 8, 8 );
chat_assert( 3 === count( $one['items'] ) && "Вопрос: \\\"цитата\\\" \\ путь\nвторая строка" === $one['items'][0]->post_content, 'кавычки, переносы и обратные слэши сохранены' );
chat_assert( 0 === count( WPPA_Chat::messages( 8, 9 )['items'] ) && 'chat_forbidden' === WPPA_Chat::send( 8, 11, 'чужой текст' ), 'доступ к данным и записи проверяется на сервере' );
chat_assert( 'chat_empty' === WPPA_Chat::send( 8, 8, '  ' ) && 'chat_too_long' === WPPA_Chat::send( 8, 8, str_repeat( 'Я', 2001 ) ), 'пустой и слишком длинный текст отвергнут' );
WPPA_Chat::send( 8, 8, '<script>alert(1)</script> & "тест"' );
$html = WPPA_Chat::messages_html( WPPA_Chat::messages( 8, 12 )['items'], 8, 12 );
chat_assert( false === strpos( $html, '<script>' ) && false !== strpos( $html, '&amp;' ) && false === strpos( $html, 'секрет второго' ), 'HTML экранирован, сообщения другого наставника не попадают' );
foreach ( array( 0, 9, 11 ) as $bad_user ) { $r = chat_ajax( 'ajax_read', $bad_user, 8, $nonce ); chat_assert( ! $r->payload['success'] && 403 === $r->status, 'AJAX чтение запрещено пользователю ' . $bad_user ); }
$r = chat_ajax( 'ajax_send', 9, 8, $nonce, 'подмена' ); chat_assert( ! $r->payload['success'] && 403 === $r->status, 'AJAX подмена mentor_id отклонена' );
$r = chat_ajax( 'ajax_send', 8, 8, 'bad', 'подделка' ); chat_assert( ! $r->payload['success'] && 403 === $r->status, 'AJAX nonce проверяется' );
$r = chat_ajax( 'ajax_send', 13, 8, $nonce, 'AJAX ответ' ); chat_assert( $r->payload['success'] && false !== strpos( $r->payload['data']['html'], 'AJAX ответ' ), 'AJAX ответ отправлен и возвращён' );
$r = chat_ajax( 'ajax_read', 8, 8, $nonce ); chat_assert( $r->payload['success'] && false !== strpos( $r->payload['data']['html'], 'AJAX ответ' ), 'наставник видит AJAX ответ' );
for ( $i = 1; $i <= 103; ++$i ) { WPPA_Chat::send( 8, 8, 'запись ' . $i ); }
$p1 = WPPA_Chat::messages( 8, 8, 1 ); $p2 = WPPA_Chat::messages( 8, 8, 2 ); $p3 = WPPA_Chat::messages( 8, 8, 3 );
chat_assert( 50 === count( $p1['items'] ) && 50 === count( $p2['items'] ) && 8 === count( $p3['items'] ) && $p1['more'] && $p2['more'] && ! $p3['more'], 'история доступна на трёх страницах без пропусков' );
chat_assert( count( array_unique( array_map( static function ( $p ) { return $p->ID; }, array_merge( $p1['items'], $p2['items'], $p3['items'] ) ) ) ) === 108, 'сообщения не перезаписываются и не дублируются между страницами' );
update_user_meta( 8, WPPA_Teams::META_ROLE, 'member' );
chat_assert( ! WPPA_Chat::can_view( 8, 8 ) && ! WPPA_Chat::can_view( 8, 12 ) && ! WPPA_Chat::messages( 8, 12 )['items'], 'после потери роли история закрыта для всех' );
update_user_meta( 8, WPPA_Teams::META_ROLE, 'mentor' );
chat_assert( 50 === count( WPPA_Chat::messages( 8, 8 )['items'] ), 'после возвращения роли история остаётся' );
WPPA_Teams::set_curator( 12, false ); chat_assert( ! WPPA_Chat::can_view( 8, 12 ), 'бывший куратор теряет доступ' ); WPPA_Teams::set_curator( 12, true );
$mentor_html = chat_html( 8, 9 ); $curator_html = chat_html( 12, 8 ); $member_html = chat_html( 11, 8 );
chat_assert( false !== strpos( $mentor_html, 'name="mentor_id" value="8"' ) && false === strpos( $mentor_html, 'секрет второго' ), 'GET другого наставника не меняет переписку наставника' );
chat_assert( false !== strpos( $curator_html, 'Наставник один' ) && false !== strpos( $curator_html, 'name="mentor_id" value="8"' ) && false !== strpos( $curator_html, '<noscript>' ), 'куратору доступны список и запасной интерфейс без JS' );
chat_assert( '' === $member_html, 'участник не получает разметку чата' );
$GLOBALS['wppa_test']['options']['wppa_settings']['enabled_tabs'][] = 'messages';
$GLOBALS['wppa_test']['current_user'] = 8; chat_assert( isset( WPPA_Template::tabs()['messages'] ), 'вкладка доступна наставнику' );
$GLOBALS['wppa_test']['current_user'] = 12; chat_assert( isset( WPPA_Template::tabs()['messages'] ), 'вкладка доступна куратору' );
$GLOBALS['wppa_test']['current_user'] = 11; chat_assert( ! isset( WPPA_Template::tabs()['messages'] ), 'вкладка скрыта участнику' );
update_option( WPPA_Builder::OPTION, array( 'dashboard' => array() ) );
chat_assert( 'chat-widget' === WPPA_Builder::layout()['messages'][0]['id'], 'новый блок попадает в старую раскладку по умолчанию' );
$GLOBALS['wppa_test']['catch_redirect'] = true; $GLOBALS['wppa_test']['logged_in'] = true; $GLOBALS['wppa_test']['current_user'] = 8;
$_POST = array( 'mentor_id' => 8, 'message' => 'без JavaScript', 'wppa_nonce' => wp_create_nonce( 'wppa_chat_send' ) );
try { WPPA_Chat::handle_post(); } catch ( RuntimeException $e ) { chat_assert( false !== strpos( $e->getMessage(), 'chat_sent' ) && false !== strpos( WPPA_Chat::messages_html( WPPA_Chat::messages( 8, 8 )['items'], 8, 8 ), 'без JavaScript' ), 'обычная POST-форма работает без JS' ); }
$_POST['wppa_nonce'] = 'bad';
try { WPPA_Chat::handle_post(); chat_assert( false, 'POST nonce' ); } catch ( RuntimeException $e ) { chat_assert( 'invalid nonce' === $e->getMessage(), 'POST nonce проверяется' ); }
// Уведомление наставнику о новых ответах и независимое прочтение.
$GLOBALS['wppa_test']['current_user'] = 12; WPPA_Chat::send( 8, 12, 'Новый ответ для наставника' );
chat_assert( WPPA_Chat::mentor_unread( 8 ) >= 1 && WPPA_Chat::unread_total( 8 ) >= 1 && 0 === WPPA_Chat::mentor_unread( 11 ), 'у наставника появляется счётчик ответов' );
$GLOBALS['wppa_test']['current_user'] = 8; $_GET = array();
$account = WPPA_Template::get( 'account', array( 'user' => new WP_User( 8 ), 'tabs' => array( 'messages' => 'Сообщения' ), 'current_tab' => 'messages' ) );
chat_assert( false !== strpos( $account, 'data-wppa-unread-badge' ) && false !== strpos( $account, 'wppa_chat_view_nonce' ), 'наставник видит индикатор и защищённую ссылку' );
$r = chat_ajax( 'ajax_unread', 8, 8, $nonce ); chat_assert( $r->payload['success'] && $r->payload['data']['total'] >= 1, 'AJAX счётчика доступен наставнику' );
$r = chat_ajax( 'ajax_read', 8, 8, $nonce ); chat_assert( $r->payload['success'] && 0 === WPPA_Chat::mentor_unread( 8 ), 'при открытии истории ответы помечаются прочитанными' );
WPPA_Chat::send( 8, 13, 'Ответ для теста без JS' ); chat_assert( WPPA_Chat::mentor_unread( 8 ) === 1, 'новый ответ после прочтения снова виден' );
chat_html( 8, 8, 1 ); chat_assert( 0 === WPPA_Chat::mentor_unread( 8 ), 'открытие истории по защищённой ссылке работает без JS' );
// Точные права удаления и защита от подмены сообщений между диалогами.
WPPA_Chat::send( 8, 8, 'Наставник удалит своё' );
$posts = WPPA_Chat::messages( 8, 8 )['items']; $own = end( $posts )->ID;
WPPA_Chat::send( 8, 12, 'Ответ на удаление' );
$posts = WPPA_Chat::messages( 8, 8 )['items']; $reply = end( $posts )->ID;
$mentor_render = WPPA_Chat::messages_html( WPPA_Chat::messages( 8, 8 )['items'], 8, 8 );
$curator_render = WPPA_Chat::messages_html( WPPA_Chat::messages( 8, 12 )['items'], 8, 12 );
chat_assert( false !== strpos( $mentor_render, 'name="message_id" value="' . $own . '"' ) && false === strpos( $mentor_render, 'name="message_id" value="' . $reply . '"' ) && false !== strpos( $curator_render, 'name="message_id" value="' . $reply . '"' ), 'кнопки удаления соответствуют правам' );
chat_assert( 'chat_forbidden' === WPPA_Chat::delete_message( 8, 8, $reply ) && get_post( $reply ), 'наставник не может удалить ответ куратора' );
chat_assert( 'chat_forbidden' === WPPA_Chat::delete_message( 8, 9, $own ) && 'chat_forbidden' === WPPA_Chat::delete_message( 9, 12, $own ), 'чужой наставник и подмена диалога запрещены' );
chat_assert( 'chat_deleted' === WPPA_Chat::delete_message( 8, 8, $own ) && ! get_post( $own ), 'наставник удаляет своё сообщение для всех' );
chat_assert( 'chat_deleted' === WPPA_Chat::delete_message( 8, 13, $reply ) && ! get_post( $reply ), 'любой куратор удаляет сообщение другого куратора' );
WPPA_Chat::send( 8, 8, 'Удаление AJAX' ); $posts = WPPA_Chat::messages( 8, 8 )['items']; $delete_id = end( $posts )->ID;
$GLOBALS['wppa_test']['logged_in'] = true; $GLOBALS['wppa_test']['current_user'] = 12;
$_POST = array( 'mentor_id' => 8, 'nonce' => $nonce, 'message_id' => $delete_id, 'delete_nonce' => 'bad', 'chat_page' => 1 );
try { WPPA_Chat::ajax_delete(); } catch ( WPPA_Stub_Json $e ) { chat_assert( ! $e->payload['success'] && get_post( $delete_id ), 'AJAX удаление проверяет отдельный nonce' ); }
$_POST['delete_nonce'] = wp_create_nonce( 'wppa_chat_delete_' . $delete_id );
try { WPPA_Chat::ajax_delete(); } catch ( WPPA_Stub_Json $e ) { chat_assert( $e->payload['success'] && ! get_post( $delete_id ), 'AJAX удаление куратора работает' ); }
WPPA_Chat::send( 8, 8, 'Удаление без JS' ); $posts = WPPA_Chat::messages( 8, 8 )['items']; $delete_id = end( $posts )->ID;
$GLOBALS['wppa_test']['current_user'] = 8;
$_POST = array( 'mentor_id' => 8, 'message_id' => $delete_id, 'wppa_delete_nonce' => wp_create_nonce( 'wppa_chat_delete_' . $delete_id ), 'chat_page' => 1 );
try { WPPA_Chat::handle_delete(); } catch ( RuntimeException $e ) { chat_assert( false !== strpos( $e->getMessage(), 'chat_deleted' ) && ! get_post( $delete_id ), 'удаление POST-формой без JS' ); }
$GLOBALS['wppa_test']['mail_fail'] = true;
$r = chat_ajax( 'ajax_send', 8, 8, $nonce, 'Письмо не ушло' );
chat_assert( $r->payload['success'] && ! empty( $r->payload['data']['mail_failed'] ) && false !== strpos( $r->payload['data']['html'], 'Письмо не ушло' ), 'при ошибке email сообщение сохранено и автор предупреждён' );
unset( $GLOBALS['wppa_test']['mail_fail'] );
// Один человек может одновременно быть наставником и куратором.
WPPA_Teams::set_curator( 8, true ); WPPA_Chat::mark_read( 8, 8, WPPA_Chat::messages( 8, 8 )['items'] );
WPPA_Chat::send( 8, 12, 'Ответ пользователю с двумя ролями' );
$dual = WPPA_Chat::unread_html( 8, WPPA_Chat::unread( 8 ), WPPA_Chat::mentors() );
chat_assert( WPPA_Chat::mentor_unread( 8 ) === 1 && false !== strpos( $dual, 'Ответы кураторов в вашей переписке' ), 'для наставника-куратора список содержит его личную переписку' );
WPPA_Teams::set_curator( 8, false );
// Деинсталляция: по умолчанию сохраняем, при явной очистке удаляем пакетами.
define( 'WP_UNINSTALL_PLUGIN', true );
include WPPA_PATH . 'uninstall.php';
chat_assert( count( WPPA_Chat::messages( 8, 8 )['items'] ) === 50, 'деинсталляция по умолчанию сохраняет историю' );
for ( $i = 0; $i < 501; ++$i ) { WPPA_Chat::send( 8, 8, 'проверка удаления' ); }
$settings = get_option( 'wppa_settings' ); $settings['delete_data_on_uninstall'] = 1; update_option( 'wppa_settings', $settings );
update_user_meta( 12, '_wppa_chat_read_2_8', 123 );
include WPPA_PATH . 'uninstall.php';
chat_assert( 123 === get_user_meta( 12, '_wppa_chat_read_2_8', true ), 'очистка не удаляет отметки другого сайта multisite' );
chat_assert( 0 === count( get_posts( array( 'post_type' => WPPA_Chat::CPT, 'post_status' => 'private', 'numberposts' => -1 ) ) ) && get_post_type( $team1 ) === WPPA_Teams::CPT && '' === get_user_meta( 12, WPPA_Chat::read_prefix() . '8', true ), 'деинсталляция с очисткой удаляет переписку и отметки, но не команды' );
echo "Проверок: $count, ошибок: $fail\n"; exit( $fail ? 1 : 0 );
