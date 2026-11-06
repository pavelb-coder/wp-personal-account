<?php
/**
 * Вход и регистрация.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Auth
 */
class WPPA_Auth {

	/**
	 * Максимум попыток входа.
	 */
	const MAX_ATTEMPTS = 5;

	/**
	 * Окно блокировки в секундах.
	 */
	const LOCK_WINDOW = 900;

	/**
	 * Обработка формы входа.
	 *
	 * @return void
	 */
	public static function handle_login() {
		check_admin_referer( 'wppa_login', 'wppa_nonce' );

		$tab_url = WPPA_Plugin::tab_url( WPPA_Account::current_tab() );
		$key     = self::client_key();

		if ( self::is_locked( $key ) ) {
			WPPA_Notices::redirect( 'login_blocked', $tab_url );
		}

		$login    = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		$password = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- пароль не санитизируем.

		if ( '' === $login || '' === $password ) {
			WPPA_Notices::redirect( 'empty_fields', $tab_url );
		}

		if ( is_email( $login ) ) {
			$user = get_user_by( 'email', $login );
			if ( $user ) {
				$login = $user->user_login;
			}
		}

		$creds = array(
			'user_login'    => $login,
			'user_password' => $password,
			'remember'      => ! empty( $_POST['rememberme'] ),
		);

		$result = wp_signon( $creds, is_ssl() );

		if ( is_wp_error( $result ) ) {
			self::register_attempt( $key );
			WPPA_Notices::redirect( 'login_failed', $tab_url );
		}

		self::clear_attempts( $key );

		$redirect = '';

		if ( ! empty( $_POST['redirect_to'] ) ) {
			$redirect = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ), '' );
		}

		if ( ! $redirect ) {
			$redirect = WPPA_Plugin::setting( 'login_redirect', '' );
		}

		if ( ! $redirect ) {
			$redirect = WPPA_Plugin::tab_url( 'dashboard' );
		}

		WPPA_Notices::redirect( 'login_success', $redirect );
	}

	/**
	 * Обработка формы регистрации.
	 *
	 * @return void
	 */
	public static function handle_register() {
		check_admin_referer( 'wppa_register', 'wppa_nonce' );

		$tab_url = WPPA_Plugin::register_url();

		if ( ! WPPA_Plugin::registration_open() ) {
			WPPA_Notices::redirect( 'registration_closed', $tab_url );
		}

		$window = WPPA_Plugin::registration_window();

		if ( 'open' !== $window['state'] ) {
			WPPA_Notices::redirect( 'before' === $window['state'] ? 'registration_not_yet' : 'registration_over', $tab_url );
		}

		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$repeat   = isset( $_POST['password_confirm'] ) ? (string) wp_unslash( $_POST['password_confirm'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$consent  = ! empty( $_POST['consent'] );

		if ( ! $consent ) {
			WPPA_Notices::redirect( 'no_consent', $tab_url );
		}

		// Дополнительные персональные данные.
		$fields  = array();
		$raw     = WPPA_Fields::values_from_request( $_POST );

		foreach ( $raw as $field_id => $field_value ) {
			$fields[ $field_id ] = WPPA_Fields::sanitize_value( $field_id, $field_value );
		}

		// Имя: собираем в порядке «Фамилия Имя Отчество», иначе — прежнее поле display_name.
		$name = trim(
			( isset( $fields['last_name'] ) ? $fields['last_name'] : '' ) . ' ' .
			( isset( $fields['first_name'] ) ? $fields['first_name'] : '' ) . ' ' .
			( isset( $fields['patronymic'] ) ? $fields['patronymic'] : '' )
		);

		if ( '' === $name && isset( $_POST['display_name'] ) ) {
			$name = sanitize_text_field( wp_unslash( $_POST['display_name'] ) );
		}

		if ( '' === $email || '' === $password || '' === $repeat ) {
			WPPA_Notices::redirect( 'empty_fields', $tab_url );
		}

		$field_error = WPPA_Fields::validate( $fields, 'register' );

		if ( '' !== $field_error ) {
			WPPA_Notices::redirect( $field_error, $tab_url );
		}

		if ( ! is_email( $email ) ) {
			WPPA_Notices::redirect( 'email_invalid', $tab_url );
		}

		if ( email_exists( $email ) ) {
			WPPA_Notices::redirect( 'email_exists', $tab_url );
		}

		if ( $password !== $repeat ) {
			WPPA_Notices::redirect( 'password_mismatch', $tab_url );
		}

		$password_error = WPPA_Password::error_code(
			$password,
			array(
				'email' => $email,
				'name'  => $name,
			)
		);

		if ( '' !== $password_error ) {
			WPPA_Notices::redirect( $password_error, $tab_url );
		}

		$username = self::unique_username( $email );
		$default  = get_option( 'default_role', 'subscriber' );

		if ( '' === $name ) {
			$name = $username;
		}

		$user_data = array_merge(
			WPPA_Fields::user_data( $fields ),
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $password,
				'display_name' => $name,
				'nickname'     => $name,
				'role'         => $default ? $default : 'subscriber',
			)
		);

		$user_id = wp_insert_user( $user_data );

		if ( is_wp_error( $user_id ) ) {
			$code = $user_id->get_error_code();
			WPPA_Notices::redirect( 'existing_user_email' === $code ? 'email_exists' : 'save_failed', $tab_url );
		}

		// Мета-поля: телефон, город, организация, должность и т. д.
		WPPA_Fields::save_meta( $user_id, $fields );
		update_user_meta( $user_id, '_wppa_consent_time', time() );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );

		WPPA_Notices::redirect( 'registration_success', WPPA_Plugin::tab_url( 'dashboard' ) );
	}

	/**
	 * Уникальное имя пользователя на основе email.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	private static function unique_username( $email ) {
		$base = strstr( $email, '@', true );
		$base = sanitize_user( $base ? $base : $email, true );
		$base = '' !== $base ? $base : 'user';

		$candidate = $base;
		$i         = 1;

		while ( username_exists( $candidate ) ) {
			++$i;
			$candidate = $base . '_' . $i;
			if ( $i > 500 ) {
				$candidate = 'user_' . wp_generate_password( 6, false, false );
				break;
			}
		}

		return $candidate;
	}

	/**
	 * Ключ клиента для лимита попыток.
	 *
	 * @return string
	 */
	private static function client_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'wppa_lp_' . md5( $ip . '|' . AUTH_SALT );
	}

	/**
	 * Проверка блокировки.
	 *
	 * @param string $key Ключ.
	 * @return bool
	 */
	private static function is_locked( $key ) {
		$attempts = (int) get_transient( $key );
		return $attempts >= self::MAX_ATTEMPTS;
	}

	/**
	 * Учёт неудачной попытки.
	 *
	 * @param string $key Ключ.
	 * @return void
	 */
	private static function register_attempt( $key ) {
		$attempts = (int) get_transient( $key );
		set_transient( $key, $attempts + 1, self::LOCK_WINDOW );
	}

	/**
	 * Сброс счётчика после успешного входа.
	 *
	 * @param string $key Ключ.
	 * @return void
	 */
	private static function clear_attempts( $key ) {
		delete_transient( $key );
	}
}
