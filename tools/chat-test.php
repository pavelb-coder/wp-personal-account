<?php
/** Проверки чата на заглушках: это не замена интеграционному тесту WordPress. */
require __DIR__ . '/wp-stubs.php';
$base = dirname( __DIR__ ) . '/';
define( 'WPPA_VERSION', 'test' );
define( 'WPPA_FILE', $base . 'wp-personal-account.php' );
define( 'WPPA_PATH', $base );
define( 'WPPA_URL', 'https://example.com/wp-content/plugins/wp-personal-account/' );
foreach ( array( 'plugin', 'template', 'notices', 'teams', 'chat', 'builder' ) as $class ) { require $base . 'includes/class-wppa-' . $class . '.php'; }
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
	$_GET = array( 'wppa_chat_mentor' => $mentor, 'wppa_chat_page' => $page );
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
chat_assert( 'chat_sent' === WPPA_Chat::send( 8, 8, "Вопрос: \\\"цитата\\\" \\ путь\nвторая строка" ), 'сообщение наставника' );
chat_assert( 'chat_sent' === WPPA_Chat::send( 8, 12, 'Ответ первого куратора' ) && 'chat_sent' === WPPA_Chat::send( 8, 13, 'Ответ второго куратора' ), 'каждый куратор отвечает независимо' );
chat_assert( 'chat_sent' === WPPA_Chat::send( 9, 9, 'секрет второго' ), 'отдельный чат другого наставника' );
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
// Деинсталляция: по умолчанию сохраняем, при явной очистке удаляем пакетами.
define( 'WP_UNINSTALL_PLUGIN', true );
include WPPA_PATH . 'uninstall.php';
chat_assert( count( WPPA_Chat::messages( 8, 8 )['items'] ) === 50, 'деинсталляция по умолчанию сохраняет историю' );
for ( $i = 0; $i < 501; ++$i ) { WPPA_Chat::send( 8, 8, 'проверка удаления' ); }
$settings = get_option( 'wppa_settings' ); $settings['delete_data_on_uninstall'] = 1; update_option( 'wppa_settings', $settings );
include WPPA_PATH . 'uninstall.php';
chat_assert( 0 === count( get_posts( array( 'post_type' => WPPA_Chat::CPT, 'post_status' => 'private', 'numberposts' => -1 ) ) ) && get_post_type( $team1 ) === WPPA_Teams::CPT, 'деинсталляция с очисткой удаляет всю переписку, но не команды' );
echo "Проверок: $count, ошибок: $fail\n"; exit( $fail ? 1 : 0 );
