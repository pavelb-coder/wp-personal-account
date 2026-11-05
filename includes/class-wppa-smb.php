<?php
/**
 * Подключение к сетевой локальной папке с авторизацией.
 *
 * Папка задаётся одним путём: UNC (\\server\share\dir), smb://server/share/dir
 * или обычный локальный путь. Логин и пароль хранятся в настройках.
 *
 * Порядок работы (как в wordprees):
 *   1. Windows — обычная файловая система.
 *   2. Шара уже смонтирована в ОС — пишем по пути.
 *   3. Есть расширение php-smbclient — пишем напрямую.
 *   4. Есть утилита smbclient — пишем через неё, пароль в файле 0600.
 *   5. Ничего нет — показываем команду монтирования.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_SMB
 */
class WPPA_SMB {

	/**
	 * Константа для пароля вне базы данных.
	 */
	const PASSWORD_CONSTANT = 'WPPA_SMB_PASSWORD';

	/**
	 * Значения по умолчанию.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'path' => '',
			'user' => '',
			'pass' => '',
		);
	}

	/**
	 * Настройки подключения.
	 *
	 * @return array
	 */
	public static function config() {
		$saved = WPPA_Plugin::setting( 'smb', array() );
		$saved = is_array( $saved ) ? $saved : array();
		$out   = array();

		foreach ( self::defaults() as $key => $default ) {
			$out[ $key ] = array_key_exists( $key, $saved ) ? $saved[ $key ] : $default;
		}

		$out['path'] = trim( (string) $out['path'] );
		$out['user'] = trim( (string) $out['user'] );
		$out['pass'] = self::password( (string) $out['pass'] );

		/**
		 * Фильтр настроек подключения к сетевой папке.
		 *
		 * @param array $out Настройки.
		 */
		return apply_filters( 'wppa_smb', $out );
	}

	/**
	 * Пароль: константа в wp-config.php важнее сохранённого в базе.
	 *
	 * @param string $saved Пароль из настроек.
	 * @return string
	 */
	public static function password( $saved ) {
		if ( defined( self::PASSWORD_CONSTANT ) && '' !== (string) constant( self::PASSWORD_CONSTANT ) ) {
			return (string) constant( self::PASSWORD_CONSTANT );
		}

		return (string) $saved;
	}

	/**
	 * Задан ли путь к сетевой папке.
	 *
	 * @param array $config Настройки.
	 * @return bool
	 */
	public static function is_configured( $config = array() ) {
		return '' !== self::path_of( $config );
	}

	/**
	 * Тип доставки: файловая система или SMB.
	 *
	 * @param array $config Настройки.
	 * @return string filesystem|smb
	 */
	public static function detect_type( $config = array() ) {
		$path = self::path_of( $config );

		if ( preg_match( '#^smb://#i', $path ) ) {
			return 'smb';
		}

		// \\server\share — UNC.
		if ( preg_match( '#^[\\\\]{2,}#', $path ) ) {
			return 'smb';
		}

		// //server/share — только если есть второй сегмент.
		if ( preg_match( '#^//[^/]+/#', $path ) ) {
			return 'smb';
		}

		return 'filesystem';
	}

	/**
	 * Путь из настроек: массив настроек или готовая строка.
	 *
	 * Раньше сюда можно было передать только массив: строка давала
	 * «Cannot access offset of type string on string» — фатальную ошибку
	 * на странице инструментов, если опция повреждена.
	 *
	 * @param array|string $config Настройки или путь.
	 * @return string
	 */
	public static function path_of( $config = array() ) {
		if ( is_string( $config ) ) {
			return trim( $config );
		}

		$config = $config ? (array) $config : self::config();

		return isset( $config['path'] ) ? trim( (string) $config['path'] ) : '';
	}

	/**
	 * Разбор пути SMB.
	 *
	 * Поддерживает \\server\share\dir, //server/share/dir и
	 * smb://[user[:pass]@]server/share/dir.
	 *
	 * @param string $path Путь.
	 * @return array|null host, share, subdir, user, password, unc, service.
	 */
	public static function parse_path( $path ) {
		$path = trim( (string) $path, " \t\n\r\0\x0B\"'" );

		if ( '' === $path ) {
			return null;
		}

		$user = '';
		$pass = '';

		if ( preg_match( '#^smb://#i', $path ) ) {
			$url = wp_parse_url( $path );

			if ( $url && ! empty( $url['host'] ) ) {
				$user = isset( $url['user'] ) ? urldecode( $url['user'] ) : '';
				$pass = isset( $url['pass'] ) ? urldecode( $url['pass'] ) : '';
				$path = '/' . $url['host'] . ( isset( $url['path'] ) ? $url['path'] : '' );
			}
		}

		$norm  = str_replace( '\\', '/', $path );
		$parts = array_values( array_filter( explode( '/', $norm ), 'strlen' ) );

		if ( count( $parts ) < 2 ) {
			return null;
		}

		$host  = array_shift( $parts );
		$share = array_shift( $parts );
		$sub   = implode( '/', $parts );

		return array(
			'host'     => $host,
			'share'    => $share,
			'subdir'   => $sub,
			'user'     => $user,
			'password' => $pass,
			'unc'      => '\\\\' . $host . '\\' . $share . ( '' !== $sub ? '\\' . str_replace( '/', '\\', $sub ) : '' ),
			'service'  => '//' . $host . '/' . $share,
		);
	}

	/**
	 * Логин и домен из строки «DOMAIN\user» или «DOMAIN/user».
	 *
	 * @param string $user Логин из настроек.
	 * @param string $url_user Логин из URL, если в настройках пусто.
	 * @return array domain, user
	 */
	public static function split_user( $user, $url_user = '' ) {
		$user = '' !== $user ? $user : (string) $url_user;
		$user = trim( $user );

		if ( false !== strpos( $user, '\\' ) ) {
			list( $domain, $login ) = explode( '\\', $user, 2 );
			return array( 'domain' => $domain, 'user' => $login );
		}

		if ( false !== strpos( $user, '/' ) ) {
			list( $domain, $login ) = explode( '/', $user, 2 );
			return array( 'domain' => $domain, 'user' => $login );
		}

		return array( 'domain' => '', 'user' => $user );
	}

	/**
	 * Есть ли расширение php-smbclient.
	 *
	 * @return bool
	 */
	public static function has_extension() {
		return function_exists( 'smbclient_state_new' ) && function_exists( 'smbclient_open' );
	}

	/**
	 * Разрешён ли запуск внешних команд.
	 *
	 * @return bool
	 */
	public static function can_exec() {
		if ( ! function_exists( 'exec' ) ) {
			return false;
		}

		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );

		return ! in_array( 'exec', $disabled, true );
	}

	/**
	 * Запуск команды (в тестах подменяется фильтром).
	 *
	 * @param string $command Команда.
	 * @return array code, output.
	 */
	public static function run( $command ) {
		/**
		 * Фильтр запуска команд.
		 *
		 * @param array|null $result null — запускать штатно.
		 * @param string     $command Команда.
		 */
		$result = apply_filters( 'wppa_smb_runner', null, $command );

		if ( is_array( $result ) ) {
			return array(
				'code'   => isset( $result['code'] ) ? (int) $result['code'] : 1,
				'output' => isset( $result['output'] ) ? (string) $result['output'] : '',
			);
		}

		if ( ! self::can_exec() ) {
			return array( 'code' => 1, 'output' => '' );
		}

		$output = array();
		$code   = 1;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		exec( $command, $output, $code );

		return array(
			'code'   => (int) $code,
			'output' => implode( "\n", (array) $output ),
		);
	}

	/**
	 * Путь к утилите smbclient.
	 *
	 * @return string Пустая строка, если утилиты нет.
	 */
	public static function smbclient_binary() {
		$candidates = array( '/usr/bin/smbclient', '/usr/local/bin/smbclient', '/bin/smbclient', '/opt/homebrew/bin/smbclient', '/usr/pkg/bin/smbclient' );

		foreach ( $candidates as $candidate ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( @is_file( $candidate ) && @is_executable( $candidate ) ) {
				return $candidate;
			}
		}

		if ( ! self::can_exec() ) {
			return '';
		}

		$found = self::run( 'command -v smbclient 2>/dev/null' );

		return 0 === $found['code'] ? trim( $found['output'] ) : '';
	}

	/**
	 * Открыт ли порт SMB у хоста.
	 *
	 * @param string $host Хост.
	 * @return array ok, message
	 */
	public static function port_check( $host ) {
		/**
		 * Фильтр проверки портов SMB (используется в тестах).
		 *
		 * @param bool|null $open null — проверять по-настоящему.
		 * @param string    $host Хост.
		 */
		$forced = apply_filters( 'wppa_smb_port_check', null, $host );

		if ( null !== $forced ) {
			return array(
				'ok'      => (bool) $forced,
				'message' => $forced ? '' : sprintf( /* translators: %s: хост */ __( 'Сетевой хост %s недоступен по портам SMB (445 / 139).', 'wp-personal-account' ), $host ),
			);
		}

		$errno  = 0;
		$errstr = '';

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$socket = @stream_socket_client( 'tcp://' . $host . ':445', $errno, $errstr, 2.0 );

		if ( ! $socket ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$socket = @stream_socket_client( 'tcp://' . $host . ':139', $errno, $errstr, 2.0 );
		}

		if ( $socket ) {
			fclose( $socket );

			return array( 'ok' => true, 'message' => '' );
		}

		return array(
			'ok'      => false,
			'message' => sprintf(
				/* translators: 1: хост, 2: код ошибки */
				__( 'Сетевой хост %1$s недоступен по портам SMB (445 / 139). Проверьте адрес и доступность сервера в локальной сети. Код: %2$d', 'wp-personal-account' ),
				$host,
				(int) $errno
			),
		);
	}

	/**
	 * Кавычки для команд внутри smbclient (-c).
	 *
	 * smbclient понимает только двойные кавычки: одинарные он считает частью
	 * имени, из-за чего cd 'olimp' ищет папку «'olimp'» и не находит её.
	 *
	 * @param string $value Значение.
	 * @return string
	 */
	public static function quote_arg( $value ) {
		return '"' . str_replace( '"', '', (string) $value ) . '"';
	}

	/**
	 * Временный файл.
	 *
	 * wp_tempnam() живёт в wp-admin/includes/file.php: на фронтенде этот файл
	 * не подключён, поэтому вызов напрямую даёт fatal error. Подключаем файл
	 * сами, а если его нет — создаём файл через tempnam().
	 *
	 * @param string $name Подсказка для имени файла.
	 * @return string|false Путь к файлу или false.
	 */
	public static function temp_file( $name = '' ) {
		if ( ! function_exists( 'wp_tempnam' ) && defined( 'ABSPATH' ) ) {
			$file = ABSPATH . 'wp-admin/includes/file.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}

		if ( function_exists( 'wp_tempnam' ) ) {
			$path = wp_tempnam( (string) $name );

			if ( $path ) {
				return $path;
			}
		}

		$dir  = function_exists( 'get_temp_dir' ) ? get_temp_dir() : sys_get_temp_dir();
		$path = tempnam( $dir, 'wppa' );

		if ( false === $path ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		@chmod( $path, 0600 );

		return $path;
	}

	/**
	 * Временный файл учётных данных (0600): пароль не виден в ps.
	 *
	 * @param array  $config    Настройки.
	 * @param array  $parsed    Разобранный путь.
	 * @return string|false
	 */
	public static function credentials_file( $config, $parsed ) {
		$login = self::split_user( $config['user'], isset( $parsed['user'] ) ? $parsed['user'] : '' );
		$pass  = '' !== $config['pass'] ? $config['pass'] : ( isset( $parsed['password'] ) ? $parsed['password'] : '' );

		$file = self::temp_file( 'wppa_smb_creds_' );

		if ( ! $file ) {
			return false;
		}

		$content = 'username = ' . $login['user'] . "\n" . 'password = ' . $pass . "\n";

		if ( '' !== $login['domain'] ) {
			$content .= 'domain = ' . $login['domain'] . "\n";
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $file, $content ) ) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@chmod( $file, 0600 );

		return $file;
	}

	/**
	 * Аргументы авторизации для smbclient: -A файл или -N без пароля.
	 *
	 * @param array $config Настройки.
	 * @param array $parsed Разобранный путь.
	 * @return array args, credentials.
	 */
	public static function auth_args( $config, $parsed ) {
		$login = self::split_user( $config['user'], isset( $parsed['user'] ) ? $parsed['user'] : '' );
		$pass  = '' !== $config['pass'] ? $config['pass'] : ( isset( $parsed['password'] ) ? $parsed['password'] : '' );

		if ( '' === $login['user'] && '' === $pass ) {
			return array( 'args' => '-N', 'credentials' => '' );
		}

		$file = self::credentials_file( $config, $parsed );

		if ( ! $file ) {
			return array( 'args' => '', 'credentials' => '' );
		}

		return array( 'args' => '-A ' . escapeshellarg( $file ), 'credentials' => $file );
	}

	/**
	 * Проверка записи в локальную (в том числе смонтированную) папку.
	 *
	 * @param string $path Путь.
	 * @return array ok, message
	 */
	public static function test_filesystem( $path ) {
		if ( '' === $path ) {
			return array(
				'ok'      => false,
				'message' => __( 'Путь к сетевой папке не задан.', 'wp-personal-account' ),
			);
		}

		if ( ! is_dir( $path ) ) {
			if ( ! wp_mkdir_p( $path ) ) {
				return array(
					'ok'      => false,
					'message' => sprintf( /* translators: %s: путь */ __( 'Папки %s нет и создать её не удалось.', 'wp-personal-account' ), $path ),
				);
			}
		}

		if ( ! is_writable( $path ) ) {
			return array(
				'ok'      => false,
				'message' => sprintf( /* translators: %s: путь */ __( 'Папка %s есть, но запись в неё запрещена.', 'wp-personal-account' ), $path ),
			);
		}

		$probe = rtrim( $path, '/\\' ) . DIRECTORY_SEPARATOR . '.wppa-probe-' . uniqid() . '.tmp';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $probe, 'wppa' ) ) {
			return array(
				'ok'      => false,
				'message' => sprintf( /* translators: %s: путь */ __( 'Не удалось записать проверочный файл в %s.', 'wp-personal-account' ), $path ),
			);
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
		@unlink( $probe );

		return array(
			'ok'      => true,
			'message' => sprintf( /* translators: %s: путь */ __( 'Папка %s доступна для записи.', 'wp-personal-account' ), $path ),
		);
	}

	/**
	 * Проверка подключения к сетевой папке с логином и паролем.
	 *
	 * @param array $config Настройки.
	 * @return array ok, message, method.
	 */
	public static function test( $config = array() ) {
		$config = $config ? $config : self::config();

		if ( ! self::is_configured( $config ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Укажите путь к сетевой папке.', 'wp-personal-account' ),
				'method'  => '',
			);
		}

		if ( 'filesystem' === self::detect_type( $config ) ) {
			$result = self::test_filesystem( $config['path'] );

			return array(
				'ok'      => $result['ok'],
				'message' => $result['message'],
				'method'  => 'filesystem',
			);
		}

		$parsed = self::parse_path( self::path_of( $config ) );

		if ( ! $parsed ) {
			return array(
				'ok'      => false,
				'message' => sprintf( /* translators: %s: путь */ __( 'Путь «%s» не похож на сетевую папку. Нужен вид \\\\сервер\\папка или smb://сервер/папка.', 'wp-personal-account' ), $config['path'] ),
				'method'  => '',
			);
		}

		// Windows: UNC-путь доступен как обычная папка.
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			$result = self::test_filesystem( $config['path'] );

			return array(
				'ok'      => $result['ok'],
				'message' => $result['message'],
				'method'  => 'windows',
			);
		}

		// Шара уже смонтирована в ОС.
		if ( is_dir( $config['path'] ) ) {
			$result = self::test_filesystem( $config['path'] );

			return array(
				'ok'      => $result['ok'],
				'message' => sprintf( /* translators: %s: путь */ __( 'Сетевой ресурс смонтирован в систему (%s) и доступен для записи.', 'wp-personal-account' ), $config['path'] ),
				'method'  => 'mounted',
			);
		}

		$subdir = '' !== $parsed['subdir'] ? $parsed['subdir'] : '';

		// Расширение php-smbclient.
		if ( self::has_extension() ) {
			$login = self::split_user( $config['user'], $parsed['user'] );
			$pass  = '' !== $config['pass'] ? $config['pass'] : $parsed['password'];
			$state = smbclient_state_new();

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$init = @smbclient_state_init( $state, $login['domain'], $login['user'], $pass );

			if ( $init ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$list = @smbclient_opendir( $state, 'smb://' . $parsed['host'] . '/' . $parsed['share'] . ( '' !== $subdir ? '/' . $subdir : '' ) );
				@smbclient_closedir( $state );

				return array(
					'ok'      => (bool) $list,
					'message' => $list
						? sprintf( /* translators: %s: путь */ __( 'Доступ к %s получен через php-smbclient.', 'wp-personal-account' ), $parsed['unc'] )
						: sprintf( /* translators: %s: путь */ __( 'Авторизация прошла, но папка %s недоступна.', 'wp-personal-account' ), $parsed['unc'] ),
					'method'  => 'extension',
				);
			}

			return array(
				'ok'      => false,
				'message' => __( 'php-smbclient не пустил: проверьте логин и пароль.', 'wp-personal-account' ),
				'method'  => 'extension',
			);
		}

		$binary = self::smbclient_binary();

		if ( '' === $binary ) {
			return array(
				'ok'      => false,
				'message' => sprintf(
					/* translators: 1: путь, 2: ресурс */
					__( 'Для прямого подключения к «%1$s» на Linux нужна утилита smbclient (sudo apt install smbclient) либо монтирование в ОС: mount -t cifs %2$s /mnt/share.', 'wp-personal-account' ),
					$parsed['unc'],
					$parsed['service']
				),
				'method'  => '',
			);
		}

		$port = self::port_check( $parsed['host'] );

		if ( ! $port['ok'] ) {
			return array(
				'ok'      => false,
				'message' => $port['message'],
				'method'  => 'cli',
			);
		}

		$auth   = self::auth_args( $config, $parsed );
		$folder = '' !== $subdir ? 'cd ' . self::quote_arg( $subdir ) . '; ' : '';

		$command = sprintf(
			'%s %s %s -c %s -t 10 2>&1',
			escapeshellcmd( $binary ),
			escapeshellarg( $parsed['service'] ),
			$auth['args'],
			escapeshellarg( $folder . 'ls' )
		);

		$result = self::run( $command );

		if ( '' !== $auth['credentials'] ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $auth['credentials'] );
		}

		$output = trim( wp_strip_all_tags( $result['output'] ) );
		$failed = 0 !== $result['code'] || preg_match( '/NT_STATUS_[A-Z_]+/', $output );

		if ( $failed && preg_match( '/NT_STATUS_LOGON_FAILURE|NT_STATUS_ACCESS_DENIED/', $output ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Сервер SMB отклонил логин или пароль.', 'wp-personal-account' ),
				'method'  => 'cli',
			);
		}

		// Авторизация прошла, но папки внутри шары нет.
		if ( $failed && false !== strpos( $output, 'NT_STATUS_OBJECT_NAME_NOT_FOUND' ) && '' !== $subdir ) {
			return array(
				'ok'      => false,
				'message' => sprintf(
					/* translators: 1: папка, 2: ресурс */
					__( 'Подключение к %2$s есть, но папки «%1$s» в ней нет. Создайте её на сервере или укажите путь без неё: выгрузка создаст папку сама.', 'wp-personal-account' ),
					$subdir,
					$parsed['service']
				),
				'method'  => 'cli',
			);
		}

		return array(
			'ok'      => ! $failed,
			'message' => $failed
				? sprintf( /* translators: %s: вывод smbclient */ __( 'smbclient вернул ошибку: %s', 'wp-personal-account' ), $output )
				: sprintf( /* translators: %s: путь */ __( 'Доступ к %s получен через smbclient.', 'wp-personal-account' ), $parsed['unc'] ),
			'method'  => 'cli',
		);
	}

	/**
	 * Записать файл в сетевую папку.
	 *
	 * @param string $name     Имя файла.
	 * @param string $contents Содержимое.
	 * @param array  $config   Настройки.
	 * @return array ok, message, file, bytes.
	 */
	public static function write( $name, $contents, $config = array(), $subdir = '' ) {
		$config = $config ? $config : self::config();
		$remote = '';
		$subdir = trim( str_replace( array( '\\', '..' ), array( '/', '' ), (string) $subdir ), '/' );

		if ( ! self::is_configured( $config ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Путь к сетевой папке не задан.', 'wp-personal-account' ),
				'file'    => '',
				'bytes'   => 0,
			);
		}

		$parsed = 'smb' === self::detect_type( $config ) ? self::parse_path( self::path_of( $config ) ) : null;

		// Подпапка команды добавляется к подпапке из настроек.
		$sub_all = '';

		if ( $parsed ) {
			// $parsed['unc'] уже содержит подпапку из настроек — добавляем только папку команды.
			$sub_all = trim( $parsed['subdir'] . ( '' !== $subdir ? '/' . $subdir : '' ), '/' );
			$remote  = $parsed['unc'] . ( '' !== $subdir ? '\\' . str_replace( '/', '\\', $subdir ) : '' ) . '\\' . $name;
		}

		// 1. Обычная или смонтированная папка (в том числе UNC на Windows).
		$local_dir = $config['path'];

		if ( ! $parsed || is_dir( $local_dir ) ) {
			if ( '' !== $subdir ) {
				$local_dir = rtrim( $local_dir, '/\\' ) . DIRECTORY_SEPARATOR . $subdir;
			}

			if ( ! is_dir( $local_dir ) && ! wp_mkdir_p( $local_dir ) ) {
				return array(
					'ok'      => false,
					'message' => sprintf( /* translators: %s: папка */ __( 'Папки %s нет и создать её не удалось.', 'wp-personal-account' ), $local_dir ),
					'file'    => $remote,
					'bytes'   => 0,
				);
			}

			$full = rtrim( $local_dir, '/\\' ) . DIRECTORY_SEPARATOR . $name;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$written = file_put_contents( $full, $contents, LOCK_EX );

			return array(
				'ok'      => false !== $written,
				'message' => false !== $written
					? sprintf( /* translators: %s: файл */ __( 'Файл %s записан в папку.', 'wp-personal-account' ), $full )
					: sprintf( /* translators: %s: файл */ __( 'Не удалось записать %s.', 'wp-personal-account' ), $full ),
				'file'    => $full,
				'bytes'   => false !== $written ? (int) $written : 0,
			);
		}

		// 2. Напрямую через php-smbclient.
		if ( self::has_extension() ) {
			$login = self::split_user( $config['user'], $parsed['user'] );
			$pass  = '' !== $config['pass'] ? $config['pass'] : $parsed['password'];
			$state = smbclient_state_new();

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! @smbclient_state_init( $state, $login['domain'], $login['user'], $pass ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'php-smbclient: не удалось авторизоваться.', 'wp-personal-account' ),
					'file'    => $remote,
					'bytes'   => 0,
				);
			}

			$dir_target = 'smb://' . $parsed['host'] . '/' . $parsed['share'] . ( '' !== $sub_all ? '/' . $sub_all : '' );
			$target     = $dir_target . '/' . $name;

			// Папки команды может не быть — создаём, ошибку игнорируем и пробуем писать.
			if ( '' !== $sub_all && function_exists( 'smbclient_mkdir' ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				@smbclient_mkdir( $state, $dir_target );
			}

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$handle = @smbclient_open( $state, $target, 'w' );

			if ( ! $handle ) {
				return array(
					'ok'      => false,
					'message' => sprintf( /* translators: %s: файл */ __( 'php-smbclient: не удалось открыть %s на запись.', 'wp-personal-account' ), $target ),
					'file'    => $remote,
					'bytes'   => 0,
				);
			}

			$written = @smbclient_write( $state, $handle, $contents ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			smbclient_close( $state, $handle );

			return array(
				'ok'      => false !== $written && strlen( $contents ) === (int) $written,
				'message' => sprintf( /* translators: %s: файл */ __( 'Файл %s записан через php-smbclient.', 'wp-personal-account' ), $remote ),
				'file'    => $remote,
				'bytes'   => false !== $written ? (int) $written : 0,
			);
		}

		// 3. Через утилиту smbclient.
		$binary = self::smbclient_binary();

		if ( '' !== $binary ) {
			$temp = self::temp_file( $name );

			if ( ! $temp ) {
				return array(
					'ok'      => false,
					'message' => __( 'Не удалось создать временный файл для выгрузки.', 'wp-personal-account' ),
					'file'    => $remote,
					'bytes'   => 0,
				);
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $temp, $contents );

			$auth = self::auth_args( $config, $parsed );
			$put  = sprintf( 'put %s %s', self::quote_arg( $temp ), self::quote_arg( $name ) );
			$sub  = $sub_all;

			$cd      = '' !== $sub ? 'cd ' . self::quote_arg( $sub ) . '; ' : '';
			$prepare = '' !== $sub ? 'mkdir ' . self::quote_arg( $sub ) . '; cd ' . self::quote_arg( $sub ) . '; ' : '';

			$run = static function ( $script ) use ( $binary, $parsed, $auth ) {
				return WPPA_SMB::run(
					sprintf(
						'%s %s %s -c %s -t 20 2>&1',
						escapeshellcmd( $binary ),
						escapeshellarg( $parsed['service'] ),
						$auth['args'],
						escapeshellarg( $script )
					)
				);
			};

			$result  = $run( $cd . $put );
			$output  = trim( wp_strip_all_tags( $result['output'] ) );
			$failed  = 0 !== $result['code'] || preg_match( '/NT_STATUS_[A-Z_]+/', $output );
			$created = false;

			// Папки внутри шары может не быть — создаём её и повторяем запись.
			if ( $failed && '' !== $sub && false !== strpos( $output, 'NT_STATUS_OBJECT_NAME_NOT_FOUND' ) ) {
				$result  = $run( $prepare . $put );
				$output  = trim( wp_strip_all_tags( $result['output'] ) );
				$failed  = 0 !== $result['code'] || preg_match( '/NT_STATUS_[A-Z_]+/', $output );
				$created = ! $failed;
			}

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $temp );

			if ( '' !== $auth['credentials'] ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $auth['credentials'] );
			}

			if ( $failed ) {
				return array(
					'ok'      => false,
					'message' => sprintf( /* translators: %s: вывод smbclient */ __( 'smbclient вернул ошибку: %s', 'wp-personal-account' ), $output ),
					'file'    => $remote,
					'bytes'   => 0,
				);
			}

			return array(
				'ok'      => true,
				'message' => $created
					? sprintf(
						/* translators: 1: файл, 2: папка */
						__( 'Файл %1$s записан через smbclient, папка «%2$s» создана на сервере.', 'wp-personal-account' ),
						$remote,
						$sub
					)
					: sprintf( /* translators: %s: файл */ __( 'Файл %s записан через smbclient.', 'wp-personal-account' ), $remote ),
				'file'    => $remote,
				'bytes'   => strlen( $contents ),
			);
		}

		$mount = self::mount_command( $config );

		return array(
			'ok'      => false,
			'message' => sprintf(
				/* translators: %s: команда */
				__( 'Нет ни php-smbclient, ни smbclient. Смонтируйте сетевую папку в ОС: %s', 'wp-personal-account' ),
				$mount['command']
			),
			'file'    => $remote,
			'bytes'   => 0,
		);
	}

	/**
	 * Удалить файл из сетевой папки.
	 *
	 * Нужно, чтобы копия прикреплённого файла не оставалась в папке после его
	 * удаления в кабинете. Ошибки не считаем критичными: файл мог быть убран
	 * вручную или папка недоступна.
	 *
	 * @param string $name   Имя файла.
	 * @param array  $config Настройки.
	 * @param string $subdir Подпапка команды.
	 * @return bool
	 */
	public static function delete( $name, $config = array(), $subdir = '' ) {
		$config = $config ? $config : self::config();
		$subdir = trim( str_replace( array( '\\', '..' ), array( '/', '' ), (string) $subdir ), '/' );

		if ( ! self::is_configured( $config ) || '' === (string) $name ) {
			return false;
		}

		$parsed  = 'smb' === self::detect_type( $config ) ? self::parse_path( self::path_of( $config ) ) : null;
		$sub_all = $parsed ? trim( $parsed['subdir'] . ( '' !== $subdir ? '/' . $subdir : '' ), '/' ) : $subdir;

		// 1. Обычная или смонтированная папка.
		if ( ! $parsed || is_dir( $config['path'] ) ) {
			$full = rtrim( $config['path'], '/\\' ) . ( '' !== $sub_all ? DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $sub_all ) : '' ) . DIRECTORY_SEPARATOR . $name;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			return is_file( $full ) ? @unlink( $full ) : false;
		}

		// 2. php-smbclient.
		if ( self::has_extension() ) {
			$login = self::split_user( $config['user'], $parsed['user'] );
			$pass  = '' !== $config['pass'] ? $config['pass'] : $parsed['password'];
			$state = smbclient_state_new();

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! @smbclient_state_init( $state, $login['domain'], $login['user'], $pass ) ) {
				return false;
			}

			$target = 'smb://' . $parsed['host'] . '/' . $parsed['share'] . ( '' !== $sub_all ? '/' . $sub_all : '' ) . '/' . $name;

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$ok = @smbclient_unlink( $state, $target );

			return (bool) $ok;
		}

		// 3. Через утилиту smbclient.
		$binary = self::smbclient_binary();

		if ( '' === $binary ) {
			return false;
		}

		$auth = self::auth_args( $config, $parsed );
		$path = ( '' !== $sub_all ? $sub_all . '/' : '' ) . $name;
		$run  = WPPA_SMB::run(
			sprintf(
				'%s %s %s -c %s -t 20 2>&1',
				escapeshellcmd( $binary ),
				escapeshellarg( $parsed['service'] ),
				$auth['args'],
				escapeshellarg( 'del ' . self::quote_arg( $path ) )
			)
		);

		if ( '' !== $auth['credentials'] ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $auth['credentials'] );
		}

		return 0 === $run['code'] && ! preg_match( '/NT_STATUS_[A-Z_]+/', trim( (string) $run['output'] ) );
	}

	/**
	 * Удалить пустую папку на диске (сетевые папки не трогаем).
	 *
	 * @param string $path Путь.
	 * @return bool
	 */
	public static function remove_dir( $path ) {
		if ( ! is_dir( $path ) || ! is_writable( $path ) ) {
			return false;
		}

		$items = @scandir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! is_array( $items ) ) {
			return false;
		}

		$items = array_diff( $items, array( '.', '..' ) );

		return empty( $items ) ? @rmdir( $path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Готовая команда монтирования с логином, паролем и доменом.
	 *
	 * Пароль выносится в файл учётных данных: иначе он виден в ps.
	 *
	 * @param array $config Настройки.
	 * @return array command, credentials_file, credentials.
	 */
	public static function mount_command( $config = array() ) {
		$config = $config ? $config : self::config();
		$parsed = self::parse_path( self::path_of( $config ) );
		$login  = self::split_user( $config['user'], $parsed ? $parsed['user'] : '' );
		$pass   = '' !== $config['pass'] ? $config['pass'] : ( $parsed ? $parsed['password'] : '' );
		$file   = '/etc/wppa-smb-credentials';

		$credentials = 'username=' . $login['user'] . "\n" . 'password=' . $pass . "\n";

		if ( '' !== $login['domain'] ) {
			$credentials .= 'domain=' . $login['domain'] . "\n";
		}

		$command = sprintf(
			'mount -t cifs %s /mnt/share -o credentials=%s,uid=www-data,gid=www-data,iocharset=utf8,vers=3.0',
			escapeshellarg( $parsed ? $parsed['service'] : $config['path'] ),
			escapeshellarg( $file )
		);

		return array(
			'command'          => $command,
			'credentials_file' => $file,
			'credentials'      => $credentials,
		);
	}
}
