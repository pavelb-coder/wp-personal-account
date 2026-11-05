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

		$name = (string) get_the_title( $team_id );
		$name = preg_replace( '/[\\\\\\/:*?"<>|]+/u', ' ', $name );
		$name = preg_replace( '/[[:cntrl:]]/', ' ', (string) $name );
		$name = trim( preg_replace( '/\s+/u', ' ', (string) $name ) );
		$name = rtrim( $name, '. ' );

		if ( mb_strlen( $name ) > self::NAME_LIMIT ) {
			$name = rtrim( mb_substr( $name, 0, self::NAME_LIMIT ) );
		}

		if ( '' === $name ) {
			$name = 'team';
		}

		return $name . ' (' . $team_id . ')';
	}

	/**
	 * Полный путь папки команды на диске (только для режима файловой системы).
	 *
	 * @param int $team_id ID команды.
	 * @return string Пустая строка, если папка сетевая.
	 */
	public static function local_dir( $team_id ) {
		$config = WPPA_SMB::config();

		if ( ! WPPA_SMB::is_configured( $config ) || 'smb' === WPPA_SMB::detect_type( $config ) ) {
			return '';
		}

		$folder = self::folder_name( $team_id );

		if ( '' === $folder ) {
			return '';
		}

		return rtrim( (string) $config['path'], '/\\' ) . DIRECTORY_SEPARATOR . $folder;
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
	public static function file_name( $name, $key, $team_id ) {
		$name = sanitize_file_name( (string) $name );
		$name = trim( str_replace( array( '/', '\\' ), ' ', $name ) );

		if ( '' === $name ) {
			$name = 'file';
		}

		// В папке команды уже есть файл с таким именем — добавляем ключ,
		// чтобы не перезаписать чужой документ.
		$dir = self::local_dir( $team_id );

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
	public static function export( $team_id, $path, $name, $key ) {
		$team_id = (int) $team_id;

		$result = array(
			'ok'      => false,
			'message' => '',
			'file'    => '',
		);

		if ( ! self::enabled() || ! $team_id ) {
			return $result;
		}

		if ( ! is_file( $path ) ) {
			$result['message'] = __( 'Файл для выгрузки не найден.', 'wp-personal-account' );

			return $result;
		}

		$folder = self::folder_name( $team_id );
		$target = self::file_name( $name, $key, $team_id );

		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$contents = file_get_contents( $path );
			$written  = WPPA_SMB::write( $target, (string) $contents, WPPA_SMB::config(), $folder );
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

		$record                = self::record( $team_id );
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
			WPPA_SMB::delete( $name, WPPA_SMB::config(), self::folder_name( $team_id ) );
		} catch ( \Throwable $e ) {
			return false;
		}

		unset( $record[ $key ] );

		if ( $record ) {
			update_post_meta( $team_id, self::META, $record );
		} else {
			delete_post_meta( $team_id, self::META );
		}

		// Пустую папку команды на диске убираем, чтобы не копился мусор.
		if ( '' !== $dir ) {
			WPPA_SMB::remove_dir( $dir );
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

		if ( '' !== $dir ) {
			WPPA_SMB::remove_dir( $dir );
		}

		delete_post_meta( $team_id, self::META );
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
