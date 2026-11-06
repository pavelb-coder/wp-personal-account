<?php
/**
 * Выгрузка выданных сертификатов в отдельную папку.
 *
 * Каждый выданный сертификат сохраняется PDF-файлом с читаемым именем
 * (по умолчанию — «Фамилия Имя Отчество»). Папка — из настроек «Сетевая
 * папка», подпапка по умолчанию «Сертификаты»; если сетевая папка не задана,
 * файлы ложатся в `uploads/wppa-certificates`.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Certificate_Export
 */
class WPPA_Certificate_Export {

	/**
	 * Настройка «выгружать автоматически».
	 */
	const SETTING = 'certificate_export';

	/**
	 * Настройка «подпапка».
	 */
	const FOLDER_KEY = 'certificate_export_dir';

	/**
	 * Настройка «шаблон имени файла».
	 */
	const TEMPLATE_KEY = 'certificate_filename';

	/**
	 * Подпапка по умолчанию.
	 */
	const DEFAULT_FOLDER = 'Сертификаты';

	/**
	 * Шаблон имени файла по умолчанию.
	 */
	const DEFAULT_TEMPLATE = '{recipient_name}';

	/**
	 * Папка в uploads, если сетевая папка не настроена.
	 */
	const UPLOAD_DIR = 'wppa-certificates';

	/**
	 * Опция с итогом последней выгрузки.
	 */
	const LOG_OPTION = 'wppa_certificate_export_log';

	/**
	 * Выгружать ли сертификаты автоматически.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$on = (bool) (int) WPPA_Plugin::setting( self::SETTING, 1 );

		/**
		 * Фильтр автоматической выгрузки сертификатов.
		 *
		 * @param bool $on Выгружать.
		 */
		return (bool) apply_filters( 'wppa_certificate_export_enabled', $on );
	}

	/**
	 * Подпапка для файлов сертификатов.
	 *
	 * @return string
	 */
	public static function folder() {
		$folder = trim( (string) WPPA_Plugin::setting( self::FOLDER_KEY, self::DEFAULT_FOLDER ) );
		$folder = trim( str_replace( array( '\\', '..' ), array( '/', '' ), $folder ), '/ ' );

		/**
		 * Фильтр подпапки сертификатов.
		 *
		 * @param string $folder Имя подпапки.
		 */
		return (string) apply_filters( 'wppa_certificate_export_folder', $folder );
	}

	/**
	 * Шаблон имени файла.
	 *
	 * @return string
	 */
	public static function template() {
		$template = (string) WPPA_Plugin::setting( self::TEMPLATE_KEY, self::DEFAULT_TEMPLATE );
		$template = '' === trim( $template ) ? self::DEFAULT_TEMPLATE : $template;

		/**
		 * Фильтр шаблона имени файла сертификата.
		 *
		 * @param string $template Шаблон с подстановками.
		 */
		return (string) apply_filters( 'wppa_certificate_export_filename', $template );
	}

	/**
	 * Доступные подстановки имени файла.
	 *
	 * @return string[]
	 */
	public static function placeholder_list() {
		return array( '{recipient_name}', '{number}', '{team}', '{date}', '{email}' );
	}

	/**
	 * Папка на диске для режима без сетевой папки.
	 *
	 * @return string
	 */
	public static function upload_dir() {
		if ( function_exists( 'wp_upload_dir' ) ) {
			$uploads = wp_upload_dir();

			if ( ! empty( $uploads['basedir'] ) ) {
				return rtrim( (string) $uploads['basedir'], '/\\' ) . DIRECTORY_SEPARATOR . self::UPLOAD_DIR;
			}
		}

		return rtrim( sys_get_temp_dir(), '/\\' ) . DIRECTORY_SEPARATOR . self::UPLOAD_DIR;
	}

	/**
	 * Сетевая папка настроена?
	 *
	 * @return bool
	 */
	public static function uses_network_folder() {
		return class_exists( 'WPPA_SMB' ) && WPPA_SMB::is_configured();
	}

	/**
	 * Полный путь папки сертификатов (для журнала и подсказок).
	 *
	 * @return string
	 */
	public static function folder_path() {
		$folder = self::folder();
		$suffix = '' === $folder ? '' : DIRECTORY_SEPARATOR . $folder;

		if ( self::uses_network_folder() ) {
			return rtrim( WPPA_SMB::path_of(), '/\\' ) . $suffix;
		}

		return self::upload_dir() . $suffix;
	}

	/**
	 * Путь для показа в интерфейсе: полный для сетевой папки,
	 * «uploads/wppa-certificates/…» — для локальной.
	 *
	 * @return string
	 */
	public static function display_path() {
		$folder = self::folder();
		$suffix = '' === $folder ? '' : '/' . $folder;

		if ( self::uses_network_folder() ) {
			return rtrim( WPPA_SMB::path_of(), '/\\' ) . $suffix;
		}

		return 'uploads/' . self::UPLOAD_DIR . $suffix;
	}

	/**
	 * Имя файла сертификата по шаблону.
	 *
	 * @param int   $user_id Участник.
	 * @param array $entry   Запись сертификата.
	 * @return string
	 */
	public static function file_name( $user_id, $entry = array() ) {
		$user = get_userdata( (int) $user_id );

		$team = isset( $entry['course'] ) ? (string) $entry['course'] : '';

		if ( '' === $team && $user instanceof WP_User ) {
			$team = (string) get_the_title( (int) WPPA_Teams::team_id( $user->ID ) );
		}

		$map = array(
			'{recipient_name}' => $user instanceof WP_User ? WPPA_Teams::full_name( $user ) : '',
			'{number}'         => isset( $entry['number'] ) ? (string) $entry['number'] : '',
			'{team}'           => $team,
			'{date}'           => ! empty( $entry['time'] ) ? date_i18n( 'Y-m-d', (int) $entry['time'] ) : '',
			'{email}'          => $user instanceof WP_User ? (string) $user->user_email : '',
		);

		$name = strtr( self::template(), $map );
		$name = class_exists( 'WPPA_Team_Files_Export' )
			? WPPA_Team_Files_Export::clean_name( $name )
			: trim( (string) preg_replace( '/[\\\\\\/:*?"<>|]+/u', ' ', $name ) );

		if ( '' === $name || 'team' === $name ) {
			$name = '' !== $map['{number}'] ? $map['{number}'] : 'certificate';
		}

		return preg_match( '/\.pdf$/i', $name ) ? $name : $name . '.pdf';
	}

	/**
	 * Сохранить PDF сертификата в папку.
	 *
	 * @param int   $cert_id ID сертификата.
	 * @param int   $user_id Участник.
	 * @param array $entry   Запись сертификата.
	 * @return array ok, message, file.
	 */
	public static function export( $cert_id, $user_id, $entry = array() ) {
		$cert_id = (int) $cert_id;
		$user_id = (int) $user_id;
		$name    = self::file_name( $user_id, $entry );

		$fail = static function ( $message ) use ( $name ) {
			$result = array(
				'ok'      => false,
				'message' => $message,
				'file'    => $name,
			);

			update_option( self::LOG_OPTION, array_merge( $result, array( 'time' => time() ) ), false );

			return $result;
		};

		if ( ! WPPA_Certificates::available() ) {
			return $fail( __( 'Для выгрузки нужен активный плагин WP Diplom.', 'wp-personal-account' ) );
		}

		$path = WPDC_Certificate::pdf( $cert_id );

		if ( is_wp_error( $path ) ) {
			return $fail( $path->get_error_message() );
		}

		$path = (string) $path;

		if ( '' === $path || ! is_readable( $path ) ) {
			return $fail( __( 'PDF сертификата не найден.', 'wp-personal-account' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = (string) file_get_contents( $path );

		if ( '' === $contents ) {
			return $fail( __( 'Не удалось прочитать PDF сертификата.', 'wp-personal-account' ) );
		}

		$folder = self::folder();

		if ( self::uses_network_folder() ) {
			$result = WPPA_SMB::write( $name, $contents, array(), $folder );
		} else {
			$dir = self::upload_dir() . ( '' === $folder ? '' : DIRECTORY_SEPARATOR . $folder );

			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				return $fail( sprintf( /* translators: %s: папка */ __( 'Папки %s нет и создать её не удалось.', 'wp-personal-account' ), $dir ) );
			}

			$name = self::unique_name( $dir, $name, $entry );
			$full = rtrim( $dir, '/\\' ) . DIRECTORY_SEPARATOR . $name;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$written = file_put_contents( $full, $contents, LOCK_EX );

			$result = array(
				'ok'      => false !== $written,
				'message' => false !== $written
					? sprintf( /* translators: %s: файл */ __( 'Файл %s записан в папку.', 'wp-personal-account' ), $full )
					: sprintf( /* translators: %s: файл */ __( 'Не удалось записать %s.', 'wp-personal-account' ), $full ),
				'file'    => $full,
				'bytes'   => false !== $written ? (int) $written : 0,
			);
		}

		update_option(
			self::LOG_OPTION,
			array(
				'ok'      => ! empty( $result['ok'] ),
				'message' => isset( $result['message'] ) ? (string) $result['message'] : '',
				'file'    => isset( $result['file'] ) ? (string) $result['file'] : $name,
				'time'    => time(),
			),
			false
		);

		return array(
			'ok'      => ! empty( $result['ok'] ),
			'message' => isset( $result['message'] ) ? (string) $result['message'] : '',
			'file'    => isset( $result['file'] ) ? (string) $result['file'] : $name,
		);
	}

	/**
	 * Свободное имя файла: к имени добавляется номер сертификата, если файл занят.
	 *
	 * @param string $dir   Папка.
	 * @param string $name  Имя файла.
	 * @param array  $entry Запись сертификата.
	 * @return string
	 */
	protected static function unique_name( $dir, $name, $entry = array() ) {
		$full = rtrim( $dir, '/\\' ) . DIRECTORY_SEPARATOR . $name;

		if ( ! file_exists( $full ) ) {
			return $name;
		}

		$base = preg_replace( '/\.pdf$/i', '', $name );
		$tail = isset( $entry['number'] ) && '' !== $entry['number'] ? ' ' . $entry['number'] : '';

		if ( '' !== $tail && ! file_exists( rtrim( $dir, '/\\' ) . DIRECTORY_SEPARATOR . $base . $tail . '.pdf' ) ) {
			return $base . $tail . '.pdf';
		}

		for ( $i = 2; $i < 100; $i++ ) {
			$candidate = $base . ( '' !== $tail ? $tail : '' ) . ' (' . $i . ')';

			if ( ! file_exists( rtrim( $dir, '/\\' ) . DIRECTORY_SEPARATOR . $candidate . '.pdf' ) ) {
				return $candidate . '.pdf';
			}
		}

		return $name;
	}

	/**
	 * Выгрузить все выданные сертификаты.
	 *
	 * @return array count, failed.
	 */
	public static function export_all() {
		$done   = 0;
		$failed = 0;

		foreach ( WPPA_Teams::all_teams() as $team ) {
			foreach ( WPPA_Teams::participants( (int) $team['id'] ) as $member ) {
				foreach ( WPPA_Certificates::for_user( (int) $member->ID ) as $cert ) {
					$result = self::export( (int) $cert['id'], (int) $member->ID, $cert );

					if ( ! empty( $result['ok'] ) ) {
						$done++;
					} else {
						$failed++;
					}
				}
			}
		}

		return array(
			'count'  => $done,
			'failed' => $failed,
		);
	}

	/**
	 * Итог последней выгрузки.
	 *
	 * @return array ok, message, file, time.
	 */
	public static function last() {
		$saved = get_option( self::LOG_OPTION, array() );

		return is_array( $saved ) ? $saved : array();
	}
}
