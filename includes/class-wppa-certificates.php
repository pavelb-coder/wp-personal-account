<?php
/**
 * Сертификаты участников: выдача через плагин WP Diplom (wp-diplom).
 *
 * Плагин не обязателен: пока `WPDC_Certificate` не объявлен, раздел
 * «Сертификаты» просто не показывается, а кабинет работает как раньше.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Certificates
 */
class WPPA_Certificates {

	/**
	 * Мета участника: выданные ему сертификаты.
	 */
	const META = '_wppa_certificates';

	/**
	 * Действие admin-post для отдачи PDF.
	 */
	const DOWNLOAD_ACTION = 'wppa_certificate_download';

	/**
	 * Ключ настройки «бланк сертификата».
	 */
	const TEMPLATE_KEY = 'certificate_template';

	/**
	 * Ключ настройки «отправлять письмо при выдаче».
	 */
	const EMAIL_KEY = 'certificate_email';

	/**
	 * Подписка на события.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( __CLASS__, 'handle_download' ) );
	}

	/**
	 * Активен ли плагин WP Diplom.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( 'WPDC_Certificate' ) && method_exists( 'WPDC_Certificate', 'create' ) && method_exists( 'WPDC_Certificate', 'pdf' );
	}

	/**
	 * Бланк по умолчанию из настроек (0 — бланк WP Diplom по умолчанию).
	 *
	 * @return int
	 */
	public static function template_id() {
		$id = (int) WPPA_Plugin::setting( self::TEMPLATE_KEY, 0 );

		/**
		 * Фильтр бланка сертификата.
		 *
		 * @param int $id ID бланка WP Diplom.
		 */
		return (int) apply_filters( 'wppa_certificate_template', $id );
	}

	/**
	 * Отправлять сертификат письмом сразу при выдаче.
	 *
	 * @return bool
	 */
	public static function send_email() {
		$send = (bool) (int) WPPA_Plugin::setting( self::EMAIL_KEY, 0 );

		/**
		 * Фильтр отправки сертификата письмом.
		 *
		 * @param bool $send Отправлять.
		 */
		return (bool) apply_filters( 'wppa_certificate_send_email', $send );
	}

	/**
	 * Бланки WP Diplom для выбора в настройках.
	 *
	 * @return array id => заголовок.
	 */
	public static function templates() {
		if ( ! self::available() || ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'wpdc_template',
				'posts_per_page' => 100,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$out = array();

		foreach ( (array) $posts as $post ) {
			$out[ (int) $post->ID ] = (string) $post->post_title;
		}

		return $out;
	}

	/**
	 * Может ли пользователь видеть сертификаты участника.
	 *
	 * @param int $user_id   Чей сертификат.
	 * @param int $viewer_id Кто смотрит.
	 * @return bool
	 */
	public static function can_view( $user_id, $viewer_id = 0 ) {
		$viewer_id = (int) ( $viewer_id ? $viewer_id : get_current_user_id() );

		if ( ! $viewer_id ) {
			return false;
		}

		if ( (int) $user_id === $viewer_id ) {
			return true;
		}

		if ( WPPA_Teams::is_curator( $viewer_id ) ) {
			return true;
		}

		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	/**
	 * Выданные сертификаты участника (сначала новые).
	 *
	 * @param int $user_id Участник.
	 * @return array
	 */
	public static function for_user( $user_id ) {
		$user_id = (int) $user_id;

		if ( ! $user_id ) {
			return array();
		}

		$saved = get_user_meta( $user_id, self::META, true );

		if ( ! is_array( $saved ) ) {
			return array();
		}

		$out = array();

		foreach ( $saved as $key => $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
				continue;
			}

			$code = isset( $entry['code'] ) ? (string) $entry['code'] : '';

			$out[ (string) $key ] = array(
				'id'      => (int) $entry['id'],
				'number'  => isset( $entry['number'] ) ? (string) $entry['number'] : '',
				'code'    => $code,
				'course'  => isset( $entry['course'] ) ? (string) $entry['course'] : '',
				'time'    => isset( $entry['time'] ) ? (int) $entry['time'] : 0,
				'by'      => isset( $entry['by'] ) ? (int) $entry['by'] : 0,
				'sent'    => ! empty( $entry['sent'] ),
				'revoked' => self::is_revoked( (int) $entry['id'] ),
				'verify'  => '' !== $code ? self::verify_url( (int) $entry['id'], $code ) : '',
				'url'     => self::download_url( $user_id, (int) $entry['id'] ),
			);
		}

		uasort(
			$out,
			static function ( $a, $b ) {
				return $b['time'] <=> $a['time'];
			}
		);

		return $out;
	}

	/**
	 * Сколько сертификатов у участника.
	 *
	 * @param int $user_id Участник.
	 * @return int
	 */
	public static function count( $user_id ) {
		return count( self::for_user( $user_id ) );
	}

	/**
	 * Сертификаты есть у кого-то из участников команды.
	 *
	 * @param int $team_id Команда.
	 * @return bool
	 */
	public static function team_has( $team_id ) {
		foreach ( WPPA_Teams::participants( (int) $team_id ) as $member ) {
			if ( self::count( (int) $member->ID ) > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Выдать сертификат участнику.
	 *
	 * @param int   $user_id    Участник.
	 * @param int   $curator_id Кто выдаёт.
	 * @param array $args       course, hours, teacher, template_id.
	 * @return array|WP_Error Запись сертификата.
	 */
	public static function issue( $user_id, $curator_id = 0, $args = array() ) {
		$user_id    = (int) $user_id;
		$curator_id = (int) ( $curator_id ? $curator_id : get_current_user_id() );

		if ( ! self::available() ) {
			return new WP_Error( 'wppa_certificate_unavailable', __( 'Выдача сертификатов требует активного плагина WP Diplom.', 'wp-personal-account' ) );
		}

		if ( ! WPPA_Teams::is_curator( $curator_id ) ) {
			return new WP_Error( 'wppa_certificate_forbidden', __( 'Сертификаты выдаёт куратор.', 'wp-personal-account' ) );
		}

		if ( ! WPPA_Teams::team_id( $user_id ) || ! in_array( WPPA_Teams::role( $user_id ), array( 'owner', 'member' ), true ) ) {
			return new WP_Error( 'wppa_certificate_missing', __( 'Участник не найден или не состоит в команде.', 'wp-personal-account' ) );
		}

		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'wppa_certificate_missing', __( 'Участник не найден.', 'wp-personal-account' ) );
		}

		$team_id = (int) WPPA_Teams::team_id( $user_id );
		$team    = get_post( $team_id );
		$course  = isset( $args['course'] ) ? (string) $args['course'] : ( $team ? (string) $team->post_title : '' );
		$teacher = isset( $args['teacher'] ) ? (string) $args['teacher'] : self::teacher_name( $curator_id );

		$custom = array(
			'team'    => $team ? (string) $team->post_title : '',
			'percent' => '',
			'score'   => '',
		);

		$score = self::score_of( $user_id );

		if ( $score ) {
			$custom['percent'] = (string) round( (float) $score['percentage'], 1 );
			$custom['score']   = (int) $score['score'] . '/' . (int) $score['max_score'];
		}

		/**
		 * Фильтр данных сертификата перед выдачей.
		 *
		 * @param array $data    Аргументы WP Diplom.
		 * @param int   $user_id Участник.
		 */
		$data = (array) apply_filters(
			'wppa_certificate_args',
			array(
				'recipient'   => WPPA_Teams::full_name( $user ),
				'email'       => (string) $user->user_email,
				'course'      => $course,
				'hours'       => isset( $args['hours'] ) ? (string) $args['hours'] : '',
				'teacher'     => $teacher,
				'template_id' => isset( $args['template_id'] ) ? (int) $args['template_id'] : self::template_id(),
				'custom'      => $custom,
			),
			$user_id
		);

		$cert_id = WPDC_Certificate::create( $data );

		if ( is_wp_error( $cert_id ) ) {
			return $cert_id;
		}

		$cert_id = (int) $cert_id;

		$sent = false;

		if ( self::send_email() && class_exists( 'WPDC_Email' ) && method_exists( 'WPDC_Email', 'send' ) ) {
			$result = WPDC_Email::send( $cert_id );
			$sent   = ! is_wp_error( $result ) && false !== $result;
		}

		$entry = array(
			'id'     => $cert_id,
			'number' => (string) get_post_meta( $cert_id, '_wpdc_number', true ),
			'code'   => (string) get_post_meta( $cert_id, '_wpdc_code', true ),
			'course' => $course,
			'time'   => time(),
			'by'     => $curator_id,
			'sent'   => $sent,
		);

		$saved = get_user_meta( $user_id, self::META, true );
		$saved = is_array( $saved ) ? $saved : array();

		$saved[ 'cert-' . $cert_id ] = $entry;

		update_user_meta( $user_id, self::META, $saved );

		return $entry;
	}

	/**
	 * Выдать сертификаты всем участникам команды с процентом не ниже порога.
	 *
	 * @param int $team_id    Команда.
	 * @param int $curator_id Куратор.
	 * @return array issued, skipped, failed, errors.
	 */
	public static function issue_team_above_threshold( $team_id, $curator_id = 0 ) {
		$team_id    = (int) $team_id;
		$curator_id = (int) ( $curator_id ? $curator_id : get_current_user_id() );
		$issued     = array();
		$skipped    = array();
		$errors     = array();

		if ( ! self::available() ) {
			return array(
				'issued'  => array(),
				'skipped' => array(),
				'errors'  => array( 'wppa_certificate_unavailable' ),
			);
		}

		if ( ! WPPA_Teams::is_curator( $curator_id ) ) {
			return array(
				'issued'  => array(),
				'skipped' => array(),
				'errors'  => array( 'wppa_certificate_forbidden' ),
			);
		}

		$rows   = WPPA_Participants::rows( $curator_id );
		$scores = WPPA_Participants::scores( $rows );

		foreach ( $rows as $user_id => $row ) {
			if ( (int) $row['team'] !== $team_id ) {
				continue;
			}

			if ( ! isset( $scores[ $user_id ] ) ) {
				$skipped[] = (int) $user_id;
				continue;
			}

			if ( WPPA_Participants::below_threshold( (float) $scores[ $user_id ]['percentage'] ) ) {
				$skipped[] = (int) $user_id;
				continue;
			}

			$result = self::issue( (int) $user_id, $curator_id );

			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_code();
				continue;
			}

			$issued[ (int) $user_id ] = $result;
		}

		return array(
			'issued'  => $issued,
			'skipped' => $skipped,
			'errors'  => $errors,
		);
	}

	/**
	 * Убрать сертификат из кабинета участника (сам сертификат в WP Diplom остаётся).
	 *
	 * @param int $user_id    Участник.
	 * @param int $cert_id    ID сертификата.
	 * @param int $curator_id Куратор.
	 * @return bool
	 */
	public static function remove( $user_id, $cert_id, $curator_id = 0 ) {
		$curator_id = (int) ( $curator_id ? $curator_id : get_current_user_id() );

		if ( ! WPPA_Teams::is_curator( $curator_id ) ) {
			return false;
		}

		$user_id = (int) $user_id;
		$cert_id = (int) $cert_id;
		$saved   = get_user_meta( $user_id, self::META, true );

		if ( ! is_array( $saved ) || ! isset( $saved[ 'cert-' . $cert_id ] ) ) {
			return false;
		}

		unset( $saved[ 'cert-' . $cert_id ] );

		update_user_meta( $user_id, self::META, $saved );

		return true;
	}

	/**
	 * Отозван ли сертификат в WP Diplom (`_wpdc_revoked`).
	 *
	 * @param int $cert_id ID сертификата.
	 * @return bool
	 */
	public static function is_revoked( $cert_id ) {
		return (bool) get_post_meta( (int) $cert_id, '_wpdc_revoked', true );
	}

	/**
	 * Публичная ссылка проверки сертификата: страница проверки WP Diplom
	 * или `/certificate/{код}/`.
	 *
	 * @param int    $cert_id ID сертификата.
	 * @param string $code    Код проверки.
	 * @return string
	 */
	public static function verify_url( $cert_id, $code = '' ) {
		$cert_id = (int) $cert_id;
		$code    = (string) ( '' !== $code ? $code : get_post_meta( $cert_id, '_wpdc_code', true ) );

		if ( '' === $code ) {
			return '';
		}

		$template = (int) get_post_meta( $cert_id, '_wpdc_template', true );

		if ( class_exists( 'WPDC_Certificate' ) && method_exists( 'WPDC_Certificate', 'verify_url' ) ) {
			try {
				$url = (string) WPDC_Certificate::verify_url( $code, $template );

				if ( '' !== $url ) {
					return $url;
				}
			} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Падение плагина не должно ломать кабинет.
				// Молча уходим на запасную ссылку.
			}
		}

		/**
		 * Фильтр ссылки проверки сертификата.
		 *
		 * @param string $url     Ссылка проверки.
		 * @param string $code    Код проверки.
		 * @param int    $cert_id ID сертификата.
		 */
		return (string) apply_filters( 'wppa_certificate_verify_url', home_url( '/certificate/' . rawurlencode( $code ) . '/' ), $code, $cert_id );
	}

	/**
	 * Ссылка на скачивание PDF сертификата.
	 *
	 * @param int $user_id Участник.
	 * @param int $cert_id ID сертификата.
	 * @return string
	 */
	public static function download_url( $user_id, $cert_id ) {
		$user_id = (int) $user_id;
		$cert_id = (int) $cert_id;

		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::DOWNLOAD_ACTION,
					'user'   => $user_id,
					'cert'   => $cert_id,
				),
				admin_url( 'admin-post.php' )
			),
			'wppa_certificate_' . $user_id . '_' . $cert_id
		);
	}

	/**
	 * Имя куратора для подписи.
	 *
	 * @param int $curator_id Куратор.
	 * @return string
	 */
	protected static function teacher_name( $curator_id ) {
		$user = get_userdata( (int) $curator_id );

		if ( ! $user instanceof WP_User ) {
			return '';
		}

		return (string) WPPA_Teams::full_name( $user );
	}

	/**
	 * Баллы участника, если плагин олимпиад активен.
	 *
	 * @param int $user_id Участник.
	 * @return array|null
	 */
	protected static function score_of( $user_id ) {
		if ( ! WPPA_Olympiad::is_active() ) {
			return null;
		}

		$map = WPPA_Olympiad::summary_map( array( (int) $user_id ) );

		return isset( $map[ (int) $user_id ] ) ? $map[ (int) $user_id ] : null;
	}

	/**
	 * Отдача PDF сертификата участнику или куратору.
	 *
	 * @return void
	 */
	public static function handle_download() {
		$user_id = isset( $_GET['user'] ) ? absint( wp_unslash( $_GET['user'] ) ) : 0;
		$cert_id = isset( $_GET['cert'] ) ? absint( wp_unslash( $_GET['cert'] ) ) : 0;

		if ( ! $user_id || ! $cert_id ) {
			wp_die( esc_html__( 'Сертификат не найден.', 'wp-personal-account' ) );
		}

		check_admin_referer( 'wppa_certificate_' . $user_id . '_' . $cert_id );

		if ( ! self::can_view( $user_id ) ) {
			wp_die( esc_html__( 'Этот сертификат вам недоступен.', 'wp-personal-account' ) );
		}

		$files = self::for_user( $user_id );

		if ( ! isset( $files[ 'cert-' . $cert_id ] ) ) {
			wp_die( esc_html__( 'Сертификат не найден.', 'wp-personal-account' ) );
		}

		if ( self::is_revoked( $cert_id ) ) {
			wp_die( esc_html__( 'Сертификат отозван — скачать его нельзя. Обратитесь к куратору.', 'wp-personal-account' ) );
		}

		if ( ! self::available() ) {
			wp_die( esc_html__( 'Для скачивания нужен активный плагин WP Diplom.', 'wp-personal-account' ) );
		}

		$path = WPDC_Certificate::pdf( $cert_id );

		if ( is_wp_error( $path ) ) {
			wp_die( esc_html( $path->get_error_message() ) );
		}

		$contents = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		nocache_headers();

		header( 'Content-Type: application/pdf' );
		header( 'Content-Length: ' . strlen( $contents ) );
		header( 'Content-Disposition: attachment; filename="certificate-' . (int) $cert_id . '.pdf"' );

		echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Бинарный PDF.
		exit;
	}

	/**
	 * Обработка форм выдачи и удаления сертификатов.
	 *
	 * @return void
	 */
	public static function handle_request() {
		check_admin_referer( 'wppa_certificate', 'wppa_nonce' );

		$curator_id = get_current_user_id();
		$back       = WPPA_Plugin::tab_url( 'participants' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce проверен выше.
		$sub    = isset( $_POST['certificate_action'] ) ? sanitize_key( wp_unslash( $_POST['certificate_action'] ) ) : '';
		$user   = isset( $_POST['participant_id'] ) ? absint( wp_unslash( $_POST['participant_id'] ) ) : 0;
		$team   = isset( $_POST['team_id'] ) ? absint( wp_unslash( $_POST['team_id'] ) ) : 0;
		$cert   = isset( $_POST['certificate_id'] ) ? absint( wp_unslash( $_POST['certificate_id'] ) ) : 0;
		// phpcs:enable

		if ( $team ) {
			$back = add_query_arg( 'wppa_team', $team, $back );
		}

		if ( ! WPPA_Teams::is_curator( $curator_id ) ) {
			WPPA_Notices::redirect( 'certificate_forbidden', $back );
		}

		if ( ! self::available() ) {
			WPPA_Notices::redirect( 'certificate_unavailable', $back );
		}

		if ( 'remove' === $sub ) {
			WPPA_Notices::redirect( self::remove( $user, $cert, $curator_id ) ? 'certificate_removed' : 'certificate_missing', $back );
		}

		if ( 'issue_threshold' === $sub ) {
			$batch = self::issue_team_above_threshold( $team, $curator_id );

			if ( empty( $batch['issued'] ) ) {
				WPPA_Notices::redirect( 'certificate_nobody', $back );
			}

			WPPA_Notices::redirect( count( $batch['issued'] ) > 1 ? 'certificate_issued_many' : 'certificate_issued', $back );
		}

		$result = self::issue( $user, $curator_id );

		if ( is_wp_error( $result ) ) {
			WPPA_Notices::redirect( 'wppa_certificate_missing' === $result->get_error_code() ? 'certificate_missing' : 'certificate_failed', $back );
		}

		WPPA_Notices::redirect( 'certificate_issued', $back );
	}
}
