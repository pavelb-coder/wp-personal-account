<?php
/**
 * Выгрузка аккаунтов в сетевую папку (SMB): файл CSV на диск, расписание, ротация, журнал.
 *
 * SMB не поддерживается в PHP напрямую: папка должна быть смонтирована в системе
 * (Linux: mount -t cifs, Windows: путь UNC \\server\share). Плагин пишет файл
 * по обычному пути — так работает и с локальным диском, и с сетевой шары.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Export_Folder
 */
class WPPA_Export_Folder {

	/**
	 * Ключ настройки.
	 */
	const SETTING = 'export_folder';

	/**
	 * Хук расписания.
	 */
	const CRON_HOOK = 'wppa_export_folder_event';

	/**
	 * Ключ журнала.
	 */
	const LOG_OPTION = 'wppa_export_folder_log';

	/**
	 * Сколько записей журнала хранить.
	 */
	const LOG_LIMIT = 10;

	/**
	 * Значения по умолчанию.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'   => 0,
			'path'      => '',
			'file'      => 'wppa-accounts-{date}.csv',
			'schedule'  => 'daily',
			'time'      => '03:00',
			'keep'      => 30,
			'groups'    => WPPA_Export::default_groups(),
			'scope'     => 'all',
			'delimiter' => ';',
			'limit'     => 5000,
		);
	}

	/**
	 * Варианты расписания.
	 *
	 * @return array
	 */
	public static function schedules() {
		return array(
			'off'        => __( 'Только вручную', 'wp-personal-account' ),
			'hourly'     => __( 'Каждый час', 'wp-personal-account' ),
			'twicedaily' => __( 'Дважды в день', 'wp-personal-account' ),
			'daily'      => __( 'Раз в день', 'wp-personal-account' ),
			'weekly'     => __( 'Раз в неделю', 'wp-personal-account' ),
		);
	}

	/**
	 * Текущие настройки.
	 *
	 * @return array
	 */
	public static function config() {
		$saved = WPPA_Plugin::setting( self::SETTING, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$out   = array();

		foreach ( self::defaults() as $key => $default ) {
			$out[ $key ] = array_key_exists( $key, $saved ) ? $saved[ $key ] : $default;
		}

		$out['enabled']   = empty( $out['enabled'] ) ? 0 : 1;
		$out['path']      = (string) $out['path'];
		$out['file']      = '' === trim( (string) $out['file'] ) ? self::defaults()['file'] : (string) $out['file'];
		$out['schedule']  = isset( self::schedules()[ $out['schedule'] ] ) ? $out['schedule'] : 'daily';
		$out['time']      = self::sanitize_time( $out['time'] );
		$out['keep']      = max( 0, min( 365, (int) $out['keep'] ) );
		$out['groups']    = is_array( $out['groups'] ) && $out['groups'] ? array_values( array_intersect( array_keys( WPPA_Export::groups() ), $out['groups'] ) ) : WPPA_Export::default_groups();
		$out['scope']     = isset( WPPA_Export::scopes()[ $out['scope'] ] ) ? $out['scope'] : 'all';
		$out['delimiter'] = ',' === $out['delimiter'] ? ',' : ';';
		$out['limit']     = max( 1, min( 20000, (int) $out['limit'] ) );

		/**
		 * Фильтр настроек выгрузки в папку.
		 *
		 * @param array $out Настройки.
		 */
		return apply_filters( 'wppa_export_folder', $out );
	}

	/**
	 * Время ЧЧ:ММ с проверкой диапазона.
	 *
	 * @param string $time Значение.
	 * @return string
	 */
	public static function sanitize_time( $time ) {
		$time = trim( (string) $time );

		if ( ! preg_match( '/^(\d{2}):(\d{2})$/', $time, $matches ) ) {
			return '03:00';
		}

		$hours   = (int) $matches[1];
		$minutes = (int) $matches[2];

		if ( $hours > 23 || $minutes > 59 ) {
			return '03:00';
		}

		return sprintf( '%02d:%02d', $hours, $minutes );
	}

	/**
	 * Путь к папке без завершающего слэша.
	 *
	 * @param string $path Путь.
	 * @return string
	 */
	public static function normalize_path( $path ) {
		$path = trim( (string) $path );

		if ( '' === $path ) {
			return '';
		}

		// Схемы вроде smb:// php:// пишем только через фильтр-обработчик.
		if ( preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $path ) ) {
			return '';
		}

		return rtrim( $path, '/\\' );
	}

	/**
	 * Имя файла по шаблону: {date}, {time}, {datetime}, {site}.
	 *
	 * @param string $template Шаблон.
	 * @param int    $timestamp Время.
	 * @return string
	 */
	public static function filename( $template = '', $timestamp = 0 ) {
		$template  = '' === trim( (string) $template ) ? self::defaults()['file'] : (string) $template;
		$timestamp = $timestamp ? (int) $timestamp : time();
		$site      = sanitize_title( (string) get_bloginfo( 'name' ) );

		$replace = array(
			'{date}'     => gmdate( 'Y-m-d', $timestamp ),
			'{time}'     => gmdate( 'His', $timestamp ),
			'{datetime}' => gmdate( 'Y-m-d-His', $timestamp ),
			'{site}'     => '' === $site ? 'site' : $site,
		);

		$name = strtr( $template, $replace );
		$name = preg_replace( '/[^A-Za-z0-9А-Яа-яЁё._\-]+/u', '-', $name );

		if ( ! preg_match( '/\.csv$/i', $name ) ) {
			$name .= '.csv';
		}

		return $name;
	}

	/**
	 * Состояние папки: существует, доступна на запись.
	 *
	 * @param string $path Путь.
	 * @return array ok, message.
	 */
	public static function check_folder( $path ) {
		$path = self::normalize_path( $path );

		if ( '' === $path ) {
			return array(
				'ok'      => false,
				'message' => __( 'Путь не задан или содержит неподдерживаемую схему (smb://). Укажите путь к смонтированной папке.', 'wp-personal-account' ),
			);
		}

		if ( ! is_dir( $path ) ) {
			return array(
				'ok'      => false,
				'message' => sprintf(
					/* translators: %s: путь */
					__( 'Папка %s не найдена или недоступна процессу PHP.', 'wp-personal-account' ),
					$path
				),
			);
		}

		if ( ! is_writable( $path ) ) {
			return array(
				'ok'      => false,
				'message' => sprintf(
					/* translators: %s: путь */
					__( 'В папку %s нельзя писать: проверьте права и монтирование сетевой шары.', 'wp-personal-account' ),
					$path
				),
			);
		}

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %s: путь */
				__( 'Папка %s доступна на запись.', 'wp-personal-account' ),
				$path
			),
		);
	}

	/**
	 * Записать CSV в папку.
	 *
	 * @param array $args Аргументы: path, file, groups, scope, delimiter, limit, timestamp, keep.
	 * @return array ok, message, file, bytes.
	 */
	public static function write( $args = array() ) {
		$config = self::config();
		$args   = wp_parse_args( $args, $config );

		$name = self::filename( (string) $args['file'], isset( $args['timestamp'] ) ? (int) $args['timestamp'] : 0 );

		$path = self::normalize_path( $args['path'] );
		$check = self::check_folder( $path );

		if ( ! $check['ok'] ) {
			self::log_entry( false, '', 0, $check['message'] );

			return array(
				'ok'      => false,
				'message' => $check['message'],
				'file'    => '',
				'bytes'   => 0,
			);
		}

		$csv  = WPPA_Export::csv(
			array(
				'groups'    => (array) $args['groups'],
				'scope'     => (string) $args['scope'],
				'delimiter' => (string) $args['delimiter'],
				'limit'     => (int) $args['limit'],
			)
		);
		$name = self::filename( (string) $args['file'], isset( $args['timestamp'] ) ? (int) $args['timestamp'] : 0 );
		$full = $path . DIRECTORY_SEPARATOR . $name;
		$temp = $full . '.tmp';

		/**
		 * Фильтр записи файла: позволяет писать в SMB своим клиентом (например, php-smbclient).
		 *
		 * @param bool|null $handled null — писать штатно.
		 * @param string    $full    Полный путь к файлу.
		 * @param string    $csv     Содержимое.
		 */
		$handled = apply_filters( 'wppa_export_folder_writer', null, $full, $csv );
		$written = null === $handled
			? (bool) file_put_contents( $temp, $csv, LOCK_EX ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			: (bool) $handled;

		if ( $written && null === $handled ) {
			// Пишем через временный файл: читатель не увидит половину выгрузки.
			$written = (bool) rename( $temp, $full ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		}

		if ( ! $written ) {
			if ( file_exists( $temp ) ) {
				unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}

			$message = sprintf(
				/* translators: %s: файл */
				__( 'Не удалось записать файл %s.', 'wp-personal-account' ),
				$full
			);

			self::log_entry( false, $full, 0, $message );

			return array(
				'ok'      => false,
				'message' => $message,
				'file'    => $full,
				'bytes'   => 0,
			);
		}

		$bytes = (int) filesize( $full );
		$kept  = self::rotate( $path, (int) $args['keep'] );
		$note  = sprintf(
			/* translators: 1: файл, 2: размер в килобайтах */
			__( 'Файл %1$s записан (%2$d КБ).', 'wp-personal-account' ),
			$full,
			(int) round( $bytes / 1024 )
		);

		if ( $kept > 0 ) {
			$note .= ' ' . sprintf(
				/* translators: %d: сколько старых файлов удалено */
				_n( 'Удалён %d старый файл по правилу ротации.', 'Удалено %d старых файлов по правилу ротации.', $kept, 'wp-personal-account' ),
				$kept
			);
		}

		self::log_entry( true, $full, $bytes, $note );

		return array(
			'ok'      => true,
			'message' => $note,
			'file'    => $full,
			'bytes'   => $bytes,
		);
	}

	/**
	 * Ротация: оставить последние N файлов выгрузки.
	 *
	 * @param string $path Папка.
	 * @param int    $keep Сколько оставить (0 — не удалять).
	 * @return int Сколько файлов удалено.
	 */
	public static function rotate( $path, $keep ) {
		$keep = (int) $keep;

		if ( $keep < 1 ) {
			return 0;
		}

		$files = glob( $path . DIRECTORY_SEPARATOR . '*.csv' );

		if ( ! is_array( $files ) || count( $files ) <= $keep ) {
			return 0;
		}

		// Сначала самые новые.
		usort(
			$files,
			function ( $a, $b ) {
				return filemtime( $b ) <=> filemtime( $a );
			}
		);

		$removed = 0;

		foreach ( array_slice( $files, $keep ) as $old ) {
			if ( unlink( $old ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				++$removed;
			}
		}

		return $removed;
	}

	/**
	 * Запись в журнал.
	 *
	 * @param bool   $ok      Успех.
	 * @param string $file    Файл.
	 * @param int    $bytes   Размер.
	 * @param string $message Сообщение.
	 * @return void
	 */
	public static function log_entry( $ok, $file, $bytes, $message ) {
		$log = self::logs();

		array_unshift(
			$log,
			array(
				'time'    => time(),
				'ok'      => (bool) $ok,
				'file'    => (string) $file,
				'bytes'   => (int) $bytes,
				'message' => (string) $message,
			)
		);

		update_option( self::LOG_OPTION, array_slice( $log, 0, self::LOG_LIMIT ), false );
	}

	/**
	 * Журнал выгрузок (сначала новые).
	 *
	 * @return array
	 */
	public static function logs() {
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Обработчик расписания.
	 *
	 * @return void
	 */
	public static function run_cron() {
		$config = self::config();

		if ( empty( $config['enabled'] ) || '' === $config['path'] ) {
			return;
		}

		self::write( $config );
	}

	/**
	 * Следующий запуск по расписанию.
	 *
	 * @return int Метка времени или 0.
	 */
	public static function next_run() {
		$next = wp_next_scheduled( self::CRON_HOOK );
		return $next ? (int) $next : 0;
	}

	/**
	 * Пересобрать расписание под настройки.
	 *
	 * @return void
	 */
	public static function reschedule() {
		$config   = self::config();
		$existing = self::next_run();
		$schedule = $config['schedule'];

		if ( empty( $config['enabled'] ) || 'off' === $schedule || '' === $config['path'] ) {
			if ( $existing ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}

			return;
		}

		$recurrence = in_array( $schedule, array( 'hourly', 'twicedaily', 'daily', 'weekly' ), true ) ? $schedule : 'daily';

		// Уже стоит нужная периодичность — ничего не трогаем.
		$current = wp_get_schedule( self::CRON_HOOK );

		if ( $existing && $current === $recurrence ) {
			return;
		}

		if ( $existing ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}

		wp_schedule_event( self::first_run_timestamp( $recurrence, $config['time'] ), $recurrence, self::CRON_HOOK );
	}

	/**
	 * Когда запускать первый раз при выбранном времени суток.
	 *
	 * @param string $recurrence Периодичность.
	 * @param string $time       Время ЧЧ:ММ в часовом поясе сайта.
	 * @return int
	 */
	public static function first_run_timestamp( $recurrence, $time ) {
		if ( ! in_array( $recurrence, array( 'daily', 'weekly' ), true ) ) {
			return time() + 60;
		}

		$time     = self::sanitize_time( $time );
		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$today    = new DateTime( gmdate( 'Y-m-d' ) . ' ' . $time, $timezone );
		$next     = $today->getTimestamp();

		if ( $next <= time() ) {
			$next += DAY_IN_SECONDS;
		}

		return $next;
	}
}
