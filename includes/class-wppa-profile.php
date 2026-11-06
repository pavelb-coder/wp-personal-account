<?php
/**
 * Профиль: данные пользователя, пароль, смена email с подтверждением.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Profile
 */
class WPPA_Profile {

	/**
	 * Мета-ключи подтверждения email.
	 */
	const META_PENDING_EMAIL = '_wppa_pending_email';
	const META_EMAIL_TOKEN   = '_wppa_email_token';
	const META_EMAIL_EXPIRES = '_wppa_email_token_expires';

	/**
	 * Обновление профиля.
	 *
	 * @return void
	 */
	public static function handle_update() {
		check_admin_referer( 'wppa_profile', 'wppa_nonce' );

		if ( ! is_user_logged_in() ) {
			WPPA_Notices::redirect( 'invalid_nonce' );
		}

		$user    = wp_get_current_user();
		$tab_url = WPPA_Plugin::tab_url( 'profile' );

		$first_name   = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name    = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';

		// Дополнительные персональные данные (кроме нативных полей формы профиля).
		$fields = array();

		foreach ( WPPA_Fields::values_from_request( $_POST ) as $field_id => $field_value ) {
			$fields[ $field_id ] = WPPA_Fields::sanitize_value( $field_id, $field_value );
		}

		$field_error = WPPA_Fields::validate( $fields, 'profile' );

		if ( '' !== $field_error ) {
			WPPA_Notices::redirect( $field_error, $tab_url );
		}

		if ( '' === $display_name ) {
			$display_name = trim(
				$last_name . ' ' . $first_name . ' ' .
				( isset( $fields['patronymic'] ) ? (string) $fields['patronymic'] : '' )
			);
		}

		if ( '' === $display_name ) {
			WPPA_Notices::redirect( 'empty_fields', $tab_url );
		}

		$result = wp_update_user(
			array(
				'ID'           => $user->ID,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => $display_name,
			)
		);

		if ( is_wp_error( $result ) ) {
			WPPA_Notices::redirect( 'save_failed', $tab_url );
		}

		WPPA_Fields::save_meta( $user->ID, $fields );

		WPPA_Notices::redirect( 'profile_saved', $tab_url );
	}

	/**
	 * Смена пароля.
	 *
	 * @return void
	 */
	public static function handle_password() {
		check_admin_referer( 'wppa_password', 'wppa_nonce' );

		if ( ! is_user_logged_in() ) {
			WPPA_Notices::redirect( 'invalid_nonce' );
		}

		$user    = wp_get_current_user();
		$tab_url = WPPA_Plugin::tab_url( 'security' );

		$current = isset( $_POST['current_password'] ) ? (string) wp_unslash( $_POST['current_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$new     = isset( $_POST['new_password'] ) ? (string) wp_unslash( $_POST['new_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$repeat  = isset( $_POST['repeat_password'] ) ? (string) wp_unslash( $_POST['repeat_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( '' === $current || '' === $new || '' === $repeat ) {
			WPPA_Notices::redirect( 'empty_fields', $tab_url );
		}

		if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
			WPPA_Notices::redirect( 'password_wrong', $tab_url );
		}

		if ( $new !== $repeat ) {
			WPPA_Notices::redirect( 'password_mismatch', $tab_url );
		}

		$password_error = WPPA_Password::error_code(
			$new,
			array(
				'email'      => $user->user_email,
				'login'      => $user->user_login,
				'name'       => $user->display_name,
				'first_name' => $user->first_name,
				'last_name'  => $user->last_name,
			)
		);

		if ( '' !== $password_error ) {
			WPPA_Notices::redirect( $password_error, $tab_url );
		}

		if ( $new === $current ) {
			WPPA_Notices::redirect( 'password_same', $tab_url );
		}

		wp_set_password( $new, $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );

		WPPA_Notices::redirect( 'password_changed', $tab_url );
	}

	/**
	 * Запрос на смену email: письмо с подтверждением.
	 *
	 * @return void
	 */
	public static function handle_email_request() {
		check_admin_referer( 'wppa_email', 'wppa_nonce' );

		if ( ! is_user_logged_in() ) {
			WPPA_Notices::redirect( 'invalid_nonce' );
		}

		$user    = wp_get_current_user();
		$tab_url = WPPA_Plugin::tab_url( 'security' );

		$new_email = isset( $_POST['new_email'] ) ? sanitize_email( wp_unslash( $_POST['new_email'] ) ) : '';

		if ( ! is_email( $new_email ) ) {
			WPPA_Notices::redirect( 'email_invalid', $tab_url );
		}

		if ( strtolower( $new_email ) === strtolower( $user->user_email ) ) {
			WPPA_Notices::redirect( 'email_same', $tab_url );
		}

		$existing = email_exists( $new_email );
		if ( $existing && (int) $existing !== (int) $user->ID ) {
			WPPA_Notices::redirect( 'email_exists', $tab_url );
		}

		$token = wp_generate_password( 32, false, false );
		$link  = WPPA_Plugin::account_url( array( 'wppa_confirm_email' => $token ) );

		update_user_meta( $user->ID, self::META_PENDING_EMAIL, $new_email );
		update_user_meta( $user->ID, self::META_EMAIL_TOKEN, wp_hash_password( $token ) );
		update_user_meta( $user->ID, self::META_EMAIL_EXPIRES, time() + DAY_IN_SECONDS );

		$subject = __( 'Подтверждение нового email', 'wp-personal-account' );
		$body    = sprintf(
			/* translators: 1: имя сайта, 2: ссылка подтверждения */
			__( "Здравствуйте!\n\nНа сайте «%1\$s» запрошена смена email для вашего аккаунта.\nПодтвердить новый адрес: %2\$s\n\nСсылка действует 24 часа. Если вы не запрашивали смену, просто проигнорируйте письмо.", 'wp-personal-account' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$link
		);

		wp_mail( $new_email, $subject, $body );

		WPPA_Notices::redirect( 'email_confirm_sent', $tab_url );
	}

	/**
	 * Подтверждение нового email по токену.
	 *
	 * @param string $token Токен из ссылки.
	 * @return void
	 */
	public static function handle_email_confirm( $token ) {
		if ( ! is_user_logged_in() ) {
			WPPA_Notices::redirect( 'email_confirm_login' );
		}

		$user     = wp_get_current_user();
		$hash     = get_user_meta( $user->ID, self::META_EMAIL_TOKEN, true );
		$expires  = (int) get_user_meta( $user->ID, self::META_EMAIL_EXPIRES, true );
		$new      = get_user_meta( $user->ID, self::META_PENDING_EMAIL, true );
		$tab_url  = WPPA_Plugin::tab_url( 'security' );

		if ( ! $hash || ! $new || $expires < time() ) {
			self::clear_email_token( $user->ID );
			WPPA_Notices::redirect( 'email_confirm_invalid', $tab_url );
		}

		if ( ! wp_check_password( $token, $hash, $user->ID ) ) {
			WPPA_Notices::redirect( 'email_confirm_invalid', $tab_url );
		}

		$existing = email_exists( $new );
		if ( $existing && (int) $existing !== (int) $user->ID ) {
			self::clear_email_token( $user->ID );
			WPPA_Notices::redirect( 'email_exists', $tab_url );
		}

		$result = wp_update_user(
			array(
				'ID'         => $user->ID,
				'user_email' => $new,
			)
		);

		self::clear_email_token( $user->ID );

		if ( is_wp_error( $result ) ) {
			WPPA_Notices::redirect( 'save_failed', $tab_url );
		}

		WPPA_Notices::redirect( 'email_confirmed', $tab_url );
	}

	/**
	 * Есть ли ожидающее подтверждение email.
	 *
	 * @param int $user_id ID пользователя.
	 * @return string Новый email или пустая строка.
	 */
	public static function pending_email( $user_id ) {
		$pending = get_user_meta( $user_id, self::META_PENDING_EMAIL, true );
		return is_string( $pending ) ? $pending : '';
	}

	/**
	 * Удаление мета-данных подтверждения.
	 *
	 * @param int $user_id ID пользователя.
	 * @return void
	 */
	private static function clear_email_token( $user_id ) {
		delete_user_meta( $user_id, self::META_PENDING_EMAIL );
		delete_user_meta( $user_id, self::META_EMAIL_TOKEN );
		delete_user_meta( $user_id, self::META_EMAIL_EXPIRES );
	}
}
