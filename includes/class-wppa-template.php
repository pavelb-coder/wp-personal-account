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
			'dashboard'    => __( 'Обзор', 'wp-personal-account' ),
			'profile'      => __( 'Профиль', 'wp-personal-account' ),
			'team'         => __( 'Команда', 'wp-personal-account' ),
			'files'        => __( 'Файлы', 'wp-personal-account' ),
			'messages'     => __( 'Сообщения', 'wp-personal-account' ),
			'participants' => __( 'Участники', 'wp-personal-account' ),
			'certificates' => __( 'Сертификаты', 'wp-personal-account' ),
			'mailing'      => __( 'Рассылка', 'wp-personal-account' ),
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

		// Вкладка «Файлы» нужна только наставнику команды и куратору.
		if ( isset( $tabs['files'] ) && ! WPPA_Curator_Files::can_use_tab() ) {
			unset( $tabs['files'] );
		}

		// Личная переписка доступна только действующим наставникам и кураторам.
		if ( isset( $tabs['messages'] ) && ( ! is_user_logged_in() || ! WPPA_Chat::can_use( get_current_user_id() ) ) ) {
			unset( $tabs['messages'] );
		}

		// «Участники и баллы» и «Рассылка» — только куратору.
		foreach ( array( 'participants', 'mailing' ) as $wppa_tab_slug ) {
			if ( isset( $tabs[ $wppa_tab_slug ] ) && ! WPPA_Teams::is_curator() ) {
				unset( $tabs[ $wppa_tab_slug ] );
			}
		}

		// «Сертификаты» — только тем, у кого они есть: участнику свои, наставнику
		// ещё и сертификаты участников команды. Куратору вкладка нужна всегда —
		// он их выдаёт.
		if ( isset( $tabs['certificates'] ) ) {
			$wppa_cert_user = get_current_user_id();
			$wppa_cert_lib  = class_exists( 'WPPA_Certificates' );
			$wppa_cert_show = false;

			if ( $wppa_cert_lib ) {
				if ( WPPA_Certificates::available() && WPPA_Teams::is_curator( $wppa_cert_user ) ) {
					$wppa_cert_show = true;
				} elseif ( WPPA_Certificates::count( $wppa_cert_user ) > 0 ) {
					$wppa_cert_show = true;
				} elseif ( WPPA_Teams::is_mentor( $wppa_cert_user ) ) {
					// Наставнику вкладка нужна, только пока у команды есть сертификаты.
					foreach ( WPPA_Teams::mentor_team_ids( $wppa_cert_user ) as $wppa_mentor_team ) {
						if ( WPPA_Certificates::team_has( $wppa_mentor_team ) ) { $wppa_cert_show = true; break; }
					}
				}
			}

			if ( ! $wppa_cert_show ) {
				unset( $tabs['certificates'] );
			}
		}

		// Куратор работает с командами и файлами: «Обзор» и «Профиль» ему не нужны.
		$is_curator = WPPA_Teams::is_curator();

		if ( $is_curator ) {
			unset( $tabs['dashboard'], $tabs['profile'] );
		}

		if ( empty( $tabs ) ) {
			$tabs = $is_curator
				? array( 'team' => $all['team'] )
				: array( 'profile' => $all['profile'] );
		}

		/**
		 * Фильтр списка вкладок.
		 *
		 * @param array $tabs Вкладки slug => label.
		 */
		return apply_filters( 'wppa_tabs', $tabs );
	}

	/**
	 * Ссылка вкладки: у куратора «Сертификаты» ведёт в плагин WP Diplom.
	 *
	 * @param string $slug Вкладка.
	 * @return array url, external, target.
	 */
	public static function tab_link( $slug ) {
		$slug = (string) $slug;
		$link = array(
			'url'      => WPPA_Plugin::tab_url( $slug ),
			'external' => false,
			'target'   => '',
		);

		if ( 'messages' === $slug && is_user_logged_in() && WPPA_Teams::is_mentor( get_current_user_id() ) && ! WPPA_Teams::is_curator( get_current_user_id() ) ) {
			$link['url'] = WPPA_Chat::thread_url();
		}

		if ( 'certificates' === $slug && class_exists( 'WPPA_Certificates' ) ) {
			$admin = WPPA_Certificates::curator_admin_link();

			if ( '' !== $admin ) {
				$link = array(
					'url'      => $admin,
					'external' => true,
					'target'   => '_blank',
				);
			}
		}

		/**
		 * Фильтр ссылки вкладки кабинета.
		 *
		 * @param array  $link url, external, target.
		 * @param string $slug Вкладка.
		 */
		return (array) apply_filters( 'wppa_tab_link', $link, $slug );
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
