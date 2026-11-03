<?php
/**
 * Загрузчик шаблонов с возможностью переопределения в теме.
 *
 * Файл темы ищется по пути: yourtheme/wp-personal-account/{name}.php
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Template
 */
class WPPA_Template {

	/**
	 * Все доступные вкладки кабинета.
	 *
	 * @return array
	 */
	public static function all_tabs() {
		return array(
			'dashboard' => __( 'Обзор', 'wp-personal-account' ),
			'profile'   => __( 'Профиль', 'wp-personal-account' ),
			'team'      => __( 'Команда', 'wp-personal-account' ),
			'security'  => __( 'Безопасность', 'wp-personal-account' ),
		);
	}

	/**
	 * Включённые вкладки в нужном порядке.
	 *
	 * @return array
	 */
	public static function tabs() {
		$all     = self::all_tabs();
		$enabled = (array) WPPA_Plugin::setting( 'enabled_tabs', array_keys( $all ) );
		$tabs    = array();

		foreach ( $all as $slug => $label ) {
			if ( in_array( $slug, $enabled, true ) ) {
				$tabs[ $slug ] = $label;
			}
		}

		if ( empty( $tabs ) ) {
			$tabs = array( 'profile' => $all['profile'] );
		}

		/**
		 * Фильтр списка вкладок.
		 *
		 * @param array $tabs Вкладки slug => label.
		 */
		return apply_filters( 'wppa_tabs', $tabs );
	}

	/**
	 * Путь к шаблону с учётом переопределения в теме.
	 *
	 * @param string $name Имя шаблона без расширения.
	 * @return string
	 */
	public static function locate( $name ) {
		$name = sanitize_file_name( $name );

		$theme = locate_template( array( 'wp-personal-account/' . $name . '.php' ) );
		if ( $theme ) {
			return $theme;
		}

		return WPPA_PATH . 'templates/' . $name . '.php';
	}

	/**
	 * Рендер шаблона.
	 *
	 * @param string $name Имя шаблона.
	 * @param array  $args Данные для шаблона.
	 * @return void
	 */
	public static function render( $name, $args = array() ) {
		$file = self::locate( $name );

		if ( ! file_exists( $file ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- контролируемый набор переменных для шаблонов.
		extract( $args, EXTR_SKIP );
		include $file;
	}

	/**
	 * Рендер шаблона в строку.
	 *
	 * @param string $name Имя шаблона.
	 * @param array  $args Данные.
	 * @return string
	 */
	public static function get( $name, $args = array() ) {
		ob_start();
		self::render( $name, $args );
		return (string) ob_get_clean();
	}
}
