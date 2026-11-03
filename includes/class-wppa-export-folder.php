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
	 * Хук выгрузки по изменению.
	 */
	const CHANGE_HOOK = 'wppa_export_folder_change_event';

	/**
	 * Опция с причинами ожидающей выгрузки.
	 */
	const PENDING_OPTION = 'wppa_export_folder_pending';

	/**
	 * Мета-ключи, изменения которых не считаем изменением данных.
	 *
	 * @return array
	 */
	public static function ignored_meta() {
		return array(
			WPPA_Profile::META_EMAIL_TOKEN,
			WPPA_Profile::META_EMAIL_EXPIRES,
			WPPA_Teams::META_TOKEN,
			WPPA_Teams::META_TOKEN_EXPIRES,
		);
	}

	/**
	 * Задержки перед выгрузкой по изменению, в минутах.
	 *
	 * @return array
	 */
	public static function delays() {
		return array(
			0  => __( 'Сразу', 'wp-personal-account' ),
			5  => __( 'Через 5 минут', 'wp-personal-account' ),
			15 => __( 'Через 15 минут', 'wp-personal-account' ),
			60 => __( 'Через час', 'wp-personal-account' ),
		);
	}

	/**
	 * Значения по умолчанию.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'   => 0,
			'mode'      => 'path',
			'path'      => '',
			'file'      => 'wppa-accounts-{date}.csv',
			'schedule'  => 'daily',
			'time'      => '03:00',
			'keep'      => 30,
			'groups'    => WPPA_Export::default_groups(),
			'scope'     => 'all',
			'delimiter'    => ';',
			'limit'        => 5000,
			'on_change'    => 0,
			'change_delay' => 5,
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
		$out['mode']      = 'smb' === $out['mode'] ? 'smb' : 'path';

		$out['on_change']    = empty( $out['on_change'] ) ? 0 : 1;
		$out['change_delay'] = array_key_exists( (int) $out['change_delay'], self::delays() ) ? (int) $out['change_delay'] : 5;
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

		// Режим SMB: путь может быть шарой, логин и пароль — в настройках.
		if ( 'smb' === $args['mode'] ) {
			$csv    = WPPA_Export::csv(
				array(
					'groups'    => (array) $args['groups'],
					'scope'     => (string) $args['scope'],
					'delimiter' => (string) $args['delimiter'],
					'limit'     => (int) $args['limit'],
				)
			);
			$result = WPPA_SMB::write( $name, $csv, WPPA_SMB::config() );

			self::log_entry( $result['ok'], $result['file'], (int) $result['bytes'], $result['message'] );

			return array(
				'ok'      => $result['ok'],
				'message' => $result['message'],
				'file'    => $result['file'],
				'bytes'   => (int) $result['bytes'],
			);
		}

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
	 * Подписка на события WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ) );
		add_action( 'deleted_user', array( __CLASS__, 'on_user_delete' ) );
		add_action( 'profile_update', array( __CLASS__, 'on_profile_update' ) );
		add_action( 'added_user_meta', array( __CLASS__, 'on_meta_change' ), 10, 3 );
		add_action( 'updated_user_meta', array( __CLASS__, 'on_meta_change' ), 10, 3 );
		add_action( 'deleted_user_meta', array( __CLASS__, 'on_meta_change' ), 10, 3 );
		add_action( self::CHANGE_HOOK, array( __CLASS__, 'run_change' ) );

		// Смена настроек команды лежит в опциях, а не в мета.
		add_action( 'add_option_' . WPPA_Teams::META_LIMIT, array( __CLASS__, 'on_team_setting' ) );
		add_action( 'update_option_' . WPPA_Teams::META_LIMIT, array( __CLASS__, 'on_team_setting' ) );
		add_action( 'add_option_' . WPPA_Teams::META_MIN, array( __CLASS__, 'on_team_setting' ) );
		add_action( 'update_option_' . WPPA_Teams::META_MIN, array( __CLASS__, 'on_team_setting' ) );
	}

	/**
	 * Регистрация пользователя.
	 *
	 * @param int $user_id ID пользователя.
	 * @return void
	 */
	public static function on_user_register( $user_id ) {
		self::note_change( sprintf( /* translators: %d: ID */ __( 'регистрация пользователя #%d', 'wp-personal-account' ), (int) $user_id ) );
	}

	/**
	 * Удаление пользователя.
	 *
	 * @param int $user_id ID пользователя.
	 * @return void
	 */
	public static function on_user_delete( $user_id ) {
		self::note_change( sprintf( /* translators: %d: ID */ __( 'удаление пользователя #%d', 'wp-personal-account' ), (int) $user_id ) );
	}

	/**
	 * Сохранение профиля.
	 *
	 * @param int $user_id ID пользователя.
	 * @return void
	 */
	public static function on_profile_update( $user_id ) {
		self::note_change( sprintf( /* translators: %d: ID */ __( 'профиль пользователя #%d', 'wp-personal-account' ), (int) $user_id ) );
	}

	/**
	 * Изменение мета-поля пользователя.
	 *
	 * @param int    $meta_id  ID записи мета.
	 * @param int    $user_id  ID пользователя.
	 * @param string $meta_key Ключ.
	 * @return void
	 */
	public static function on_meta_change( $meta_id, $user_id, $meta_key ) {
		$meta_key = (string) $meta_key;

		if ( 0 !== strpos( $meta_key, '_wppa_' ) || in_array( $meta_key, self::ignored_meta(), true ) ) {
			return;
		}

		self::note_change( sprintf(
			/* translators: 1: поле, 2: ID */
			__( 'поле %1$s у пользователя #%2$d', 'wp-personal-account' ),
			$meta_key,
			(int) $user_id
		) );
	}

	/**
	 * Изменение настройки команды.
	 *
	 * @return void
	 */
	public static function on_team_setting() {
		self::note_change( __( 'настройки команды', 'wp-personal-account' ) );
	}

	/**
	 * Запомнить изменение и запланировать выгрузку.
	 *
	 * Изменения подряд объединяются в одну выгрузку, чтобы не писать файл
	 * на каждое сохранение профиля.
	 *
	 * @param string $reason Причина.
	 * @return bool
	 */
	public static function note_change( $reason ) {
		$config = self::config();

		if ( empty( $config['on_change'] ) || '' === $config['path'] ) {
			return false;
		}

		$pending = self::pending();
		$pending[] = (string) $reason;
		$pending   = array_slice( array_values( array_unique( $pending ) ), -20 );

		update_option( self::PENDING_OPTION, $pending, false );

		$delay = (int) $config['change_delay'];

		if ( 0 === $delay ) {
			self::run_change();
			return true;
		}

		// Уже запланировано — время не сдвигаем, иначе поток изменений отложит выгрузку навсегда.
		if ( ! wp_next_scheduled( self::CHANGE_HOOK ) ) {
			wp_schedule_single_event( time() + $delay * MINUTE_IN_SECONDS, self::CHANGE_HOOK );
		}

		return true;
	}

	/**
	 * Причины ожидающей выгрузки.
	 *
	 * @return array
	 */
	public static function pending() {
		$pending = get_option( self::PENDING_OPTION, array() );

		return is_array( $pending ) ? $pending : array();
	}

	/**
	 * Когда ближайшая выгрузка по изменению.
	 *
	 * @return int Метка времени или 0.
	 */
	public static function next_change() {
		$next = wp_next_scheduled( self::CHANGE_HOOK );

		return $next ? (int) $next : 0;
	}

	/**
	 * Выгрузка по изменению.
	 *
	 * @return array
	 */
	public static function run_change() {
		$pending = self::pending();
		$config  = self::config();

		if ( empty( $pending ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Нет изменений для выгрузки.', 'wp-personal-account' ),
				'file'    => '',
				'bytes'   => 0,
			);
		}

		$result = self::write( $config );

		self::clear_pending();
		wp_clear_scheduled_hook( self::CHANGE_HOOK );

		if ( $result['ok'] ) {
			self::log_entry(
				true,
				$result['file'],
				(int) $result['bytes'],
				sprintf(
					/* translators: 1: причина, 2: сообщение */
					__( 'Выгрузка по изменению (%1$s). %2$s', 'wp-personal-account' ),
					implode( ', ', array_slice( $pending, 0, 5 ) ),
					$result['message']
				)
			);
		}

		return $result;
	}

	/**
	 * Сбросить ожидающие изменения.
	 *
	 * @return void
	 */
	public static function clear_pending() {
		delete_option( self::PENDING_OPTION );
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

		if ( empty( $config['on_change'] ) ) {
			self::clear_pending();

			if ( wp_next_scheduled( self::CHANGE_HOOK ) ) {
				wp_clear_scheduled_hook( self::CHANGE_HOOK );
			}
		}

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
