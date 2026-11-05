<?php
/**
 * Файлы куратора для наставника команды.
 *
 * Куратор прикрепляет документы к команде, наставник этой команды их видит
 * и скачивает. Файлы лежат вне медиатеки, в отдельной папке, и отдаются только
 * через проверяемую точку входа — прямой адрес ничего не даёт посторонним.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Curator_Files
 */
class WPPA_Curator_Files {

	/**
	 * Мета команды: список файлов.
	 */
	const META = '_wppa_curator_files';

	/**
	 * Сколько файлов можно прикрепить к одной команде.
	 */
	const MAX_FILES = 20;

	/**
	 * Подпапка в uploads.
	 */
	const SUBDIR = 'wppa-curator';

	/**
	 * Точка входа для скачивания.
	 */
	const DOWNLOAD_ACTION = 'wppa_curator_file';

	/**
	 * Максимальный размер файла в байтах.
	 *
	 * @return int
	 */
	public static function max_size() {
		$size = 10 * 1024 * 1024;

		/**
		 * Фильтр максимального размера файла куратора.
		 *
		 * @param int $size Байты.
		 */
		return (int) apply_filters( 'wppa_curator_file_max_size', $size );
	}

	/**
	 * Разрешённые расширения.
	 *
	 * @return array
	 */
	public static function types() {
		$types = array( 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'odt', 'ods', 'odp', 'zip', 'rar', '7z', 'jpg', 'jpeg', 'png', 'gif', 'webp' );

		/**
		 * Фильтр разрешённых расширений файлов куратора.
		 *
		 * @param array $types Расширения.
		 */
		return (array) apply_filters( 'wppa_curator_file_types', $types );
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
	 * Файлы отдаются через admin-post с проверкой роли, поэтому прямой адрес
	 * не нужен: даже если папка откроется по URL, имя файла содержит случайный ключ.
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
	 * Файлы команды (сначала новые).
	 *
	 * @param int $team_id ID команды.
	 * @return array Список: key, name, file, size, time, user.
	 */
	public static function files( $team_id ) {
		$team_id = (int) $team_id;

		if ( ! $team_id ) {
			return array();
		}

		$saved = get_post_meta( $team_id, self::META, true );

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
				'from' => isset( $entry['from'] ) && 'mentor' === $entry['from'] ? 'mentor' : 'curator',
				'ext'  => strtolower( (string) pathinfo( (string) $entry['file'], PATHINFO_EXTENSION ) ),
			);
		}

		return $out;
	}

	/**
	 * Сколько файлов у команды.
	 *
	 * @param int $team_id ID команды.
	 * @return int
	 */
	public static function count( $team_id ) {
		return count( self::files( $team_id ) );
	}

	/**
	 * Адрес файла для скачивания.
	 *
	 * @param int    $team_id ID команды.
	 * @param string $key     Ключ файла.
	 * @return string
	 */
	public static function download_url( $team_id, $key ) {
		return add_query_arg(
			array(
				'action'   => self::DOWNLOAD_ACTION,
				'team'     => (int) $team_id,
				'file'     => rawurlencode( (string) $key ),
				'_wpnonce' => wp_create_nonce( 'wppa_file_' . (int) $team_id . '_' . (string) $key ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Кому показывать вкладку «Файлы».
	 *
	 * Куратору вкладка нужна всегда: он прикрепляет файлы любой команде.
	 * Наставнику — когда есть что скачать (куратор уже присылал файлы) или
	 * когда команда прошла во второй этап (тогда он может прикреплять сам).
	 * Остальным участникам — когда куратор что-то прислал их команде.
	 *
	 * @param int $user_id Пользователь.
	 * @return bool
	 */
	public static function can_use_tab( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		if ( WPPA_Teams::is_curator( $user_id ) ) {
			return true;
		}

		$team_id = (int) WPPA_Teams::team_id( $user_id );

		if ( ! $team_id ) {
			return false;
		}

		if ( WPPA_Teams::is_mentor( $user_id ) ) {
			return self::has_curator_files( $team_id ) || WPPA_Teams::is_stage2( $team_id );
		}

		return self::has_curator_files( $team_id );
	}

	/**
	 * Есть ли у команды файлы от куратора.
	 *
	 * @param int $team_id ID команды.
	 * @return bool
	 */
	public static function has_curator_files( $team_id ) {
		foreach ( self::files( (int) $team_id ) as $entry ) {
			if ( 'mentor' !== $entry['from'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Может ли пользователь смотреть файлы команды.
	 *
	 * Наставник этой команды, куратор, администратор сайта и участник команды —
	 * но участнику показываем раздел, только когда куратор уже прислал файлы.
	 *
	 * @param int $team_id ID команды.
	 * @param int $user_id ID пользователя (0 — текущий).
	 * @return bool
	 */
	public static function can_view( $team_id, $user_id = 0 ) {
		$team_id = (int) $team_id;
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! $team_id || ! $user_id || WPPA_Teams::CPT !== get_post_type( $team_id ) ) {
			return false;
		}

		if ( WPPA_Teams::is_curator( $user_id ) ) {
			return true;
		}

		if ( current_user_can( 'manage_options' ) && (int) $user_id === get_current_user_id() ) {
			return true;
		}

		if ( (int) WPPA_Teams::team_id( $user_id ) !== $team_id ) {
			return false;
		}

		if ( WPPA_Teams::is_mentor( $user_id ) ) {
			return true;
		}

		// Участник команды видит раздел, когда в нём есть документы куратора.
		return self::has_curator_files( $team_id );
	}

	/**
	 * Может ли пользователь прикреплять и удалять файлы.
	 *
	 * @param int $team_id ID команды.
	 * @param int $user_id ID пользователя (0 — текущий).
	 * @return bool
	 */
	public static function can_manage( $team_id, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! $user_id || WPPA_Teams::CPT !== get_post_type( (int) $team_id ) ) {
			return false;
		}

		// Прикреплять файлы может куратор (любой команде) и наставник своей команды.
		return '' !== self::direction( (int) $team_id, $user_id );
	}

	/**
	 * В каком качестве пользователь прикрепляет файлы к команде.
	 *
	 * @param int $team_id ID команды.
	 * @param int $user_id Пользователь.
	 * @return string curator|mentor|пустая строка, если прав нет.
	 */
	public static function can_attach( $team_id, $user_id = 0 ) {
		$team_id = (int) $team_id;
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! self::can_manage( $team_id, $user_id ) ) {
			return false;
		}

		// Куратор прикрепляет документы всегда, наставник — после второго этапа.
		if ( WPPA_Teams::is_curator( $user_id ) ) {
			return true;
		}

		return WPPA_Teams::is_stage2( $team_id );
	}

	/**
	 * Ждёт ли наставник отметки «второй этап», чтобы прикрепить файл.
	 *
	 * @param int $team_id ID команды.
	 * @param int $user_id Кто смотрит.
	 * @return bool
	 */
	public static function waiting_stage2( $team_id, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		return 'mentor' === self::direction( $team_id, $user_id ) && ! WPPA_Teams::is_stage2( (int) $team_id );
	}

	/**
	 * Направление обмена файлами.
	 *
	 * @param int $team_id ID команды.
	 * @param int $user_id Кто смотрит.
	 * @return string
	 */
	public static function direction( $team_id, $user_id = 0 ) {
		$team_id = (int) $team_id;
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! $team_id || ! $user_id ) {
			return '';
		}

		if ( WPPA_Teams::is_curator( $user_id ) ) {
			return 'curator';
		}

		if ( (int) WPPA_Teams::team_id( $user_id ) === $team_id && WPPA_Teams::is_mentor( $user_id ) ) {
			return 'mentor';
		}

		return '';
	}

	/**
	 * Может ли пользователь удалить конкретный файл.
	 *
	 * Куратор удаляет любые файлы команды, наставник — только те, что прикрепил сам.
	 *
	 * @param int    $team_id ID команды.
	 * @param string $key     Ключ файла.
	 * @param int    $user_id Пользователь.
	 * @return bool
	 */
	public static function can_remove( $team_id, $key, $user_id = 0 ) {
		$direction = self::direction( (int) $team_id, $user_id );

		if ( 'curator' === $direction ) {
			return true;
		}

		if ( 'mentor' !== $direction ) {
			return false;
		}

		$files = self::files( (int) $team_id );

		return isset( $files[ (string) $key ] ) && 'mentor' === $files[ (string) $key ]['from'];
	}

	/**
	 * Прикрепить уже проверенный файл.
	 *
	 * Отдельный метод от upload(): позволяет обойтись без move_uploaded_file,
	 * поэтому его можно проверять в тестах.
	 *
	 * @param int    $team_id  ID команды.
	 * @param string $path     Путь к существующему файлу.
	 * @param string $name     Исходное имя.
	 * @param int    $user_id  Кто прикрепляет.
	 * @return array|WP_Error ok, key, name.
	 */
	public static function store( $team_id, $path, $name, $user_id = 0 ) {
		$team_id = (int) $team_id;
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		$from = self::direction( $team_id, $user_id );

		if ( '' === $from ) {
			return new WP_Error( 'wppa_file_forbidden', __( 'Прикреплять файлы может куратор и наставник команды.', 'wp-personal-account' ) );
		}

		// Наставник получает возможность прикреплять файлы, когда команда прошла
		// во второй этап. Участники этого делать не могут никогда.
		if ( 'mentor' === $from && ! WPPA_Teams::is_stage2( $team_id ) ) {
			return new WP_Error(
				'wppa_file_stage2',
				__( 'Прикреплять файлы можно после того, как команда прошла во второй этап.', 'wp-personal-account' )
			);
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

		if ( self::count( $team_id ) >= self::MAX_FILES ) {
			return new WP_Error( 'wppa_file_limit', __( 'К команде уже прикреплено максимум файлов.', 'wp-personal-account' ) );
		}

		if ( ! self::ensure_dir() ) {
			return new WP_Error( 'wppa_file_dir', __( 'Не удалось создать папку для файлов.', 'wp-personal-account' ) );
		}

		$saved = self::files( $team_id );
		$key   = strtolower( wp_generate_password( 16, false, false ) );
		$tries = 0;

		// Ключ должен быть уникальным: он же имя файла в папке и адрес скачивания.
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
			'user' => (int) $user_id,
			'from' => $from,
		);

		update_post_meta( $team_id, self::META, $files );

		// Файл наставника сразу уходит в сетевую папку команды.
		$export = array(
			'ok'      => false,
			'message' => '',
			'file'    => '',
		);

		if ( 'mentor' === $from && class_exists( 'WPPA_Team_Files_Export' ) ) {
			$export = WPPA_Team_Files_Export::export( $team_id, self::dir()['dir'] . '/' . $file, $name, $key );
		}

		return array(
			'ok'     => true,
			'key'    => $key,
			'name'   => $name,
			'export' => $export,
		);
	}

	/**
	 * Прикрепить загруженный файл из формы.
	 *
	 * @param int   $team_id ID команды.
	 * @param array $file    Элемент $_FILES.
	 * @param int   $user_id Кто прикрепляет.
	 * @return array|WP_Error
	 */
	public static function upload( $team_id, $file, $user_id = 0 ) {
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

		// Загруженный файл обязан быть результатом загрузки, а не путём из запроса.
		if ( ! is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'wppa_file_upload', __( 'Не удалось загрузить файл.', 'wp-personal-account' ) );
		}

		return self::store( $team_id, $tmp, (string) $file['name'], $user_id );
	}

	/**
	 * Удалить файл команды.
	 *
	 * @param int    $team_id ID команды.
	 * @param string $key     Ключ файла.
	 * @param int    $user_id Кто удаляет.
	 * @return bool
	 */
	public static function remove( $team_id, $key, $user_id = 0 ) {
		$team_id = (int) $team_id;
		$key     = (string) $key;

		if ( ! self::can_remove( $team_id, $key, $user_id ) ) {
			return false;
		}

		$files = self::files( $team_id );

		if ( ! isset( $files[ $key ] ) ) {
			return false;
		}

		$path = self::dir()['dir'] . '/' . $files[ $key ]['file'];

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
		if ( is_file( $path ) ) {
			@unlink( $path );
		}

		$was_mentor = 'mentor' === $files[ $key ]['from'];

		unset( $files[ $key ] );
		update_post_meta( $team_id, self::META, $files );

		// Копия в сетевой папке больше не нужна.
		if ( $was_mentor && class_exists( 'WPPA_Team_Files_Export' ) ) {
			WPPA_Team_Files_Export::remove( $team_id, $key );
		}

		// Письмо «для команды прикреплены файлы» больше не актуально:
		// убираем письма этой команды из очереди рассылки о файлах.
		if ( ! $was_mentor && class_exists( 'WPPA_Mailing_Files' ) ) {
			WPPA_Mailing_Files::drop_team( $team_id );
		}

		return true;
	}

	/**
	 * Удалить все файлы команды (при роспуске команды).
	 *
	 * @param int $team_id ID команды.
	 * @return void
	 */
	public static function clear( $team_id ) {
		$dir = self::dir()['dir'];

		foreach ( self::files( $team_id ) as $file ) {
			$path = $dir . '/' . $file['file'];

			if ( is_file( $path ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $path );
			}
		}

		delete_post_meta( (int) $team_id, self::META );

		if ( class_exists( 'WPPA_Team_Files_Export' ) ) {
			WPPA_Team_Files_Export::clear( (int) $team_id );
		}

		// Файлов не осталось — письма этой команде из очереди рассылки о файлах убираем.
		if ( class_exists( 'WPPA_Mailing_Files' ) ) {
			WPPA_Mailing_Files::drop_team( (int) $team_id );
		}
	}

	/**
	 * Обработка формы: прикрепить или удалить файл.
	 *
	 * @return void
	 */
	public static function handle_request() {
		check_admin_referer( 'wppa_curator_file', 'wppa_nonce' );

		$user_id = get_current_user_id();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce проверен выше.
		$sub = isset( $_POST['curator_file_action'] ) ? sanitize_key( wp_unslash( $_POST['curator_file_action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce проверен выше.
		$posted_team = isset( $_POST['team_id'] ) ? absint( wp_unslash( $_POST['team_id'] ) ) : 0;

		// Куратор работает с любой командой, наставник — только со своей.
		$team_id = $posted_team ? $posted_team : (int) WPPA_Teams::team_id( $user_id );
		$is_curator = $user_id && WPPA_Teams::is_curator( $user_id );

		// Откуда пришла форма: из раздела «Файлы» возвращаем туда же, а не в карточку команды.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce проверен выше.
		$stay = isset( $_POST['wppa_back'] ) ? sanitize_key( wp_unslash( $_POST['wppa_back'] ) ) : '';

		if ( $is_curator ) {
			if ( 'files' === $stay ) {
				$back = $team_id
					? add_query_arg( 'wppa_team', (int) $team_id, WPPA_Plugin::tab_url( 'files' ) )
					: WPPA_Plugin::tab_url( 'files' );
			} else {
				$back = $team_id ? add_query_arg( 'wppa_team', $team_id, WPPA_Plugin::tab_url( 'team' ) ) : WPPA_Plugin::tab_url( 'team' );
			}
		} else {
			$back = WPPA_Plugin::tab_url( 'files' );
		}

		if ( ! $user_id || '' === self::direction( $team_id, $user_id ) ) {
			WPPA_Notices::redirect( 'file_forbidden', $back );
		}

		if ( ! $team_id || WPPA_Teams::CPT !== get_post_type( $team_id ) ) {
			WPPA_Notices::redirect( 'file_team_missing', $back );
		}

		if ( 'remove' === $sub ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce проверен выше.
			$key = isset( $_POST['file_key'] ) ? sanitize_text_field( wp_unslash( $_POST['file_key'] ) ) : '';

			if ( ! self::can_remove( $team_id, $key, $user_id ) ) {
				WPPA_Notices::redirect( 'file_forbidden', $back );
			}

			WPPA_Notices::redirect( self::remove( $team_id, $key, $user_id ) ? 'file_removed' : 'file_missing', $back );
		}

		if ( 'upload' !== $sub ) {
			WPPA_Notices::redirect( '', $back );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- проверяется в upload().
		$file   = isset( $_FILES['curator_file'] ) ? $_FILES['curator_file'] : array();
		$result = self::upload( $team_id, $file, $user_id );

		if ( is_wp_error( $result ) ) {
			WPPA_Notices::redirect( self::notice_code( $result->get_error_code() ), $back );
		}

		// Файл прикреплён, но в сетевую папку не попал — скажем об этом отдельно.
		if ( ! empty( $result['export'] ) && empty( $result['export']['ok'] ) ) {
			WPPA_Notices::redirect( 'file_export_failed', $back );
		}

		WPPA_Notices::redirect( 'file_added', $back );
	}

	/**
	 * Код уведомления по коду ошибки загрузки.
	 *
	 * @param string $code Код WP_Error.
	 * @return string
	 */
	public static function notice_code( $code ) {
		$map = array(
			'wppa_file_type'      => 'file_type',
			'wppa_file_size'      => 'file_size',
			'wppa_file_empty'     => 'file_empty',
			'wppa_file_limit'     => 'file_limit',
			'wppa_file_forbidden' => 'file_forbidden',
			'wppa_file_dir'       => 'file_save_failed',
			'wppa_file_copy'      => 'file_save_failed',
			'wppa_file_stage2'    => 'file_stage2',
		);

		return isset( $map[ $code ] ) ? $map[ $code ] : 'file_missing';
	}

	/**
	 * Отдача файла наставнику или куратору.
	 *
	 * @return void
	 */
	public static function handle_download() {
		$user_id = get_current_user_id();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonce проверяется ниже.
		$team_id = isset( $_GET['team'] ) ? absint( wp_unslash( $_GET['team'] ) ) : 0;
		$key     = isset( $_GET['file'] ) ? sanitize_text_field( wp_unslash( $_GET['file'] ) ) : '';
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		// phpcs:enable

		if ( ! $user_id ) {
			auth_redirect();
		}

		if ( ! wp_verify_nonce( $nonce, 'wppa_file_' . $team_id . '_' . $key ) || ! self::can_view( $team_id, $user_id ) ) {
			wp_die(
				esc_html__( 'Файл недоступен.', 'wp-personal-account' ),
				esc_html__( 'Доступ закрыт', 'wp-personal-account' ),
				array( 'response' => 403 )
			);
		}

		$files = self::files( $team_id );

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
		if ( function_exists( 'size_format' ) ) {
			return size_format( (int) $bytes );
		}

		return number_format_i18n( (int) $bytes ) . ' B';
	}

	/**
	 * Перемещение файла: move_uploaded_file, а если он недоступен — rename/copy.
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
