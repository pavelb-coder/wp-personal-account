<?php
/**
 * Восстановление пароля внутри личного кабинета: заявка на сброс и установка нового пароля.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Reset
 */
class WPPA_Reset {

	/**
	 * Сколько живёт ссылка сброса (сутки).
	 */
	const LINK_TTL = 86400;

	/**
	 * Пауза между заявками на сброс для одного адреса.
	 */
	const THROTTLE = 60;

	/**
	 * Ключ транзиента заявки на сброс.
	 *
	 * @param string $login Логин или email.
	 * @return string
	 */
	private static function throttle_key( $login ) {
		$login = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $login ) : strtolower( (string) $login );
		return 'wppa_rp_' . md5( $login . '|' . (string) get_current_user_id() );
	}

	/**
	 * Слишком часто запрашивают сброс?
	 *
	 * @param string $login Логин или email.
	 * @return bool
	 */
	public static function is_throttled( $login ) {
		return (bool) get_transient( self::throttle_key( $login ) );
	}

	/**
	 * Отметить заявку на сброс.
	 *
	 * @param string $login Логин или email.
	 * @return void
	 */
	private static function mark_throttled( $login ) {
		set_transient( self::throttle_key( $login ), 1, self::THROTTLE );
	}

	/**
	 * Пользователь по логину или email.
	 *
	 * @param string $login Логин или email.
	 * @return WP_User|null
	 */
	public static function find_user( $login ) {
		$login = trim( (string) $login );

		if ( '' === $login ) {
			return null;
		}

		$user = false;

		if ( is_email( $login ) ) {
			$user = get_user_by( 'email', $login );
		}

		if ( ! $user ) {
			$user = get_user_by( 'login', $login );
		}

		return $user instanceof WP_User ? $user : null;
	}

	/**
	 * Ссылка на форму установки нового пароля.
	 *
	 * @param string $login Логин.
	 * @param string $key   Ключ сброса.
	 * @return string
	 */
	public static function reset_url( $login, $key ) {
		return WPPA_Plugin::account_url(
			array(
				'wppa_view'  => 'resetpass',
				'wppa_login' => rawurlencode( (string) $login ),
				'wppa_key'   => rawurlencode( (string) $key ),
			)
		);
	}

	/**
	 * Текст письма со ссылкой сброса.
	 *
	 * @param WP_User $user Пользователь.
	 * @param string  $key  Ключ сброса.
	 * @return string
	 */
	public static function mail_body( $user, $key ) {
		$url = self::reset_url( $user->user_login, $key );

		$body = sprintf(
			/* translators: %s: имя пользователя */
			__( 'Здравствуйте, %s!', 'wp-personal-account' ),
			$user->display_name
		) . "\n\n";

		$body .= __( 'Вы запросили восстановление пароля в личном кабинете. Ссылка действует 24 часа и работает один раз:', 'wp-personal-account' ) . "\n\n";
		$body .= $url . "\n\n";
		$body .= __( 'Если вы не запрашивали сброс пароля, просто проигнорируйте это письмо — пароль останется прежним.', 'wp-personal-account' ) . "\n";

		/**
		 * Фильтр текста письма для сброса пароля.
		 *
		 * @param string  $body Текст.
		 * @param WP_User $user Пользователь.
		 * @param string  $url  Ссылка сброса.
		 */
		return (string) apply_filters( 'wppa_reset_password_body', $body, $user, $url );
	}

	/**
	 * Отправка письма со ссылкой сброса.
	 *
	 * @param WP_User $user Пользователь.
	 * @return bool
	 */
	public static function send_mail( $user ) {
		$key = get_password_reset_key( $user );

		if ( is_wp_error( $key ) ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: название сайта */
			__( '[%s] Восстановление пароля', 'wp-personal-account' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		/**
		 * Фильтр темы письма для сброса пароля.
		 *
		 * @param string  $subject Тема.
		 * @param WP_User $user    Пользователь.
		 */
		$subject = (string) apply_filters( 'wppa_reset_password_subject', $subject, $user );

		return (bool) wp_mail(
			$user->user_email,
			$subject,
			self::mail_body( $user, $key ),
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	/**
	 * Проверка ключа сброса.
	 *
	 * @param string $key   Ключ.
	 * @param string $login Логин.
	 * @return WP_User|null
	 */
	public static function validate( $key, $login ) {
		$key   = (string) $key;
		$login = (string) $login;

		if ( '' === $key || '' === $login ) {
			return null;
		}

		$user = check_password_reset_key( $key, $login );

		return $user instanceof WP_User ? $user : null;
	}

	/**
	 * Обработка заявки «Забыли пароль?».
	 *
	 * @return void
	 */
	public static function handle_request() {
		check_admin_referer( 'wppa_lostpassword', 'wppa_nonce' );

		$back  = WPPA_Plugin::lost_password_url();
		$login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';

		if ( '' === $login ) {
			WPPA_Notices::redirect( 'reset_login_empty', $back );
		}

		if ( self::is_throttled( $login ) ) {
			WPPA_Notices::redirect( 'reset_too_fast', $back );
		}

		self::mark_throttled( $login );

		$user = self::find_user( $login );

		// Ответ всегда одинаковый: не подсказываем, есть ли такой пользователь.
		if ( $user ) {
			self::send_mail( $user );
		}

		WPPA_Notices::redirect( 'reset_sent', WPPA_Plugin::account_url( array( 'wppa_view' => 'login' ) ) );
	}

	/**
	 * Обработка формы установки нового пароля.
	 *
	 * @return void
	 */
	public static function handle_reset() {
		check_admin_referer( 'wppa_resetpass', 'wppa_nonce' );

		$key      = isset( $_POST['wppa_key'] ) ? sanitize_text_field( wp_unslash( $_POST['wppa_key'] ) ) : '';
		$login    = isset( $_POST['wppa_login'] ) ? sanitize_text_field( wp_unslash( $_POST['wppa_login'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$repeat   = isset( $_POST['password_confirm'] ) ? (string) wp_unslash( $_POST['password_confirm'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$back = self::reset_url( $login, $key );

		$user = self::validate( $key, $login );

		if ( ! $user ) {
			WPPA_Notices::redirect( 'reset_link_invalid', WPPA_Plugin::lost_password_url() );
		}

		if ( $password !== $repeat ) {
			WPPA_Notices::redirect( 'password_mismatch', $back );
		}

		$error = WPPA_Password::error_code(
			$password,
			array(
				'email'      => $user->user_email,
				'login'      => $user->user_login,
				'name'       => $user->display_name,
				'first_name' => $user->first_name,
				'last_name'  => $user->last_name,
			)
		);

		if ( '' !== $error ) {
			WPPA_Notices::redirect( $error, $back );
		}

		reset_password( $user, $password );

		WPPA_Notices::redirect( 'reset_done', WPPA_Plugin::account_url( array( 'wppa_view' => 'login' ) ) );
	}
}
