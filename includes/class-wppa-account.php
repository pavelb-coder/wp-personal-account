<?php
/**
 * Личный кабинет: выбор вкладки и вывод интерфейса.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Account
 */
class WPPA_Account {

	/**
	 * Текущая вкладка.
	 *
	 * @return string
	 */
	public static function current_tab() {
		$tabs = WPPA_Template::tabs();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- навигация, не изменяет данные.
		$requested = isset( $_GET['wppa_tab'] ) ? sanitize_key( wp_unslash( $_GET['wppa_tab'] ) ) : '';

		if ( $requested && isset( $tabs[ $requested ] ) ) {
			return $requested;
		}

		$keys = array_keys( $tabs );
		return (string) reset( $keys );
	}

	/**
	 * Рендер кабинета (для авторизованных) или форм входа/регистрации.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! is_user_logged_in() ) {
			self::render_guest();
			return;
		}

		$user = wp_get_current_user();

		WPPA_Template::render(
			'account',
			array(
				'user'        => $user,
				'tabs'        => WPPA_Template::tabs(),
				'current_tab' => self::current_tab(),
			)
		);
	}

	/**
	 * Экран для гостей.
	 *
	 * @return void
	 */
	private static function render_guest() {
		$registration = WPPA_Plugin::registration_open();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только выбор отображаемой формы.
		$view = isset( $_GET['wppa_view'] ) ? sanitize_key( wp_unslash( $_GET['wppa_view'] ) ) : 'login';
		if ( 'register' === $view && ! $registration ) {
			$view = 'login';
		}

		WPPA_Template::render(
			'guest',
			array(
				'view'         => $view,
				'registration' => $registration,
			)
		);
	}
}
