<?php
/**
 * Панель управления плагином в админке: обзор, аккаунты, инструменты.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Admin
 */
class WPPA_Admin {

	/**
	 * Минимальная роль для доступа к панели.
	 */
	const CAP = 'manage_options';

	/**
	 * Слаг главной страницы панели.
	 */
	const SLUG = 'wppa-dashboard';

	/**
	 * Хуки.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_wppa_tool', array( __CLASS__, 'handle_tool' ) );
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ) );
	}

	/**
	 * Регистрация меню.
	 *
	 * @return void
	 */
	public static function menu() {
		add_menu_page(
			__( 'Личный кабинет', 'wp-personal-account' ),
			__( 'Личный кабинет', 'wp-personal-account' ),
			self::CAP,
			self::SLUG,
			array( __CLASS__, 'page_dashboard' ),
			'dashicons-admin-users',
			58
		);

		add_submenu_page(
			self::SLUG,
			__( 'Обзор', 'wp-personal-account' ),
			__( 'Обзор', 'wp-personal-account' ),
			self::CAP,
			self::SLUG,
			array( __CLASS__, 'page_dashboard' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Аккаунты', 'wp-personal-account' ),
			__( 'Аккаунты', 'wp-personal-account' ),
			self::CAP,
			'wppa-accounts',
			array( __CLASS__, 'page_accounts' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Команды', 'wp-personal-account' ),
			__( 'Команды', 'wp-personal-account' ),
			self::CAP,
			'wppa-teams',
			array( __CLASS__, 'page_teams' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Инструменты', 'wp-personal-account' ),
			__( 'Инструменты', 'wp-personal-account' ),
			self::CAP,
			'wppa-tools',
			array( __CLASS__, 'page_tools' )
		);
	}

	/**
	 * Стили панели (только на страницах плагина).
	 *
	 * @param string $hook Текущий хук.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'wppa' ) && false === strpos( (string) $hook, 'wppa-settings' ) ) {
			return;
		}

		wp_enqueue_style(
			'wppa-admin',
			WPPA_URL . 'assets/css/admin.css',
			array(),
			WPPA_VERSION
		);
	}

	/**
	 * Текст в подвале на страницах плагина.
	 *
	 * @param string $text Текст.
	 * @return string
	 */
	public static function footer_text( $text ) {
		$screen = get_current_screen();
		if ( $screen && false !== strpos( (string) $screen->id, 'wppa' ) ) {
			return sprintf(
				/* translators: %s: версия плагина */
				esc_html__( 'WP Personal Account %s', 'wp-personal-account' ),
				esc_html( WPPA_VERSION )
			);
		}
		return $text;
	}

	/**
	 * Рендер админ-шаблона из templates/admin/.
	 *
	 * @param string $name Имя файла без расширения.
	 * @param array  $args Данные.
	 * @return void
	 */
	public static function render( $name, $args = array() ) {
		$name = preg_replace( '/[^a-z0-9\-]/i', '', $name );
		$file = locate_template( array( 'wp-personal-account/admin/' . $name . '.php' ) );

		if ( ! $file ) {
			$file = WPPA_PATH . 'templates/admin/' . $name . '.php';
		}

		if ( ! file_exists( $file ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- контролируемый набор переменных.
		extract( $args, EXTR_SKIP );
		include $file;
	}

	/* ---------------------------------------------------------------------
	 * Страницы
	 * ------------------------------------------------------------------ */

	/**
	 * Страница «Обзор».
	 *
	 * @return void
	 */
	public static function page_dashboard() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		self::render(
			'dashboard',
			array(
				'stats'    => self::stats(),
				'chart'    => self::registrations_chart( 14 ),
				'recent'   => get_users(
					array(
						'number'   => 8,
						'orderby'  => 'registered',
						'order'    => 'DESC',
						'fields'   => 'all',
					)
				),
				'status'   => self::system_status(),
				'notice'   => self::tool_notice(),
			)
		);
	}

	/**
	 * Страница «Аккаунты».
	 *
	 * @return void
	 */
	public static function page_accounts() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- просмотр списка.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$role   = isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : '';
		// phpcs:enable

		$per_page = 25;

		$args = array(
			'number'     => $per_page,
			'offset'     => ( $paged - 1 ) * $per_page,
			'orderby'    => 'registered',
			'order'      => 'DESC',
			'count_total' => true,
		);

		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		if ( '' !== $role ) {
			$args['role'] = $role;
		}

		$query = new WP_User_Query( $args );

		self::render(
			'accounts',
			array(
				'users'     => $query->get_results(),
				'total'     => (int) $query->get_total(),
				'paged'     => $paged,
				'per_page'  => $per_page,
				'search'    => $search,
				'role'      => $role,
				'roles'     => self::roles_list(),
				'notice'    => self::tool_notice(),
			)
		);
	}

	/**
	 * Страница «Команды».
	 *
	 * @return void
	 */
	public static function page_teams() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$teams = get_posts(
			array(
				'post_type'   => WPPA_Teams::CPT,
				'post_status' => 'publish',
				'numberposts' => 200,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$rows = array();

		foreach ( $teams as $team ) {
			$owner = get_userdata( (int) $team->post_author );

			$rows[] = array(
				'id'      => (int) $team->ID,
				'name'    => $team->post_title,
				'owner'   => $owner ? $owner->display_name : __( '— удалён —', 'wp-personal-account' ),
				'owner_id' => (int) $team->post_author,
				'members' => WPPA_Teams::members_count( (int) $team->ID ),
				'created' => (int) get_post_meta( (int) $team->ID, WPPA_Teams::META_CREATED, true ),
			);
		}

		self::render(
			'teams',
			array(
				'rows'         => $rows,
				'max_members'  => WPPA_Teams::max_members(),
				'total_users'  => count_users(),
				'notice'       => self::tool_notice(),
			)
		);
	}

	/**
	 * Страница «Инструменты».
	 *
	 * @return void
	 */
	public static function page_tools() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		self::render(
			'tools',
			array(
				'notice' => self::tool_notice(),
				'status' => self::system_status(),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Данные
	 * ------------------------------------------------------------------ */

	/**
	 * Список ролей.
	 *
	 * @return array
	 */
	public static function roles_list() {
		$roles = array();

		foreach ( wp_roles()->roles as $slug => $data ) {
			$roles[ $slug ] = translate_user_role( $data['name'] );
		}

		return $roles;
	}

	/**
	 * Ключевые метрики.
	 *
	 * @return array
	 */
	public static function stats() {
		$counts      = count_users();
		$total_users = isset( $counts['total_users'] ) ? (int) $counts['total_users'] : 0;
		$avail       = isset( $counts['avail_roles'] ) ? (array) $counts['avail_roles'] : array();

		$since30 = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );

		$new_query = new WP_User_Query(
			array(
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => true,
				'date_query'  => array(
					array(
						'after'     => $since30,
						'inclusive' => true,
						'column'    => 'user_registered',
					),
				),
			)
		);

		$pending_query = new WP_User_Query(
			array(
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => true,
				'meta_query'  => array(
					array(
						'key'     => WPPA_Profile::META_PENDING_EMAIL,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		return array(
			'total_users'   => $total_users,
			'new_30'        => (int) $new_query->get_total(),
			'subscribers'   => isset( $avail['subscriber'] ) ? (int) $avail['subscriber'] : 0,
			'roles'         => count( $avail ),
			'pending_email' => (int) $pending_query->get_total(),
			'teams'         => self::teams_count(),
			'team_members'  => self::team_members_count(),
		);
	}

	/**
	 * Количество команд.
	 *
	 * @return int
	 */
	public static function teams_count() {
		$query = new WP_Query(
			array(
				'post_type'      => WPPA_Teams::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Количество пользователей в командах.
	 *
	 * @return int
	 */
	public static function team_members_count() {
		$query = new WP_User_Query(
			array(
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => true,
				'meta_query'  => array(
					array(
						'key'     => WPPA_Teams::META_TEAM,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		return (int) $query->get_total();
	}

	/**
	 * Регистрации по дням для графика.
	 *
	 * @param int $days Количество дней.
	 * @return array Массив array( 'date' => ..., 'label' => ..., 'count' => int ).
	 */
	public static function registrations_chart( $days = 14 ) {
		$days  = max( 1, min( 60, (int) $days ) );
		$chart = array();
		$max   = 0;

		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$start = strtotime( '-' . $i . ' days', strtotime( 'today' ) );
			$end   = $start + DAY_IN_SECONDS - 1;

			$query = new WP_User_Query(
				array(
					'fields'      => 'ID',
					'number'      => 1,
					'count_total' => true,
					'date_query'  => array(
						array(
							'after'     => gmdate( 'Y-m-d H:i:s', $start ),
							'before'    => gmdate( 'Y-m-d H:i:s', $end ),
							'inclusive' => true,
							'column'    => 'user_registered',
						),
					),
				)
			);

			$count = (int) $query->get_total();
			$max   = max( $max, $count );

			$chart[] = array(
				'date'  => gmdate( 'Y-m-d', $start ),
				'label' => date_i18n( 'j M', $start ),
				'count' => $count,
			);
		}

		return array(
			'items' => $chart,
			'max'   => max( 1, $max ),
			'total' => array_sum( wp_list_pluck( $chart, 'count' ) ),
		);
	}

	/**
	 * Состояние системы и интеграций.
	 *
	 * @return array
	 */
	public static function system_status() {
		$page_id  = WPPA_Plugin::account_page_id();
		$settings = WPPA_Plugin::get_settings();

		return array(
			'account_page' => $page_id
				? array(
					'ok'    => true,
					'label' => get_the_title( $page_id ),
					'link'  => get_permalink( $page_id ),
					'edit'  => get_edit_post_link( $page_id, 'raw' ),
				)
				: array(
					'ok'    => false,
					'label' => __( 'Не выбрана', 'wp-personal-account' ),
					'link'  => '',
					'edit'  => '',
				),
			'registration' => array(
				'ok'    => (bool) get_option( 'users_can_register' ) && (bool) $settings['registration_enabled'],
				'label' => ( get_option( 'users_can_register' ) && $settings['registration_enabled'] )
					? __( 'Открыта', 'wp-personal-account' )
					: __( 'Закрыта', 'wp-personal-account' ),
			),
			'tabs'         => array(
				'ok'    => ! empty( $settings['enabled_tabs'] ),
				'label' => sprintf(
					/* translators: %d: количество вкладок */
					_n( '%d вкладка', '%d вкладок', count( (array) $settings['enabled_tabs'] ), 'wp-personal-account' ),
					count( (array) $settings['enabled_tabs'] )
				),
			),
			'environment'  => array(
				'wp'   => get_bloginfo( 'version' ),
				'php'  => PHP_VERSION,
				'plugin' => WPPA_VERSION,
			),
		);
	}

	/**
	 * Уведомление о выполненном инструменте.
	 *
	 * @return array|null
	 */
	private static function tool_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только код для вывода.
		$code = isset( $_GET['wppa_done'] ) ? sanitize_key( wp_unslash( $_GET['wppa_done'] ) ) : '';

		$map = array(
			'page_created'  => array( 'success', __( 'Страница личного кабинета создана и выбрана в настройках.', 'wp-personal-account' ) ),
			'limits_clear'  => array( 'success', __( 'Счётчики попыток входа сброшены.', 'wp-personal-account' ) ),
			'emails_clear'  => array( 'success', __( 'Ожидающие подтверждения email очищены.', 'wp-personal-account' ) ),
			'cache_clear'   => array( 'success', __( 'Кэш статистики сброшен.', 'wp-personal-account' ) ),
			'team_dissolved' => array( 'success', __( 'Команда распущена.', 'wp-personal-account' ) ),
		);

		return isset( $map[ $code ] ) ? $map[ $code ] : null;
	}

	/* ---------------------------------------------------------------------
	 * Инструменты (admin-post)
	 * ------------------------------------------------------------------ */

	/**
	 * Обработчик инструментов обслуживания.
	 *
	 * @return void
	 */
	public static function handle_tool() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'wp-personal-account' ), 403 );
		}

		check_admin_referer( 'wppa_tool' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce проверен выше.
		$tool = isset( $_POST['wppa_tool'] ) ? sanitize_key( wp_unslash( $_POST['wppa_tool'] ) ) : '';

		switch ( $tool ) {
			case 'create_page':
				WPPA_Plugin::activate();
				self::redirect_tool( 'page_created' );
				break;

			case 'clear_limits':
				self::clear_login_limits();
				self::redirect_tool( 'limits_clear' );
				break;

			case 'clear_pending_emails':
				delete_metadata( 'user', 0, WPPA_Profile::META_PENDING_EMAIL, '', true );
				delete_metadata( 'user', 0, WPPA_Profile::META_EMAIL_TOKEN, '', true );
				delete_metadata( 'user', 0, WPPA_Profile::META_EMAIL_EXPIRES, '', true );
				self::redirect_tool( 'emails_clear' );
				break;

			case 'export_csv':
				self::export_users_csv();
				break;

			case 'dissolve_team':
				$team_id = isset( $_POST['team_id'] ) ? absint( wp_unslash( $_POST['team_id'] ) ) : 0;

				if ( $team_id && WPPA_Teams::CPT === get_post_type( $team_id ) ) {
					WPPA_Teams::dissolve( $team_id );
				}

				self::redirect_tool( 'team_dissolved' );
				break;

			case 'clear_cache':
				delete_transient( 'wppa_admin_stats' );
				self::redirect_tool( 'cache_clear' );
				break;

			default:
				self::redirect_tool( '' );
		}
	}

	/**
	 * Редирект назад на страницу-источник.
	 *
	 * @param string $code Код уведомления.
	 * @return void
	 */
	private static function redirect_tool( $code ) {
		$back = wp_get_referer();
		$back = $back ? $back : admin_url( 'admin.php?page=wppa-tools' );

		if ( '' !== $code ) {
			$back = add_query_arg( 'wppa_done', $code, $back );
		}

		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Сброс транзиентов лимита попыток входа.
	 *
	 * @return void
	 */
	private static function clear_login_limits() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_wppa_lp_%'
			   OR option_name LIKE '_transient_timeout_wppa_lp_%'"
		);
	}

	/**
	 * Экспорт пользователей в CSV.
	 *
	 * @return void
	 */
	private static function export_users_csv() {
		$filename = 'wppa-users-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );

		// BOM, чтобы Excel корректно открыл UTF-8.
		fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		fputcsv(
			$output,
			array(
				__( 'ID', 'wp-personal-account' ),
				__( 'Логин', 'wp-personal-account' ),
				__( 'Email', 'wp-personal-account' ),
				__( 'Имя', 'wp-personal-account' ),
				__( 'Роль', 'wp-personal-account' ),
				__( 'Регистрация', 'wp-personal-account' ),
				__( 'Команда', 'wp-personal-account' ),
			)
		);

		$users = get_users(
			array(
				'number'  => 5000,
				'orderby' => 'registered',
				'order'   => 'DESC',
			)
		);

		foreach ( $users as $user ) {
			fputcsv(
				$output,
				array(
					$user->ID,
					$user->user_login,
					$user->user_email,
					$user->display_name,
					implode( ', ', (array) $user->roles ),
					$user->user_registered,
					WPPA_Teams::team_id( $user->ID ) ? get_the_title( WPPA_Teams::team_id( $user->ID ) ) : '',
				)
			);
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

}
