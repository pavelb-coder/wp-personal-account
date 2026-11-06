<?php
/**
 * Документы конкретного участника: куратор прикрепляет файл участнику команды.
 *
 * Файлы команды (`WPPA_Curator_Files`) видит вся команда, а эти — адресные:
 * мета `_wppa_participant_files` хранится у пользователя, папка —
 * `uploads/wppa-participants/`. Скачать может куратор и сам участник.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Класс адресных документов участника.
 */
class WPPA_Participant_Files {

	/**
	 * Мета пользователя со списком файлов.
	 */
	const META = '_wppa_participant_files';

	/**
	 * Имя действия скачивания (admin-post).
	 */
	const DOWNLOAD_ACTION = 'wppa_participant_download';

	/**
	 * Подпапка в uploads.
	 */
	const SUBDIR = 'wppa-participants';

	/**
	 * Сколько файлов можно прикрепить одному участнику.
	 */
	const MAX_FILES = 20;

	/**
	 * Максимальный размер файла — как у файлов команды.
	 *
	 * @return int
	 */
	public static function max_size() {
		return (int) WPPA_Curator_Files::max_size();
	}

	/**
	 * Разрешённые расширения.
	 *
	 * @return array
	 */
	public static function types() {
		/**
		 * Фильтр разрешённых расширений документов участника.
		 *
		 * @param array $types Расширения.
		 */
		return (array) apply_filters( 'wppa_participant_file_types', WPPA_Curator_Files::types() );
	}

	/**
	 * Подписка на события.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( __CLASS__, 'handle_download' ) );
	}

	/**
	 * Папка для файлов: путь и адрес.
	 *
	 * @return array dir, url.
	 */
	public static function dir() {
		$uploads = function_exists( 'wp_get_upload_dir' ) ? wp_get_upload_dir() : array();

		$fallback_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/uploads' : sys_get_temp_dir() . '/wppa-uploads';

		$base_dir = ! empty( $uploads['basedir'] ) ? (string) $uploads['basedir'] : $fallback_dir;
		$base_url = ! empty( $uploads['baseurl'] ) ? (string) $uploads['baseurl'] : content_url( '/uploads' );

		return array(
			'dir' => rtrim( $base_dir, '/\\' ) . '/' . self::SUBDIR,
			'url' => rtrim( $base_url, '/' ) . '/' . self::SUBDIR,
		);
	}

	/**
	 * Создать папку и закрыть её от прямого доступа.
	 *
	 * @return bool
	 */
	public static function ensure_dir() {
		$dir = self::dir()['dir'];

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$index = $dir . '/index.php';

		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		$htaccess = $dir . '/.htaccess';

		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $htaccess, "Require all denied\nDeny from all\n" );
		}

		return true;
	}

	/**
	 * Может ли пользователь прикреплять документы участникам: только куратор.
	 *
	 * @param int $user_id Пользователь.
	 * @return bool
	 */
	public static function can_manage( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		return $user_id && WPPA_Teams::is_curator( $user_id );
	}

	/**
	 * Является ли пользователь участником команды (капитан или участник, но не наставник).
	 *
	 * @param int $user_id Пользователь.
	 * @return bool
	 */
	public static function is_participant( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 || ! WPPA_Teams::team_id( $user_id ) ) {
			return false;
		}

		return in_array( WPPA_Teams::role( $user_id ), array( 'owner', 'member' ), true );
	}

	/**
	 * Может ли пользователь видеть документы участника: куратор или сам участник.
	 *
	 * @param int $participant_id Участник.
	 * @param int $viewer_id      Кто смотрит.
	 * @return bool
	 */
	public static function can_view( $participant_id, $viewer_id = 0 ) {
		$viewer_id = $viewer_id ? (int) $viewer_id : get_current_user_id();

		if ( ! $viewer_id ) {
			return false;
		}

		if ( self::can_manage( $viewer_id ) ) {
			return true;
		}

		return (int) $participant_id === $viewer_id;
	}

	/**
	 * Файлы участника (сначала новые).
	 *
	 * @param int $user_id Участник.
	 * @return array
	 */
	public static function files( $user_id ) {
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
			if ( ! is_array( $entry ) || empty( $entry['file'] ) ) {
				continue;
			}

			$out[ (string) $key ] = array(
				'key'  => (string) $key,
				'name' => ! empty( $entry['name'] ) ? (string) $entry['name'] : (string) $entry['file'],
				'file' => (string) $entry['file'],
				'size' => isset( $entry['size'] ) ? (int) $entry['size'] : 0,
				'time' => isset( $entry['time'] ) ? (int) $entry['time'] : 0,
				'user' => isset( $entry['user'] ) ? (int) $entry['user'] : 0,
				'ext'  => strtolower( (string) pathinfo( (string) $entry['file'], PATHINFO_EXTENSION ) ),
			);
		}

		return $out;
	}

	/**
	 * Сколько документов у участника.
	 *
	 * @param int $user_id Участник.
	 * @return int
	 */
	public static function count( $user_id ) {
		return count( self::files( $user_id ) );
	}

	/**
	 * Ссылка на скачивание.
	 *
	 * @param int    $user_id Участник.
	 * @param string $key     Ключ файла.
	 * @return string
	 */
	public static function download_url( $user_id, $key ) {
		return add_query_arg(
			array(
				'action'   => self::DOWNLOAD_ACTION,
				'user'     => (int) $user_id,
				'file'     => rawurlencode( (string) $key ),
				'_wpnonce' => wp_create_nonce( 'wppa_participant_file_' . (int) $user_id . '_' . (string) $key ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Сохранить файл участнику.
	 *
	 * @param int    $user_id      Участник.
	 * @param string $path         Путь к файлу.
	 * @param string $name         Имя файла.
	 * @param int    $curator_id   Кто прикрепляет.
	 * @return array|WP_Error
	 */
	public static function store( $user_id, $path, $name, $curator_id = 0 ) {
		$user_id    = (int) $user_id;
		$curator_id = $curator_id ? (int) $curator_id : get_current_user_id();

		if ( ! self::can_manage( $curator_id ) ) {
			return new WP_Error( 'wppa_participant_forbidden', __( 'Документы участнику прикрепляет куратор.', 'wp-personal-account' ) );
		}

		if ( ! self::is_participant( $user_id ) ) {
			return new WP_Error( 'wppa_participant_missing', __( 'Участник не найден или не состоит в команде.', 'wp-personal-account' ) );
		}

		if ( ! is_file( $path ) ) {
			return new WP_Error( 'wppa_file_missing', __( 'Файл не найден.', 'wp-personal-account' ) );
		}

		$name = sanitize_file_name( (string) $name );

		if ( '' === $name ) {
			$name = 'file';
		}

		$ext = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( '' === $ext || ! in_array( $ext, self::types(), true ) ) {
			return new WP_Error( 'wppa_file_type', __( 'Такой тип файла прикрепить нельзя.', 'wp-personal-account' ) );
		}

		$size = (int) filesize( $path );

		if ( $size <= 0 ) {
			return new WP_Error( 'wppa_file_empty', __( 'Файл пустой.', 'wp-personal-account' ) );
		}

		if ( $size > self::max_size() ) {
			return new WP_Error(
				'wppa_file_size',
				sprintf(
					/* translators: %s: максимальный размер */
					__( 'Файл больше допустимого размера (%s).', 'wp-personal-account' ),
					size_format( self::max_size() )
				)
			);
		}

		if ( self::count( $user_id ) >= self::MAX_FILES ) {
			return new WP_Error( 'wppa_file_limit', __( 'К участнику уже прикреплено максимум файлов.', 'wp-personal-account' ) );
		}

		if ( ! self::ensure_dir() ) {
			return new WP_Error( 'wppa_file_dir', __( 'Не удалось создать папку для файлов.', 'wp-personal-account' ) );
		}

		$saved = self::files( $user_id );
		$key   = strtolower( wp_generate_password( 16, false, false ) );
		$tries = 0;

		while ( ( isset( $saved[ $key ] ) || file_exists( self::dir()['dir'] . '/' . $key . '-' . $name ) ) && $tries < 5 ) {
			++$tries;
			$key = strtolower( wp_generate_password( 16, false, false ) ) . $tries;
		}

		$file = $key . '-' . $name;
		$dest = self::dir()['dir'] . '/' . $file;

		if ( ! self::move( $path, $dest ) ) {
			return new WP_Error( 'wppa_file_copy', __( 'Не удалось сохранить файл.', 'wp-personal-account' ) );
		}

		$files         = $saved;
		$files[ $key ] = array(
			'name' => $name,
			'file' => $file,
			'size' => $size,
			'time' => time(),
			'user' => $curator_id,
		);

		update_user_meta( $user_id, self::META, $files );

		return array(
			'ok'   => true,
			'key'  => $key,
			'name' => $name,
		);
	}

	/**
	 * Прикрепить загруженный файл из формы.
	 *
	 * @param int   $user_id Участник.
	 * @param array $file    Элемент $_FILES.
	 * @param int   $curator_id Кто прикрепляет.
	 * @return array|WP_Error
	 */
	public static function upload( $user_id, $file, $curator_id = 0 ) {
		if ( ! is_array( $file ) || ! isset( $file['tmp_name'], $file['name'] ) ) {
			return new WP_Error( 'wppa_file_missing', __( 'Файл не выбран.', 'wp-personal-account' ) );
		}

		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_NO_FILE === $error ) {
			return new WP_Error( 'wppa_file_missing', __( 'Файл не выбран.', 'wp-personal-account' ) );
		}

		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'wppa_file_upload', __( 'Не удалось загрузить файл: сервер отклонил загрузку.', 'wp-personal-account' ) );
		}

		$tmp = (string) $file['tmp_name'];

		if ( ! WPPA_Curator_Files::is_uploaded( $tmp ) ) {
			return new WP_Error( 'wppa_file_upload', __( 'Не удалось загрузить файл.', 'wp-personal-account' ) );
		}

		return self::store( $user_id, $tmp, (string) $file['name'], $curator_id );
	}

	/**
	 * Прикрепить несколько файлов участнику за раз.
	 *
	 * @param int   $user_id Участник.
	 * @param array $files   $_FILES-элемент или список файлов.
	 * @param int   $curator_id Кто прикрепляет.
	 * @return array added, errors, count_added, count_failed.
	 */
	public static function upload_many( $user_id, $files, $curator_id = 0 ) {
		$list   = WPPA_Curator_Files::normalize_files( $files );
		$added  = array();
		$errors = array();

		foreach ( $list as $file ) {
			$result = self::upload( $user_id, $file, $curator_id );

			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_code();
				continue;
			}

			$added[] = $result;
		}

		return array(
			'added'        => $added,
			'errors'       => $errors,
			'count_added'  => count( $added ),
			'count_failed' => count( $errors ),
		);
	}

	/**
	 * Удалить документ участника.
	 *
	 * @param int    $user_id Участник.
	 * @param string $key     Ключ файла.
	 * @param int    $curator_id Кто удаляет.
	 * @return bool
	 */
	public static function remove( $user_id, $key, $curator_id = 0 ) {
		$user_id = (int) $user_id;
		$key     = (string) $key;

		if ( ! self::can_manage( $curator_id ) ) {
			return false;
		}

		$files = self::files( $user_id );

		if ( ! isset( $files[ $key ] ) ) {
			return false;
		}

		$path = self::dir()['dir'] . '/' . $files[ $key ]['file'];

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
		if ( is_file( $path ) ) {
			@unlink( $path );
		}

		unset( $files[ $key ] );
		update_user_meta( $user_id, self::META, $files );

		return true;
	}

	/**
	 * Удалить все документы участника (например, при выходе из команды).
	 *
	 * @param int $user_id Участник.
	 * @return void
	 */
	public static function clear( $user_id ) {
		$dir = self::dir()['dir'];

		foreach ( self::files( $user_id ) as $file ) {
			$path = $dir . '/' . $file['file'];

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			if ( is_file( $path ) ) {
				@unlink( $path );
			}
		}

		delete_user_meta( (int) $user_id, self::META );
	}

	/**
	 * Обработка формы: прикрепить или удалить документ участника.
	 *
	 * @return void
	 */
	public static function handle_request() {
		check_admin_referer( 'wppa_participant_file', 'wppa_nonce' );

		$curator_id = get_current_user_id();
		$back       = WPPA_Plugin::tab_url( 'files' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce проверен выше.
		$sub            = isset( $_POST['participant_file_action'] ) ? sanitize_key( wp_unslash( $_POST['participant_file_action'] ) ) : '';
		$participant_id = isset( $_POST['participant_id'] ) ? absint( wp_unslash( $_POST['participant_id'] ) ) : 0;
		$team_id        = isset( $_POST['team_id'] ) ? absint( wp_unslash( $_POST['team_id'] ) ) : 0;
		// phpcs:enable

		if ( $team_id ) {
			$back = add_query_arg( 'wppa_team', $team_id, $back );
		}

		if ( ! self::can_manage( $curator_id ) ) {
			WPPA_Notices::redirect( 'participant_file_forbidden', $back );
		}

		if ( ! self::is_participant( $participant_id ) ) {
			WPPA_Notices::redirect( 'participant_missing', $back );
		}

		if ( 'remove' === $sub ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce проверен выше.
			$key = isset( $_POST['file_key'] ) ? sanitize_text_field( wp_unslash( $_POST['file_key'] ) ) : '';

			WPPA_Notices::redirect( self::remove( $participant_id, $key, $curator_id ) ? 'participant_file_removed' : 'file_missing', $back );
		}

		if ( 'upload' !== $sub ) {
			WPPA_Notices::redirect( '', $back );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- проверяется в upload().
		$files = isset( $_FILES['participant_files'] ) ? $_FILES['participant_files'] : array();

		if ( ! $files ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- проверяется в upload().
			$files = isset( $_FILES['participant_file'] ) ? $_FILES['participant_file'] : array();
		}

		$result = self::upload_many( $participant_id, $files, $curator_id );

		if ( 0 === $result['count_added'] ) {
			$code = ! empty( $result['errors'] ) ? WPPA_Curator_Files::notice_code( (string) $result['errors'][0] ) : 'file_empty';
			WPPA_Notices::redirect( $code, $back );
		}

		if ( $result['count_failed'] > 0 ) {
			WPPA_Notices::redirect( 'participant_files_added_partial', $back );
		}

		WPPA_Notices::redirect( $result['count_added'] > 1 ? 'participant_files_added' : 'participant_file_added', $back );
	}

	/**
	 * Скачивание документа участника.
	 *
	 * @return void
	 */
	public static function handle_download() {
		$viewer_id = get_current_user_id();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonce проверяется ниже.
		$user_id = isset( $_GET['user'] ) ? absint( wp_unslash( $_GET['user'] ) ) : 0;
		$key     = isset( $_GET['file'] ) ? sanitize_text_field( wp_unslash( $_GET['file'] ) ) : '';
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		// phpcs:enable

		if ( ! $viewer_id ) {
			auth_redirect();
		}

		if ( ! wp_verify_nonce( $nonce, 'wppa_participant_file_' . $user_id . '_' . $key ) || ! self::can_view( $user_id, $viewer_id ) ) {
			wp_die(
				esc_html__( 'Файл недоступен.', 'wp-personal-account' ),
				esc_html__( 'Доступ закрыт', 'wp-personal-account' ),
				array( 'response' => 403 )
			);
		}

		$files = self::files( $user_id );

		if ( ! isset( $files[ $key ] ) ) {
			wp_die(
				esc_html__( 'Файл не найден.', 'wp-personal-account' ),
				esc_html__( 'Файл не найден', 'wp-personal-account' ),
				array( 'response' => 404 )
			);
		}

		$path = self::dir()['dir'] . '/' . $files[ $key ]['file'];

		if ( ! is_file( $path ) ) {
			wp_die(
				esc_html__( 'Файл удалён с сервера.', 'wp-personal-account' ),
				esc_html__( 'Файл не найден', 'wp-personal-account' ),
				array( 'response' => 404 )
			);
		}

		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $files[ $key ]['name'] ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );
		exit;
	}

	/**
	 * Размер файла в человекочитаемом виде.
	 *
	 * @param int $bytes Байты.
	 * @return string
	 */
	public static function size_label( $bytes ) {
		return WPPA_Curator_Files::size_label( $bytes );
	}

	/**
	 * Перемещение файла: move_uploaded_file, а если он недоступен — copy.
	 *
	 * @param string $from Источник.
	 * @param string $to   Назначение.
	 * @return bool
	 */
	private static function move( $from, $to ) {
		if ( is_uploaded_file( $from ) && move_uploaded_file( $from, $to ) ) {
			return true;
		}

		if ( @copy( $from, $to ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $from );

			return true;
		}

		return false;
	}
}
