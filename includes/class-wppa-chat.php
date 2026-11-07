<?php
/** Личная переписка наставника со всеми кураторами сайта. */
defined( 'ABSPATH' ) || exit;

class WPPA_Chat {
	const CPT = 'wppa_chat_message';
	const MENTOR_META = '_wppa_chat_mentor';
	const PAGE_SIZE = 50;
	const MAX_LENGTH = 2000;
	const READ_META_PREFIX = '_wppa_chat_read_';
	private static $last_mail_failed = false;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'wp_ajax_wppa_chat_read', array( __CLASS__, 'ajax_read' ) );
		add_action( 'wp_ajax_wppa_chat_send', array( __CLASS__, 'ajax_send' ) );
		add_action( 'wp_ajax_wppa_chat_delete', array( __CLASS__, 'ajax_delete' ) );
		add_action( 'wp_ajax_wppa_chat_unread', array( __CLASS__, 'ajax_unread' ) );
	}

	public static function register_post_type() {
		register_post_type( self::CPT, array(
			'label' => __( 'Сообщения наставников', 'wp-personal-account' ),
			'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
			'show_ui' => false, 'show_in_rest' => false, 'query_var' => false,
			'can_export' => false, 'capability_type' => array( 'wppa_chat_message', 'wppa_chat_messages' ),
			'map_meta_cap' => true,
			'supports' => array( 'editor', 'author' ),
		) );
	}

	/** Только активные наставники и кураторы. Администратор не становится куратором автоматически. */
	public static function can_use( $user_id ) {
		$user_id = (int) $user_id;
		return $user_id > 0 && ( WPPA_Teams::is_curator( $user_id ) || WPPA_Teams::is_mentor( $user_id ) );
	}

	/** Доступ к конкретному диалогу: сам наставник либо любой куратор. */
	public static function can_view( $mentor_id, $user_id ) {
		$mentor_id = (int) $mentor_id;
		$user_id = (int) $user_id;
		return $mentor_id > 0 && $user_id > 0 && WPPA_Teams::is_mentor( $mentor_id )
			&& ( $user_id === $mentor_id || WPPA_Teams::is_curator( $user_id ) );
	}

	/** Все действующие наставники: куратор может открыть любую переписку. */
	public static function mentors() {
		$users = get_users( array(
			'meta_key' => WPPA_Teams::META_ROLE,
			'meta_value' => 'mentor',
			'number' => -1,
			'orderby' => 'display_name',
			'order' => 'ASC',
		) );
		return array_values( array_filter( (array) $users, static function ( $user ) {
			return $user instanceof WP_User && WPPA_Teams::is_mentor( $user->ID );
		} ) );
	}

	/** Страница истории: последние сообщения сначала загружаются из БД, затем выводятся по времени. */
	public static function messages( $mentor_id, $user_id, $page = 1 ) {
		if ( ! self::can_view( $mentor_id, $user_id ) ) {
			return array( 'items' => array(), 'more' => false );
		}
		$page = min( 10000, max( 1, (int) $page ) );
		$posts = get_posts( array(
			'post_type' => self::CPT, 'post_status' => 'private',
			'meta_key' => self::MENTOR_META, 'meta_value' => (string) (int) $mentor_id,
			'numberposts' => self::PAGE_SIZE + 1, 'offset' => ( $page - 1 ) * self::PAGE_SIZE,
			'orderby' => 'ID', 'order' => 'DESC', 'suppress_filters' => true,
		) );
		$more = count( $posts ) > self::PAGE_SIZE;
		return array( 'items' => array_reverse( array_slice( $posts, 0, self::PAGE_SIZE ) ), 'more' => $more );
	}

	/** Метаданные пользователей общие в multisite, поэтому добавляем ID сайта. */
	public static function read_prefix() { return self::READ_META_PREFIX . get_current_blog_id() . '_'; }

	/** Для каждого куратора — собственный счётчик новых сообщений каждого наставника. */
	public static function unread( $user_id, $mentors = null ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || ! WPPA_Teams::is_curator( $user_id ) ) { return array(); }
		if ( null === $mentors ) { $mentors = self::mentors(); }
		$active = array();
		foreach ( $mentors as $mentor ) {
			if ( $mentor instanceof WP_User && (int) $mentor->ID !== $user_id && self::can_view( $mentor->ID, $user_id ) ) {
				$active[ (int) $mentor->ID ] = $mentor;
			}
		}
		if ( ! $active ) { return array(); }
		global $wpdb;
		// Только сообщения наставника (не ответы кураторов), без чтения текста.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- актуальные персональные счётчики.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT /* WPPA_CHAT_UNREAD */ p.post_author AS mentor_id, COUNT(DISTINCT p.ID) AS unread_count
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
			LEFT JOIN (
				SELECT meta_key, MAX(CAST(meta_value AS UNSIGNED)) AS last_seen
				FROM {$wpdb->usermeta} WHERE user_id = %d GROUP BY meta_key
			) seen ON seen.meta_key = CONCAT(%s, p.post_author)
			WHERE p.post_type = %s AND p.post_status = 'private'
			AND CAST(pm.meta_value AS UNSIGNED) = p.post_author
			AND p.ID > COALESCE(seen.last_seen, 0)
			GROUP BY p.post_author",
			self::MENTOR_META, $user_id, self::read_prefix(), self::CPT
		) );
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$id = (int) $row->mentor_id;
			$count = (int) $row->unread_count;
			if ( isset( $active[ $id ] ) && $count > 0 ) { $counts[ $id ] = $count; }
		}
		return $counts;
	}

	/** Новые ответы кураторов в личном диалоге наставника. */
	public static function mentor_unread( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || ! WPPA_Teams::is_mentor( $user_id ) ) { return 0; }
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- персональный актуальный счётчик.
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT /* WPPA_CHAT_MENTOR_UNREAD */ COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value = %s
			WHERE p.post_type = %s AND p.post_status = 'private' AND p.post_author <> %d
			AND p.ID > COALESCE((SELECT MAX(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s), 0)",
			self::MENTOR_META, (string) $user_id, self::CPT, $user_id, $user_id, self::read_prefix() . $user_id
		) );
		return max( 0, (int) $count );
	}

	public static function unread_total( $user_id, $counts = null ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || ! self::can_use( $user_id ) ) { return 0; }
		if ( null === $counts ) { $counts = WPPA_Teams::is_curator( $user_id ) ? self::unread( $user_id ) : array(); }
		return array_sum( $counts ) + self::mentor_unread( $user_id );
	}

	/** Отмечаем просмотр только по последней выданной странице, не затрагивая параллельно пришедшие сообщения. */
	public static function mark_read( $mentor_id, $user_id, $messages ) {
		$mentor_id = (int) $mentor_id; $user_id = (int) $user_id;
		if ( ! self::can_view( $mentor_id, $user_id ) ) { return; }
		$latest = 0;
		foreach ( (array) $messages as $message ) {
			if ( $message instanceof WP_Post && self::CPT === $message->post_type && 'private' === $message->post_status
				&& (int) get_post_meta( $message->ID, self::MENTOR_META, true ) === $mentor_id ) {
				$latest = max( $latest, (int) $message->ID );
			}
		}
		if ( ! $latest ) { return; }
		$key = self::read_prefix() . $mentor_id;
		// Сравнение предыдущего значения не позволяет параллельному запросу сдвинуть отметку назад.
		for ( $attempt = 0; $attempt < 4; ++$attempt ) {
			$previous = get_user_meta( $user_id, $key, true );
			if ( (int) $previous >= $latest ) { return; }
			if ( '' === $previous ) {
				if ( add_user_meta( $user_id, $key, $latest, true ) ) { return; }
			} elseif ( update_user_meta( $user_id, $key, $latest, $previous ) ) { return; }
		}
	}

	/** Список непрочитанных диалогов содержит только имена и ссылки, текст не раскрывается. */
	public static function unread_html( $user_id, $counts, $mentors ) {
		if ( ! WPPA_Teams::is_curator( $user_id ) ) { return ''; }
		$own_count = self::mentor_unread( $user_id );
		if ( ! $counts && ! $own_count ) { return ''; }
		$out = '<div class="wppa-chat__unread"><strong>' . esc_html__( 'Новые сообщения', 'wp-personal-account' ) . '</strong><ul>';
		if ( $own_count ) {
			$out .= '<li><a href="' . esc_url( self::thread_url( $user_id ) ) . '">' . esc_html__( 'Ответы кураторов в вашей переписке', 'wp-personal-account' ) . '</a> — ' . esc_html( sprintf( __( 'непрочитанных: %d', 'wp-personal-account' ), $own_count ) ) . '</li>';
		}
		foreach ( $mentors as $mentor ) {
			$id = (int) $mentor->ID;
			if ( empty( $counts[ $id ] ) || ! self::can_view( $id, $user_id ) ) { continue; }
			$out .= '<li><a href="' . esc_url( self::thread_url( $id ) ) . '">' . esc_html( $mentor->display_name . ' (' . $mentor->user_email . ')' ) . '</a> — ' . esc_html( sprintf( __( 'непрочитанных: %d', 'wp-personal-account' ), (int) $counts[ $id ] ) ) . '</li>';
		}
		return $out . '</ul></div>';
	}

	public static function ajax_unread() {
		$user_id = is_user_logged_in() ? get_current_user_id() : 0;
		$nonce = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		if ( ! $user_id || ! self::can_use( $user_id ) || ! wp_verify_nonce( $nonce, 'wppa_chat_ajax' ) ) {
			wp_send_json_error( array( 'message' => __( 'Нет доступа. Обновите страницу.', 'wp-personal-account' ) ), 403 );
		}
		$mentors = WPPA_Teams::is_curator( $user_id ) ? self::mentors() : array();
		$counts = self::unread( $user_id, $mentors );
		wp_send_json_success( array( 'total' => self::unread_total( $user_id, $counts ), 'html' => self::unread_html( $user_id, $counts, $mentors ) ) );
	}

	/** Сообщения — только обычный текст, без HTML, вложений и ссылок с разметкой. */
	public static function send( $mentor_id, $user_id, $text ) {
		self::$last_mail_failed = false;
		if ( ! self::can_view( $mentor_id, $user_id ) ) {
			return 'chat_forbidden';
		}
		if ( ! is_string( $text ) || strlen( $text ) > 16000 ) {
			return 'chat_too_long';
		}
		$text = trim( sanitize_textarea_field( $text ) );
		if ( '' === $text ) {
			return 'chat_empty';
		}
		$length = preg_match_all( '/./us', $text, $unused );
		if ( false === $length || $length > self::MAX_LENGTH ) {
			return 'chat_too_long';
		}
		$id = wp_insert_post( wp_slash( array(
			'post_type' => self::CPT, 'post_status' => 'private', 'post_author' => (int) $user_id,
			'post_title' => '', 'post_content' => $text,
			'meta_input' => array( self::MENTOR_META => (int) $mentor_id ),
		) ), true );
		if ( is_wp_error( $id ) || ! $id ) { return 'chat_failed'; }
		self::$last_mail_failed = ! self::notify_recipients( $mentor_id, $user_id );
		return 'chat_sent';
	}

	/** Каждому получателю отдельное письмо без текста приватной переписки. */
	private static function notify_recipients( $mentor_id, $sender_id ) {
		$mentor_id = (int) $mentor_id; $sender_id = (int) $sender_id;
		$recipients = $sender_id === $mentor_id ? WPPA_Teams::curators() : array( get_userdata( $mentor_id ) );
		$sent_to = array(); $ok = true;
		foreach ( $recipients as $recipient ) {
			if ( ! $recipient instanceof WP_User || (int) $recipient->ID === $sender_id ) { continue; }
			if ( $sender_id === $mentor_id && ! WPPA_Teams::is_curator( $recipient->ID ) ) { continue; }
			$email = sanitize_email( $recipient->user_email );
			if ( ! is_email( $email ) ) { $ok = false; continue; }
			$key = strtolower( $email ); if ( isset( $sent_to[ $key ] ) ) { continue; }
			$sent_to[ $key ] = true;
			// В письме нет текста сообщения и nonce другого пользователя.
			$url = WPPA_Plugin::tab_url( 'messages', $sender_id === $mentor_id ? array( 'wppa_chat_mentor' => $mentor_id ) : array() );
			// После входа получатель возвращается в нужный диалог.
			$url = add_query_arg( 'wppa_redirect', rawurlencode( $url ), $url );
			$subject = __( 'Новое сообщение в личном кабинете', 'wp-personal-account' );
			$body = __( 'Вам написали в личном кабинете. Войдите на сайт, чтобы прочитать сообщение:', 'wp-personal-account' ) . "\n\n" . $url;
			try { if ( ! wp_mail( $email, $subject, $body ) ) { $ok = false; } } catch ( Throwable $error ) { $ok = false; }
		}
		return $ok && ! empty( $sent_to );
	}

	/** Куратор может удалить любое сообщение, наставник — только своё. */
	public static function delete_message( $mentor_id, $user_id, $message_id ) {
		$mentor_id = (int) $mentor_id; $user_id = (int) $user_id; $message_id = (int) $message_id;
		if ( ! self::can_view( $mentor_id, $user_id ) || $message_id <= 0 ) { return 'chat_forbidden'; }
		$post = get_post( $message_id );
		if ( ! $post instanceof WP_Post || self::CPT !== $post->post_type || 'private' !== $post->post_status
			|| (int) get_post_meta( $message_id, self::MENTOR_META, true ) !== $mentor_id
			|| ( ! WPPA_Teams::is_curator( $user_id ) && (int) $post->post_author !== $user_id ) ) { return 'chat_forbidden'; }
		return wp_delete_post( $message_id, true ) ? 'chat_deleted' : 'chat_delete_failed';
	}

	public static function handle_delete() {
		$mentor = isset( $_POST['mentor_id'] ) && is_scalar( $_POST['mentor_id'] ) ? absint( wp_unslash( $_POST['mentor_id'] ) ) : 0;
		$id = isset( $_POST['message_id'] ) && is_scalar( $_POST['message_id'] ) ? absint( wp_unslash( $_POST['message_id'] ) ) : 0;
		check_admin_referer( 'wppa_chat_delete_' . $id, 'wppa_delete_nonce' );
		$user_id = is_user_logged_in() ? get_current_user_id() : 0;
		$result = self::delete_message( $mentor, $user_id, $id );
		$page = isset( $_POST['chat_page'] ) && is_scalar( $_POST['chat_page'] ) ? min( 10000, max( 1, absint( wp_unslash( $_POST['chat_page'] ) ) ) ) : 1;
		$back = self::can_view( $mentor, $user_id ) ? self::thread_url( WPPA_Teams::is_curator( $user_id ) ? $mentor : 0, $page ) : WPPA_Plugin::account_url();
		WPPA_Notices::redirect( $result, $back );
	}

	public static function ajax_delete() {
		list( $user_id, $mentor ) = self::ajax_user_and_mentor();
		$id = isset( $_POST['message_id'] ) && is_scalar( $_POST['message_id'] ) ? absint( wp_unslash( $_POST['message_id'] ) ) : 0;
		$delete_nonce = isset( $_POST['delete_nonce'] ) && is_string( $_POST['delete_nonce'] ) ? wp_unslash( $_POST['delete_nonce'] ) : '';
		if ( ! wp_verify_nonce( $delete_nonce, 'wppa_chat_delete_' . $id ) ) { wp_send_json_error( array( 'message' => __( 'Не удалось подтвердить удаление. Обновите страницу.', 'wp-personal-account' ) ), 403 ); }
		$result = self::delete_message( $mentor, $user_id, $id );
		if ( 'chat_deleted' !== $result ) { wp_send_json_error( array( 'message' => __( 'Не удалось удалить сообщение или нет доступа.', 'wp-personal-account' ) ), 403 ); }
		$page = isset( $_POST['chat_page'] ) && is_scalar( $_POST['chat_page'] ) ? min( 10000, max( 1, absint( wp_unslash( $_POST['chat_page'] ) ) ) ) : 1;
		$data = self::messages( $mentor, $user_id, $page );
		wp_send_json_success( array( 'html' => self::messages_html( $data['items'], $mentor, $user_id, $page ), 'more' => $data['more'] ) );
	}

	public static function thread_url( $mentor_id = 0, $page = 1 ) {
		$args = array();
		if ( $mentor_id ) {
			$args['wppa_chat_mentor'] = (int) $mentor_id;
			$args['wppa_chat_view_nonce'] = wp_create_nonce( 'wppa_chat_view_' . (int) $mentor_id );
		}
		if ( ! $mentor_id && is_user_logged_in() && WPPA_Teams::is_mentor( get_current_user_id() ) && ! WPPA_Teams::is_curator( get_current_user_id() ) ) {
			$args['wppa_chat_view_nonce'] = wp_create_nonce( 'wppa_chat_view_' . get_current_user_id() );
		}
		if ( $page > 1 ) { $args['wppa_chat_page'] = (int) $page; }
		return WPPA_Plugin::tab_url( 'messages', $args );
	}

	/** Отправка без JavaScript. */
	public static function handle_post() {
		check_admin_referer( 'wppa_chat_send', 'wppa_nonce' );
		$user_id = is_user_logged_in() ? get_current_user_id() : 0;
		$mentor = isset( $_POST['mentor_id'] ) && is_scalar( $_POST['mentor_id'] ) ? absint( wp_unslash( $_POST['mentor_id'] ) ) : 0;
		$text = isset( $_POST['message'] ) && is_string( $_POST['message'] ) ? wp_unslash( $_POST['message'] ) : '';
		$code = self::send( $mentor, $user_id, $text );
		if ( 'chat_sent' === $code && self::$last_mail_failed ) { $code = 'chat_sent_mail_failed'; }
		$back = self::can_view( $mentor, $user_id ) ? self::thread_url( WPPA_Teams::is_curator( $user_id ) ? $mentor : 0 ) : WPPA_Plugin::account_url();
		WPPA_Notices::redirect( $code, $back );
	}

	private static function ajax_user_and_mentor() {
		$user_id = is_user_logged_in() ? get_current_user_id() : 0;
		$nonce = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		$mentor = isset( $_POST['mentor_id'] ) && is_scalar( $_POST['mentor_id'] ) ? absint( wp_unslash( $_POST['mentor_id'] ) ) : 0;
		if ( ! $user_id || ! wp_verify_nonce( $nonce, 'wppa_chat_ajax' ) || ! self::can_view( $mentor, $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Нет доступа к переписке. Обновите страницу.', 'wp-personal-account' ) ), 403 );
		}
		return array( $user_id, $mentor );
	}

	public static function ajax_read() {
		list( $user_id, $mentor ) = self::ajax_user_and_mentor();
		$data = self::messages( $mentor, $user_id );
		self::mark_read( $mentor, $user_id, $data['items'] );
		wp_send_json_success( array( 'html' => self::messages_html( $data['items'], $mentor, $user_id ), 'more' => $data['more'] ) );
	}

	public static function ajax_send() {
		list( $user_id, $mentor ) = self::ajax_user_and_mentor();
		$text = isset( $_POST['message'] ) && is_string( $_POST['message'] ) ? wp_unslash( $_POST['message'] ) : '';
		$result = self::send( $mentor, $user_id, $text );
		if ( 'chat_sent' !== $result ) {
			$notices = WPPA_Notices::registry();
			wp_send_json_error( array( 'message' => isset( $notices[ $result ][1] ) ? $notices[ $result ][1] : __( 'Не удалось отправить сообщение.', 'wp-personal-account' ) ), 400 );
		}
		$data = self::messages( $mentor, $user_id );
		self::mark_read( $mentor, $user_id, $data['items'] );
		wp_send_json_success( array( 'html' => self::messages_html( $data['items'], $mentor, $user_id ), 'more' => $data['more'], 'mail_failed' => self::$last_mail_failed ) );
	}

	/** Готовая безопасная разметка, используется и при первом выводе, и при обновлении. */
	public static function messages_html( $messages, $mentor_id, $user_id, $page = 1 ) {
		if ( ! self::can_view( $mentor_id, $user_id ) ) { return ''; }
		if ( ! $messages ) {
			return '<p class="wppa-chat__empty">' . esc_html__( 'Сообщений пока нет. Напишите первое сообщение.', 'wp-personal-account' ) . '</p>';
		}
		$out = '';
		foreach ( $messages as $message ) {
			if ( ! $message instanceof WP_Post || self::CPT !== $message->post_type || (int) get_post_meta( $message->ID, self::MENTOR_META, true ) !== (int) $mentor_id ) { continue; }
			$sender = get_userdata( (int) $message->post_author );
			$name = $sender ? $sender->display_name : __( 'Пользователь удалён', 'wp-personal-account' );
			$role = (int) $message->post_author === (int) $mentor_id ? __( 'Наставник', 'wp-personal-account' ) : __( 'Куратор', 'wp-personal-account' );
			$date = ! empty( $message->post_date ) ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $message->post_date ) : '';
			$out .= '<article class="wppa-chat__message' . ( (int) $message->post_author === (int) $user_id ? ' wppa-chat__message--mine' : '' ) . '">';
			$out .= '<div class="wppa-chat__meta"><strong>' . esc_html( $role . ' · ' . $name ) . '</strong><time>' . esc_html( $date ) . '</time></div>';
			$out .= '<div class="wppa-chat__text">' . nl2br( esc_html( $message->post_content ) ) . '</div>';
			if ( WPPA_Teams::is_curator( $user_id ) || (int) $message->post_author === (int) $user_id ) {
				$out .= '<form method="post" action="' . esc_url( self::thread_url( WPPA_Teams::is_curator( $user_id ) ? $mentor_id : 0, $page ) ) . '" data-wppa-chat-delete>';
				$out .= '<input type="hidden" name="wppa_action" value="chat_delete"><input type="hidden" name="mentor_id" value="' . esc_attr( (string) $mentor_id ) . '">';
				$out .= '<input type="hidden" name="message_id" value="' . esc_attr( (string) $message->ID ) . '"><input type="hidden" name="chat_page" value="' . esc_attr( (string) $page ) . '">';
				$out .= '<input type="hidden" name="wppa_delete_nonce" value="' . esc_attr( wp_create_nonce( 'wppa_chat_delete_' . $message->ID ) ) . '">';
				$out .= '<button type="submit" class="wppa-chat__delete">' . esc_html__( 'Удалить', 'wp-personal-account' ) . '</button></form>';
			}
			$out .= '</article>';
		}
		return $out;
	}
}
