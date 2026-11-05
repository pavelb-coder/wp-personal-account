<?php
/**
 * Автоматическая выгрузка файлов наставника в сетевую папку.
 *
 * Как только наставник прикрепляет файл к команде, копия уходит в сетевую
 * папку из настроек — в подпапку своей команды. Кураторские файлы не выгружаются:
 * папка нужна для документов, которые присылают наставники.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Team_Files_Export
 */
class WPPA_Team_Files_Export {

	/**
	 * Мета команды: что уже выгружено (ключ файла → имя в сетевой папке).
	 */
	const META = '_wppa_team_export_files';

	/**
	 * Сколько символов названия команды оставляем в имени папки.
	 */
	const NAME_LIMIT = 60;

	/**
	 * Общая папка, в которой группируются папки команд.
	 *
	 * Например: \\server\share\olimp\Команды\Студия «Вектор» (12).
	 */
	const ROOT_NAME = 'Команды';

	/**
	 * Выгрузка включена.
	 *
	 * Работает, когда в настройках задан путь к сетевой папке. Отключить можно
	 * фильтром.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$enabled = WPPA_SMB::is_configured();

		/**
		 * Фильтр автоматической выгрузки файлов наставника в сетевую папку.
		 *
		 * @param bool $enabled Включено ли.
		 */
		return (bool) apply_filters( 'wppa_team_files_export', $enabled );
	}

	/**
	 * Имя папки команды.
	 *
	 * Название очищается от служебных символов, номер команды добавляется,
	 * чтобы папки не сливались при одинаковых названиях.
	 *
	 * @param int $team_id ID команды.
	 * @return string
	 */
	public static function folder_name( $team_id ) {
		$team_id = (int) $team_id;

		if ( ! $team_id ) {
			return '';
		}

		$name = self::clean_name( (string) get_the_title( $team_id ) );

		// Номер команды в имени папки: одноимённые команды не сливаются в одну.
		return $name . ' (' . $team_id . ')';
	}

	/**
	 * Общая папка для команд.
	 *
	 * @return string
	 */
	public static function folder_root() {
		/**
		 * Фильтр имени общей папки команд. Пустая строка — папки команд лежат
		 * прямо в папке из настроек, без группировки.
		 *
		 * @param string $root Имя каталога.
		 */
		$root = trim( (string) apply_filters( 'wppa_team_files_export_root', self::ROOT_NAME ) );

		if ( '' === $root ) {
			return '';
		}

		return self::clean_name( $root );
	}

	/**
	 * Путь папки команды внутри выгрузки: «Команды/Название команды».
	 *
	 * @param int $team_id ID команды.
	 * @return string
	 */
	public static function folder_path( $team_id ) {
		$folder = self::folder_name( $team_id );

		if ( '' === $folder ) {
			return '';
		}

		$root = self::folder_root();

		return '' === $root ? $folder : $root . '/' . $folder;
	}

	/**
	 * Очистить имя папки от служебных символов.
	 *
	 * @param string $name Исходное имя.
	 * @return string
	 */
	public static function clean_name( $name ) {
		$name = preg_replace( '/[\\\\\\/:*?\"<>|]+/u', ' ', (string) $name );
		$name = preg_replace( '/[[:cntrl:]]/', ' ', (string) $name );
		$name = trim( preg_replace( '/\s+/u', ' ', (string) $name ) );
		$name = rtrim( $name, '. ' );

		if ( mb_strlen( $name ) > self::NAME_LIMIT ) {
			$name = rtrim( mb_substr( $name, 0, self::NAME_LIMIT ) );
		}

		return '' === $name ? 'team' : $name;
	}

	/**
	 * Полный путь папки команды на диске (только для режима файловой системы).
	 *
	 * @param int $team_id ID команды.
	 * @return string Пустая строка, если папка сетевая.
	 */
	public static function local_dir( $team_id, $config = array() ) {
		$config = is_array( $config ) && $config ? $config : WPPA_SMB::config();

		if ( ! WPPA_SMB::is_configured( $config ) || 'smb' === WPPA_SMB::detect_type( $config ) ) {
			return '';
		}

		$folder = self::folder_path( $team_id );

		if ( '' === $folder ) {
			return '';
		}

		return rtrim( (string) $config['path'], '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $folder );
	}

	/**
	 * Что уже выгружено по команде.
	 *
	 * @param int $team_id ID команды.
	 * @return array Ключ файла => имя в сетевой папке.
	 */
	public static function record( $team_id ) {
		$saved = get_post_meta( (int) $team_id, self::META, true );

		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Имя файла в сетевой папке.
	 *
	 * @param string $name    Имя, которое дал наставник.
	 * @param string $key     Ключ файла.
	 * @param int    $team_id ID команды.
	 * @return string
	 */
	public static function file_name( $name, $key, $team_id, $config = array() ) {
		$name = sanitize_file_name( (string) $name );
		$name = trim( str_replace( array( '/', '\\' ), ' ', $name ) );

		if ( '' === $name ) {
			$name = 'file';
		}

		// В папке команды уже есть файл с таким именем — добавляем ключ,
		// чтобы не перезаписать чужой документ.
		$dir = self::local_dir( $team_id, $config );

		if ( '' !== $dir && file_exists( $dir . DIRECTORY_SEPARATOR . $name ) ) {
			$info = pathinfo( $name );
			$ext  = isset( $info['extension'] ) && '' !== $info['extension'] ? '.' . $info['extension'] : '';

			$name = ( isset( $info['filename'] ) ? $info['filename'] : 'file' ) . '-' . substr( (string) $key, 0, 6 ) . $ext;
		}

		return $name;
	}

	/**
	 * Выгрузить файл наставника в сетевую папку команды.
	 *
	 * @param int    $team_id ID команды.
	 * @param string $path    Путь к файлу в uploads.
	 * @param string $name    Имя файла.
	 * @param string $key     Ключ файла.
	 * @return array ok, message, file.
	 */
	public static function export( $team_id, $path, $name, $key, $config = array() ) {
		$team_id = (int) $team_id;
		$config  = is_array( $config ) && $config ? $config : WPPA_SMB::config();

		$result = array(
			'ok'      => false,
			'message' => '',
			'file'    => '',
		);

		if ( ! WPPA_SMB::is_configured( $config ) || ! $team_id ) {
			return $result;
		}

		if ( ! is_file( $path ) ) {
			$result['message'] = __( 'Файл для выгрузки не найден.', 'wp-personal-account' );

			return $result;
		}

		$folder = self::folder_path( $team_id );
		$record = self::record( $team_id );

		// Файл уже выгружали раньше — пишем под тем же именем, а не плодим копии.
		$target = isset( $record[ (string) $key ] ) && '' !== (string) $record[ (string) $key ]
			? (string) $record[ (string) $key ]
			: self::file_name( $name, $key, $team_id, $config );

		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$contents = file_get_contents( $path );
			$written  = WPPA_SMB::write( $target, (string) $contents, $config, $folder );
		} catch ( \Throwable $e ) {
			// Сбой сети не должен ломать загрузку файла в кабинет.
			$written = array(
				'ok'      => false,
				'message' => $e->getMessage(),
				'file'    => '',
			);
		}

		if ( empty( $written['ok'] ) ) {
			$result['message'] = isset( $written['message'] ) ? (string) $written['message'] : '';

			return $result;
		}

		$record[ (string) $key ] = $target;

		update_post_meta( $team_id, self::META, $record );

		return array(
			'ok'      => true,
			'message' => isset( $written['message'] ) ? (string) $written['message'] : '',
			'file'    => isset( $written['file'] ) && '' !== $written['file'] ? (string) $written['file'] : $target,
		);
	}

	/**
	 * Убрать копию файла из сетевой папки.
	 *
	 * @param int    $team_id ID команды.
	 * @param string $key     Ключ файла.
	 * @return bool
	 */
	public static function remove( $team_id, $key ) {
		$team_id = (int) $team_id;
		$key     = (string) $key;
		$record  = self::record( $team_id );

		if ( ! isset( $record[ $key ] ) ) {
			return false;
		}

		$name = (string) $record[ $key ];
		$dir  = self::local_dir( $team_id );

		try {
			WPPA_SMB::delete( $name, WPPA_SMB::config(), self::folder_path( $team_id ) );
		} catch ( \Throwable $e ) {
			return false;
		}

		unset( $record[ $key ] );

		if ( $record ) {
			update_post_meta( $team_id, self::META, $record );
		} else {
			delete_post_meta( $team_id, self::META );
		}

		// Пустые папки на диске убираем, чтобы не копился мусор: сначала папку
		// команды, затем общую — если в ней не осталось других команд.
		if ( '' !== $dir && WPPA_SMB::remove_dir( $dir ) ) {
			WPPA_SMB::remove_dir( dirname( $dir ) );
		}

		return true;
	}

	/**
	 * Убрать всё, что команда выгрузила (при роспуске команды).
	 *
	 * @param int $team_id ID команды.
	 * @return void
	 */
	public static function clear( $team_id ) {
		$team_id = (int) $team_id;

		foreach ( array_keys( self::record( $team_id ) ) as $key ) {
			self::remove( $team_id, $key );
		}

		$dir = self::local_dir( $team_id );

		if ( '' !== $dir && WPPA_SMB::remove_dir( $dir ) ) {
			WPPA_SMB::remove_dir( dirname( $dir ) );
		}

		delete_post_meta( $team_id, self::META );
	}

	/**
	 * Настройки папки для массовой выгрузки.
	 *
	 * Основной источник — раздел «Сетевая папка». Если он пуст, берём путь из
	 * «Экспорт в папку»: кнопка «Экспортировать» должна складывать CSV и файлы
	 * команд в одно место.
	 *
	 * @return array
	 */
	public static function bulk_config() {
		$config = WPPA_SMB::config();

		if ( WPPA_SMB::is_configured( $config ) ) {
			return $config;
		}

		$folder = WPPA_Export_Folder::config();
		$path   = isset( $folder['path'] ) ? trim( (string) $folder['path'] ) : '';

		if ( '' === $path ) {
			return $config;
		}

		$config['path'] = $path;
		$config['user'] = '';
		$config['pass'] = '';

		return $config;
	}

	/**
	 * Выгрузить файлы наставников всех команд — по кнопке «Экспортировать».
	 *
	 * Кураторские файлы не выгружаются: в сетевую папку уходят только документы,
	 * которые прислали наставники. Повторный запуск перезаписывает те же файлы
	 * под теми же именами, дублей не появляется.
	 *
	 * @return array ok, files, teams, failed, message.
	 */
	public static function export_all() {
		$result = array(
			'ok'       => true,
			'files'    => 0,
			'exported' => 0,
			'skipped'  => 0,
			'teams'    => 0,
			'failed'   => 0,
			'errors'   => array(),
			'message'  => '',
		);

		$config = self::bulk_config();

		if ( ! apply_filters( 'wppa_team_files_export', WPPA_SMB::is_configured( $config ) ) ) {
			$result['ok']      = false;
			$result['message'] = __( 'Сетевая папка не настроена — файлы наставников не выгружены.', 'wp-personal-account' );

			return $result;
		}

		$uploads = WPPA_Curator_Files::dir();
		$base    = is_array( $uploads ) && ! empty( $uploads['dir'] ) ? rtrim( (string) $uploads['dir'], '/\\' ) : '';

		foreach ( WPPA_Teams::all_teams( 2000 ) as $team ) {
			$team_id    = (int) $team['id'];
			$team_files = 0;

			foreach ( WPPA_Curator_Files::files( $team_id ) as $file ) {
				if ( 'mentor' !== $file['from'] ) {
					continue;
				}

				$team_files++;
				$result['files']++;

				$source = '' === $base ? '' : $base . '/' . $file['file'];

				// Исходник пропал из uploads (удалён вручную, переехал uploads) —
				// это не сбой сети: просто помечаем и идём дальше, без пугающего
				// «выгрузилось не полностью».
				if ( '' === $source || ! is_file( $source ) ) {
					$result['skipped']++;
					$result['errors'][] = sprintf(
						/* translators: 1: команда, 2: файл */
						__( '«%1$s» → %2$s: исходного файла нет в папке загрузок, копия пропущена.', 'wp-personal-account' ),
						(string) $team['name'],
						(string) $file['name']
					);

					continue;
				}

				$exported = self::export( $team_id, $source, $file['name'], $file['key'], $config );

				if ( empty( $exported['ok'] ) ) {
					// Одна повторная попытка: сетевые папки иногда отваливаются на миг.
					$exported = self::export( $team_id, $source, $file['name'], $file['key'], $config );
				}

				if ( empty( $exported['ok'] ) ) {
					$result['failed']++;
					$result['errors'][] = sprintf(
						/* translators: 1: команда, 2: файл, 3: причина */
						__( '«%1$s» → %2$s: %3$s', 'wp-personal-account' ),
						(string) $team['name'],
						(string) $file['name'],
						'' !== (string) $exported['message'] ? (string) $exported['message'] : __( 'не удалось записать в папку', 'wp-personal-account' )
					);
				} else {
					$result['exported']++;
				}
			}

			if ( $team_files > 0 ) {
				$result['teams']++;
			}
		}

		if ( $result['failed'] > 0 ) {
			$result['ok']      = false;
			$result['message'] = sprintf(
				/* translators: 1: выгружено, 2: всего, 3: ошибок */
				__( 'Файлы наставников: выгружено %1$d из %2$d, ошибок %3$d. Первая: %4$s', 'wp-personal-account' ),
				(int) $result['exported'],
				(int) $result['files'],
				(int) $result['failed'],
				(string) reset( $result['errors'] )
			);
		} elseif ( 0 === $result['files'] ) {
			$result['message'] = __( 'Файлов наставников для выгрузки нет.', 'wp-personal-account' );
		} elseif ( $result['skipped'] > 0 ) {
			$result['message'] = sprintf(
				/* translators: 1: выгружено, 2: пропущено */
				__( 'Файлы наставников выгружены: %1$d. Пропущено без исходника: %2$d (подробности в журнале).', 'wp-personal-account' ),
				(int) $result['exported'],
				(int) $result['skipped']
			);
		} else {
			$result['message'] = sprintf(
				/* translators: 1: файлов, 2: команд */
				__( 'Файлы наставников выгружены: %1$d в %2$d командах.', 'wp-personal-account' ),
				(int) $result['exported'],
				(int) $result['teams']
			);
		}

		return $result;
	}

	/**
	 * Подписка на события.
	 *
	 * @return void
	 */
	public static function init() {
		// Выгрузка идёт из WPPA_Curator_Files::store() и remove().
	}
}
