<?php
/**
 * Доступ к сетевой папке SMB с авторизацией.
 *
 * Два пути: расширение php-smbclient (если установлено) или утилита smbclient
 * через командную строку. Если нет ни того, ни другого — плагин показывает,
 * как смонтировать шару с логином и паролем.
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
			'host'    => '',
			'share'   => '',
			'dir'     => '',
			'user'    => '',
			'pass'    => '',
			'domain'  => '',
			'port'    => 445,
		);
	}

	/**
	 * Настройки доступа (из общих настроек плагина).
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

		$out['host']   = preg_replace( '#^(smb|smbclient)://#i', '', trim( (string) $out['host'] ) );
		$out['host']   = trim( (string) $out['host'], "/\\ " );
		$out['share']  = trim( (string) $out['share'], "/\\ " );
		$out['dir']    = trim( (string) $out['dir'], "/\\ " );
		$out['user']   = (string) $out['user'];
		$out['domain'] = (string) $out['domain'];
		$out['port']   = max( 1, min( 65535, (int) $out['port'] ) );
		$out['pass']   = self::password( (string) $out['pass'] );

		/**
		 * Фильтр настроек SMB.
		 *
		 * @param array $out Настройки.
		 */
		return apply_filters( 'wppa_smb', $out );
	}

	/**
	 * Пароль: константа в wp-config.php важнее того, что сохранено в базе.
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
	 * Данные доступа заполнены?
	 *
	 * @param array $config Настройки.
	 * @return bool
	 */
	public static function is_configured( $config = array() ) {
		$config = $config ? $config : self::config();

		return '' !== $config['host'] && '' !== $config['share'] && '' !== $config['user'];
	}

	/**
	 * Путь к папке в терминах SMB: //host/share/dir.
	 *
	 * @param array $config Настройки.
	 * @return string
	 */
	public static function target( $config = array() ) {
		$config = $config ? $config : self::config();
		$path   = '//' . $config['host'] . '/' . $config['share'];

		if ( '' !== $config['dir'] ) {
			$path .= '/' . $config['dir'];
		}

		return $path;
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
	 * Есть ли утилита smbclient в системе.
	 *
	 * @return bool
	 */
	public static function has_cli() {
		if ( ! self::can_exec() ) {
			return false;
		}

		$found = self::run( 'command -v smbclient 2>/dev/null' );

		return 0 === $found['code'] && '' !== trim( $found['output'] );
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
	 * Запуск команды (обёртка: в тестах подменяется фильтром).
	 *
	 * @param string $command Команда.
	 * @return array code, output.
	 */
	public static function run( $command ) {
		/**
		 * Фильтр запуска команд SMB.
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

		$output = array();
		$code   = 1;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec, WordPress.Security.EscapeOutput.OutputNotEscaped
		exec( $command, $output, $code );

		return array(
			'code'   => (int) $code,
			'output' => implode( "\n", (array) $output ),
		);
	}

	/**
	 * Строка авторизации для smbclient: DOMAIN\user%pass.
	 *
	 * @param array $config Настройки.
	 * @return string
	 */
	public static function auth_string( $config = array() ) {
		$config = $config ? $config : self::config();
		$user   = '' !== $config['domain'] ? $config['domain'] . '\\' . $config['user'] : $config['user'];

		return $config['pass'] !== '' ? $user . '%' . $config['pass'] : $user;
	}

	/**
	 * Проверка доступа к шаре с учётом логина и пароля.
	 *
	 * @param array $config Настройки.
	 * @return array ok, message, method.
	 */
	public static function test( $config = array() ) {
		$config = $config ? $config : self::config();

		if ( ! self::is_configured( $config ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Укажите сервер, шару и пользователя.', 'wp-personal-account' ),
				'method'  => '',
			);
		}

		if ( self::has_extension() ) {
			$state = smbclient_state_new();

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$ok = @smbclient_state_init( $state, $config['domain'], $config['user'], $config['pass'] );

			if ( $ok ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$list = @smbclient_opendir( $state, self::target( $config ) );
				@smbclient_closedir( $state );

				return array(
					'ok'      => (bool) $list,
					'message' => $list
						? sprintf( /* translators: %s: путь */ __( 'Доступ к %s получен через php-smbclient.', 'wp-personal-account' ), self::target( $config ) )
						: sprintf( /* translators: %s: путь */ __( 'Авторизация прошла, но папка %s недоступна.', 'wp-personal-account' ), self::target( $config ) ),
					'method'  => 'extension',
				);
			}

			return array(
				'ok'      => false,
				'message' => __( 'php-smbclient не пустил: проверьте логин, пароль и домен.', 'wp-personal-account' ),
				'method'  => 'extension',
			);
		}

		if ( self::has_cli() ) {
			$command = sprintf(
				'smbclient %s -U %s -c %s 2>&1',
				escapeshellarg( self::target( $config ) ),
				escapeshellarg( self::auth_string( $config ) ),
				escapeshellarg( 'ls' )
			);
			$result  = self::run( $command );

			return array(
				'ok'      => 0 === $result['code'],
				'message' => 0 === $result['code']
					? sprintf( /* translators: %s: путь */ __( 'Доступ к %s получен через smbclient.', 'wp-personal-account' ), self::target( $config ) )
					: sprintf(
						/* translators: %s: вывод утилиты */
						__( 'smbclient не пустил: %s', 'wp-personal-account' ),
						trim( wp_strip_all_tags( $result['output'] ) )
					),
				'method'  => 'cli',
			);
		}

		return array(
			'ok'      => false,
			'message' => __( 'На сервере нет ни php-smbclient, ни утилиты smbclient. Смонтируйте шару с логином и паролем — команда есть в подсказке под полями.', 'wp-personal-account' ),
			'method'  => '',
		);
	}

	/**
	 * Записать файл в шару с авторизацией.
	 *
	 * @param string $name     Имя файла.
	 * @param string $contents Содержимое.
	 * @param array  $config   Настройки.
	 * @return array ok, message, file.
	 */
	public static function write( $name, $contents, $config = array() ) {
		$config = $config ? $config : self::config();

		if ( ! self::is_configured( $config ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Не заданы сервер, шара и пользователь SMB.', 'wp-personal-account' ),
				'file'    => '',
			);
		}

		$remote = self::target( $config ) . '/' . $name;

		if ( self::has_extension() ) {
			$state = smbclient_state_new();

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! @smbclient_state_init( $state, $config['domain'], $config['user'], $config['pass'] ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'php-smbclient: не удалось авторизоваться.', 'wp-personal-account' ),
					'file'    => $remote,
				);
			}

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$handle = @smbclient_open( $state, $remote, 'w' );

			if ( ! $handle ) {
				return array(
					'ok'      => false,
					'message' => sprintf( /* translators: %s: файл */ __( 'php-smbclient: не удалось открыть %s на запись.', 'wp-personal-account' ), $remote ),
					'file'    => $remote,
				);
			}

			$written = @smbclient_write( $state, $handle, $contents ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			smbclient_close( $state, $handle );

			return array(
				'ok'      => false !== $written && strlen( $contents ) === (int) $written,
				'message' => sprintf( /* translators: %s: файл */ __( 'Файл %s записан через php-smbclient.', 'wp-personal-account' ), $remote ),
				'file'    => $remote,
			);
		}

		if ( self::has_cli() ) {
			$temp = wp_tempnam( $name );

			if ( ! $temp ) {
				return array(
					'ok'      => false,
					'message' => __( 'Не удалось создать временный файл для выгрузки.', 'wp-personal-account' ),
					'file'    => $remote,
				);
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $temp, $contents );

			$script = '' !== $config['dir'] ? sprintf( 'cd %s; put %s %s', escapeshellarg( $config['dir'] ), escapeshellarg( $temp ), escapeshellarg( $name ) ) : sprintf( 'put %s %s', escapeshellarg( $temp ), escapeshellarg( $name ) );

			$command = sprintf(
				'smbclient %s -U %s -c %s 2>&1',
				escapeshellarg( '//' . $config['host'] . '/' . $config['share'] ),
				escapeshellarg( self::auth_string( $config ) ),
				escapeshellarg( $script )
			);

			$result = self::run( $command );
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink

			return array(
				'ok'      => 0 === $result['code'],
				'message' => 0 === $result['code']
					? sprintf( /* translators: %s: файл */ __( 'Файл %s записан через smbclient.', 'wp-personal-account' ), $remote )
					: sprintf( /* translators: %s: вывод утилиты */ __( 'smbclient вернул ошибку: %s', 'wp-personal-account' ), trim( wp_strip_all_tags( $result['output'] ) ) ),
				'file'    => $remote,
			);
		}

		return array(
			'ok'      => false,
			'message' => __( 'Нет ни php-smbclient, ни smbclient — записать в шару напрямую нельзя. Смонтируйте её и используйте режим «Папка на сервере».', 'wp-personal-account' ),
			'file'    => $remote,
		);
	}

	/**
	 * Команда монтирования с логином, паролем и доменом.
	 *
	 * Пароль выносится в файл учётных данных: иначе он виден в списке процессов.
	 *
	 * @param array $config Настройки.
	 * @return array command, credentials_file, credentials.
	 */
	public static function mount_command( $config = array() ) {
		$config = $config ? $config : self::config();
		$remote = '//' . $config['host'] . '/' . $config['share'];
		$file   = '/etc/wppa-smb-credentials';

		$credentials = "username={$config['user']}\npassword={$config['pass']}\n";

		if ( '' !== $config['domain'] ) {
			$credentials .= "domain={$config['domain']}\n";
		}

		$command = sprintf(
			'mount -t cifs %s /mnt/share -o credentials=%s,uid=www-data,gid=www-data,iocharset=utf8,vers=3.0',
			escapeshellarg( $remote ),
			escapeshellarg( $file )
		);

		return array(
			'command'           => $command,
			'credentials_file'  => $file,
			'credentials'       => $credentials,
		);
	}
}
