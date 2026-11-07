<?php
/** Личная переписка наставника со всеми кураторами сайта. */
defined( 'ABSPATH' ) || exit;

class WPPA_Chat {
	const CPT = 'wppa_chat_message';
	const MENTOR_META = '_wppa_chat_mentor';
	const PAGE_SIZE = 50;
	const MAX_LENGTH = 2000;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'wp_ajax_wppa_chat_read', array( __CLASS__, 'ajax_read' ) );
		add_action( 'wp_ajax_wppa_chat_send', array( __CLASS__, 'ajax_send' ) );
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

	/** Сообщения — только обычный текст, без HTML, вложений и ссылок с разметкой. */
	public static function send( $mentor_id, $user_id, $text ) {
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
		return is_wp_error( $id ) || ! $id ? 'chat_failed' : 'chat_sent';
	}

	public static function thread_url( $mentor_id = 0, $page = 1 ) {
		$args = array();
		if ( $mentor_id ) { $args['wppa_chat_mentor'] = (int) $mentor_id; }
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
		wp_send_json_success( array( 'html' => self::messages_html( $data['items'], $mentor, $user_id ), 'more' => $data['more'] ) );
	}

	/** Готовая безопасная разметка, используется и при первом выводе, и при обновлении. */
	public static function messages_html( $messages, $mentor_id, $user_id ) {
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
			$out .= '<div class="wppa-chat__text">' . nl2br( esc_html( $message->post_content ) ) . '</div></article>';
		}
		return $out;
	}
}
