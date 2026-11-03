<?php
/**
 * Шорткоды плагина.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Shortcodes
 */
class WPPA_Shortcodes {

	/**
	 * Регистрация шорткодов.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'wppa_account', array( __CLASS__, 'account' ) );
		add_shortcode( 'wppa_login', array( __CLASS__, 'login' ) );
		add_shortcode( 'wppa_register', array( __CLASS__, 'register' ) );
		add_shortcode( 'wppa_auth', array( __CLASS__, 'auth' ) );
	}

	/**
	 * [wppa_account] — личный кабинет.
	 *
	 * @return string
	 */
	public static function account() {
		return WPPA_Template::get( 'account-wrapper' );
	}

	/**
	 * [wppa_login] — форма входа.
	 *
	 * @param array $atts Атрибуты.
	 * @return string
	 */
	public static function login( $atts ) {
		$atts = shortcode_atts(
			array(
				'redirect' => '',
				'title'    => '',
			),
			$atts,
			'wppa_login'
		);

		if ( is_user_logged_in() ) {
			return WPPA_Template::get(
				'logged-in',
				array(
					'message' => __( 'Вы уже авторизованы.', 'wp-personal-account' ),
				)
			);
		}

		return WPPA_Template::get(
			'login',
			array(
				'redirect' => esc_url_raw( $atts['redirect'] ),
				'title'    => sanitize_text_field( $atts['title'] ),
			)
		);
	}

	/**
	 * [wppa_auth] — вход и регистрация одним блоком (как виджет).
	 *
	 * @param array $atts Атрибуты.
	 * @return string
	 */
	public static function auth( $atts ) {
		$atts = shortcode_atts(
			array(
				'mode'     => 'both',
				'redirect' => '',
				'hide'     => '', // hide="0" — показывать и авторизованным.
			),
			$atts,
			'wppa_auth'
		);

		ob_start();
		WPPA_Auth_Form::render(
			array(
				'mode'             => sanitize_key( $atts['mode'] ),
				'redirect'         => esc_url_raw( $atts['redirect'] ),
				'hide_for_members' => '' === $atts['hide'] ? 1 : (int) $atts['hide'],
			)
		);

		return (string) ob_get_clean();
	}

	/**
	 * [wppa_register] — форма регистрации.
	 *
	 * @param array $atts Атрибуты.
	 * @return string
	 */
	public static function register( $atts ) {
		$atts = shortcode_atts(
			array(
				'title' => '',
			),
			$atts,
			'wppa_register'
		);

		if ( is_user_logged_in() ) {
			return WPPA_Template::get(
				'logged-in',
				array(
					'message' => __( 'Вы уже авторизованы.', 'wp-personal-account' ),
				)
			);
		}

		$allowed = (bool) WPPA_Plugin::setting( 'registration_enabled', 1 ) && get_option( 'users_can_register' );

		if ( ! $allowed ) {
			return WPPA_Template::get(
				'notice',
				array(
					'type' => 'info',
					'text' => __( 'Регистрация на сайте отключена.', 'wp-personal-account' ),
				)
			);
		}

		return WPPA_Template::get(
			'register',
			array(
				'title' => sanitize_text_field( $atts['title'] ),
			)
		);
	}
}
