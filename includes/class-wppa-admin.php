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
			__( 'Конструктор', 'wp-personal-account' ),
			__( 'Конструктор', 'wp-personal-account' ),
			self::CAP,
			'wppa-builder',
			array( 'WPPA_Builder', 'page' )
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
				'mentors_count' => WPPA_Teams::mentors_count( (int) $team->ID ),
				'people'  => count( WPPA_Teams::members( (int) $team->ID ) ),
				'limit'   => WPPA_Teams::team_limit( (int) $team->ID ),
				'own_limit' => WPPA_Teams::raw_team_limit( (int) $team->ID ),
				'mentors'   => WPPA_Teams::mentors( (int) $team->ID ),
				'members_list' => WPPA_Teams::members( (int) $team->ID ),
				'min'       => WPPA_Teams::team_min( (int) $team->ID ),
				'own_min'   => WPPA_Teams::raw_team_min( (int) $team->ID ),
				'missing'   => WPPA_Teams::missing_members( (int) $team->ID ),
				'gaps'      => WPPA_Teams::gaps( (int) $team->ID ),
				'complete'  => WPPA_Teams::is_complete( (int) $team->ID ),
				'stage2'    => WPPA_Teams::is_stage2( (int) $team->ID ),
				'created' => (int) get_post_meta( (int) $team->ID, WPPA_Teams::META_CREATED, true ),
			);
		}

		self::render(
			'teams',
			array(
				'rows'         => $rows,
				'max_members'  => WPPA_Teams::max_members(),
				'min_members'  => WPPA_Teams::min_members(),
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
				'notice'        => self::tool_notice(),
				'status'        => self::system_status(),
				'export_folder' => WPPA_Export_Folder::config(),
				'export_log'    => WPPA_Export_Folder::logs(),
				'export_next'   => WPPA_Export_Folder::next_run(),
				'folder_status' => 'smb' === WPPA_Export_Folder::config()['mode']
					? WPPA_SMB::test( WPPA_SMB::config() )
					: WPPA_Export_Folder::check_folder( WPPA_Export_Folder::config()['path'] ),
				'export_pending' => WPPA_Export_Folder::pending(),
				'export_change'  => WPPA_Export_Folder::next_change(),
				'smb_config'    => WPPA_SMB::config(),
				'smb_test'      => WPPA_SMB::test( WPPA_SMB::config() ),
				'export_check'  => '' === WPPA_Export_Folder::normalize_path( WPPA_Export_Folder::config()['path'] )
					? null
					: WPPA_Export_Folder::check_folder( WPPA_Export_Folder::config()['path'] ),
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
	public static function tool_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только код для вывода.
		$code = isset( $_GET['wppa_done'] ) ? sanitize_key( wp_unslash( $_GET['wppa_done'] ) ) : '';

		$map = array(
			'page_created'  => array( 'success', __( 'Страница личного кабинета создана и выбрана в настройках.', 'wp-personal-account' ) ),
			'limits_clear'  => array( 'success', __( 'Счётчики попыток входа сброшены.', 'wp-personal-account' ) ),
			'emails_clear'  => array( 'success', __( 'Ожидающие подтверждения email очищены.', 'wp-personal-account' ) ),
			'cache_clear'   => array( 'success', __( 'Кэш статистики сброшен.', 'wp-personal-account' ) ),
			'team_dissolved' => array( 'success', __( 'Команда распущена.', 'wp-personal-account' ) ),
			'layout_reset'   => array( 'success', __( 'Раскладка кабинета сброшена к стандартной.', 'wp-personal-account' ) ),
			'team_limit_saved' => array( 'success', __( 'Лимит участников команды обновлён.', 'wp-personal-account' ) ),
			'team_min_saved'   => array( 'success', __( 'Минимум участников команды обновлён.', 'wp-personal-account' ) ),
			'team_settings_saved' => array( 'success', __( 'Минимум и лимит участников команды обновлены.', 'wp-personal-account' ) ),
			'team_mentor_added'   => array( 'success', __( 'Наставник назначен.', 'wp-personal-account' ) ),
			'team_mentor_removed' => array( 'success', __( 'Роль наставника снята.', 'wp-personal-account' ) ),
			'team_mentor_invalid' => array( 'warning', __( 'Не удалось изменить наставника: команда или участник не найдены.', 'wp-personal-account' ) ),
			'team_mentor_not_member' => array( 'warning', __( 'Этот пользователь не состоит в выбранной команде.', 'wp-personal-account' ) ),
			'team_mentor_captain'    => array( 'warning', __( 'Капитан не может быть наставником: он уже ведёт команду.', 'wp-personal-account' ) ),
			'team_mentor_already'    => array( 'info', __( 'Этот участник уже наставник.', 'wp-personal-account' ) ),
			'team_mentor_not_mentor' => array( 'info', __( 'У этого участника и так нет роли наставника.', 'wp-personal-account' ) ),
			'tool_expired'           => array( 'warning', __( 'Страница устарела: пока она была открыта, истёк срок действия формы. Обновите страницу и повторите действие.', 'wp-personal-account' ) ),
			'tool_forbidden'         => array( 'error', __( 'Недостаточно прав для этого действия.', 'wp-personal-account' ) ),
			'tool_failed'            => array( 'error', __( 'Действие не выполнено: на сайте произошла ошибка. Подробности записаны в журнал ошибок PHP.', 'wp-personal-account' ) ),
			'folder_export_ok'   => array( 'success', __( 'Файл выгрузки записан в папку. Подробности — в журнале ниже.', 'wp-personal-account' ) ),
			'folder_export_fail' => array( 'error', __( 'Выгрузка не удалась: папка недоступна или нет прав на запись. Подробности — в журнале ниже.', 'wp-personal-account' ) ),
			'folder_check_ok'    => array( 'success', __( 'Папка доступна на запись.', 'wp-personal-account' ) ),
			'folder_check_fail'  => array( 'error', __( 'Папка недоступна: проверьте путь, права и монтирование сетевой шары.', 'wp-personal-account' ) ),
			'folder_log_clear'   => array( 'success', __( 'Журнал выгрузок очищен.', 'wp-personal-account' ) ),
			'smb_check_ok'       => array( 'success', __( 'Подключение к сетевой папке с указанным логином есть.', 'wp-personal-account' ) ),
			'smb_check_fail'     => array( 'error', __( 'Подключиться к сетевой папке не удалось. Причина — в журнале выгрузок.', 'wp-personal-account' ) ),
			'folder_verify_ok'   => array( 'success', __( 'Подключение к сетевой папке есть, запись возможна.', 'wp-personal-account' ) ),
			'folder_verify_fail' => array( 'error', __( 'Подключиться к сетевой папке не удалось. Причина — в журнале внизу страницы.', 'wp-personal-account' ) ),
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
		// Вместо красного экрана WordPress — возврат назад с понятным уведомлением.
		if ( ! current_user_can( self::CAP ) ) {
			self::redirect_tool( 'tool_forbidden' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- значение проверяется ниже.
		$nonce = isset( $_POST['_wpnonce'] ) ? wp_unslash( $_POST['_wpnonce'] ) : '';

		// Массив вместо строки прилетает от ботов и старых форм: не роняем сайт.
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wppa_tool' ) ) {
			self::redirect_tool( 'tool_expired' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce проверен выше.
		$raw_tool = isset( $_POST['wppa_tool'] ) ? wp_unslash( $_POST['wppa_tool'] ) : '';
		$tool     = is_string( $raw_tool ) ? sanitize_key( $raw_tool ) : '';

		try {
			$code = self::run_tool( $tool );
		} catch ( Throwable $e ) {
			// Критическая ошибка на весь сайт из-за одного действия не нужна:
			// пишем подробности в журнал и возвращаем понятное уведомление.
			if ( function_exists( 'error_log' ) ) {
				error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					sprintf(
						'WPPA: действие «%1$s» не выполнено — %2$s в %3$s:%4$d',
						$tool,
						$e->getMessage(),
						$e->getFile(),
						$e->getLine()
					)
				);
			}

			self::redirect_tool( 'tool_failed' );
		}

		self::redirect_tool( $code );
	}

	/**
	 * Выполнение инструмента: возвращает код уведомления.
	 *
	 * Отдельный метод, чтобы ошибку внутри любого действия можно было поймать
	 * в handle_tool() и не отдавать посетителю экран критической ошибки.
	 *
	 * @param string $tool Ключ действия.
	 * @return string Код уведомления.
	 */
	private static function run_tool( $tool ) {
		switch ( $tool ) {
			case 'create_page':
				WPPA_Plugin::activate();
				return 'page_created';

			case 'clear_limits':
				self::clear_login_limits();
				return 'limits_clear';

			case 'clear_pending_emails':
				delete_metadata( 'user', 0, WPPA_Profile::META_PENDING_EMAIL, '', true );
				delete_metadata( 'user', 0, WPPA_Profile::META_EMAIL_TOKEN, '', true );
				delete_metadata( 'user', 0, WPPA_Profile::META_EMAIL_EXPIRES, '', true );
				return 'emails_clear';

			case 'export_csv':
				self::export_users_csv();
				return '';

			case 'export_folder_now':
				$result = WPPA_Export_Folder::write( WPPA_Export_Folder::config() );
				return $result['ok'] ? 'folder_export_ok' : 'folder_export_fail';

			case 'smb_check':
				$result = WPPA_SMB::test( WPPA_SMB::config() );
				WPPA_Export_Folder::log_entry( $result['ok'], '', 0, $result['message'] );
				return $result['ok'] ? 'smb_check_ok' : 'smb_check_fail';

			case 'folder_verify':
				$folder = WPPA_Export_Folder::config();
				$result = 'smb' === $folder['mode']
					? WPPA_SMB::test( WPPA_SMB::config() )
					: WPPA_Export_Folder::check_folder( $folder['path'] );
				WPPA_Export_Folder::log_entry( $result['ok'], '', 0, $result['message'] );
				return $result['ok'] ? 'folder_verify_ok' : 'folder_verify_fail';

			case 'export_folder_check':
				$folder = WPPA_Export_Folder::config();
				$result = WPPA_Export_Folder::check_folder( $folder['path'] );
				return $result['ok'] ? 'folder_check_ok' : 'folder_check_fail';

			case 'export_folder_clear_log':
				delete_option( WPPA_Export_Folder::LOG_OPTION );
				return 'folder_log_clear';

			case 'update_team_limit':
				$team_id = isset( $_POST['team_id'] ) ? absint( wp_unslash( $_POST['team_id'] ) ) : 0;
				$limit   = isset( $_POST['team_limit'] ) ? absint( wp_unslash( $_POST['team_limit'] ) ) : 0;

				if ( $team_id && WPPA_Teams::CPT === get_post_type( $team_id ) ) {
					WPPA_Teams::set_team_limit( $team_id, $limit );
				}

				return 'team_limit_saved';

			case 'update_team_settings':
				$team_id = isset( $_POST['team_id'] ) ? absint( wp_unslash( $_POST['team_id'] ) ) : 0;
				$min     = isset( $_POST['team_min'] ) ? absint( wp_unslash( $_POST['team_min'] ) ) : 0;
				$limit   = isset( $_POST['team_limit'] ) ? absint( wp_unslash( $_POST['team_limit'] ) ) : 0;

				if ( $team_id && WPPA_Teams::CPT === get_post_type( $team_id ) ) {
					// Сначала лимит: минимум ограничивается им.
					WPPA_Teams::set_team_limit( $team_id, $limit );
					WPPA_Teams::set_team_min( $team_id, $min );
				}

				return 'team_settings_saved';

			case 'update_team_min':
				$team_id = isset( $_POST['team_id'] ) ? absint( wp_unslash( $_POST['team_id'] ) ) : 0;
				$min     = isset( $_POST['team_min'] ) ? absint( wp_unslash( $_POST['team_min'] ) ) : 0;

				if ( $team_id && WPPA_Teams::CPT === get_post_type( $team_id ) ) {
					WPPA_Teams::set_team_min( $team_id, $min );
				}

				return 'team_min_saved';

			case 'set_team_mentor':
			case 'remove_team_mentor':
				$team_id   = isset( $_POST['team_id'] ) ? absint( wp_unslash( $_POST['team_id'] ) ) : 0;
				$member_id = isset( $_POST['member_id'] ) ? absint( wp_unslash( $_POST['member_id'] ) ) : 0;

				return self::mentor_action( $tool, $team_id, $member_id );

			case 'set_curator':
			case 'remove_curator':
				$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;

				return self::curator_action( $tool, $user_id );

			case 'dissolve_team':
				$team_id = isset( $_POST['team_id'] ) ? absint( wp_unslash( $_POST['team_id'] ) ) : 0;

				if ( $team_id && WPPA_Teams::CPT === get_post_type( $team_id ) ) {
					WPPA_Teams::dissolve( $team_id );
				}

				return 'team_dissolved';

			case 'reset_layout':
				WPPA_Builder::reset();
				return 'layout_reset';

			case 'clear_cache':
				delete_transient( 'wppa_admin_stats' );
				return 'cache_clear';

			default:
				return '';
		}
	}

	/**
	 * Назначение и снятие наставника: что именно произошло.
	 *
	 * Возвращает код уведомления, ничего не выводит и не перенаправляет —
	 * так его можно проверить без HTTP.
	 *
	 * @param string $tool      set_team_mentor|remove_team_mentor.
	 * @param int    $team_id   ID команды.
	 * @param int    $member_id ID пользователя.
	 * @return string Код уведомления.
	 */
	public static function mentor_action( $tool, $team_id, $member_id ) {
		$team_id   = (int) $team_id;
		$member_id = (int) $member_id;

		if ( ! $team_id || ! $member_id || WPPA_Teams::CPT !== get_post_type( $team_id ) ) {
			return 'team_mentor_invalid';
		}

		if ( (int) WPPA_Teams::team_id( $member_id ) !== $team_id ) {
			return 'team_mentor_not_member';
		}

		if ( 'owner' === WPPA_Teams::role( $member_id ) ) {
			return 'team_mentor_captain';
		}

		if ( 'set_team_mentor' === $tool ) {
			if ( 'mentor' === WPPA_Teams::role( $member_id ) ) {
				return 'team_mentor_already';
			}

			return WPPA_Teams::make_mentor( $team_id, $member_id ) ? 'team_mentor_added' : 'team_mentor_invalid';
		}

		if ( 'remove_team_mentor' !== $tool ) {
			return 'team_mentor_invalid';
		}

		if ( 'mentor' !== WPPA_Teams::role( $member_id ) ) {
			return 'team_mentor_not_mentor';
		}

		return WPPA_Teams::dismiss_mentor( $team_id, $member_id ) ? 'team_mentor_removed' : 'team_mentor_invalid';
	}

	/**
	 * Назначение и снятие куратора.
	 *
	 * Возвращает код уведомления, ничего не выводит и не перенаправляет —
	 * так его можно проверить без HTTP.
	 *
	 * @param string $tool    set_curator|remove_curator.
	 * @param int    $user_id ID пользователя.
	 * @return string Код уведомления.
	 */
	public static function curator_action( $tool, $user_id ) {
		$user_id = (int) $user_id;

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return 'curator_invalid';
		}

		$is_curator = WPPA_Teams::is_curator( $user_id );

		if ( 'set_curator' === $tool ) {
			if ( $is_curator ) {
				return 'curator_already';
			}

			return WPPA_Teams::set_curator( $user_id, true ) ? 'curator_added' : 'curator_invalid';
		}

		if ( 'remove_curator' !== $tool ) {
			return 'curator_invalid';
		}

		if ( ! $is_curator ) {
			return 'curator_not';
		}

		return WPPA_Teams::set_curator( $user_id, false ) ? 'curator_removed' : 'curator_invalid';
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
		$args = array(
			'groups'    => self::export_groups_from_request(),
			'scope'     => isset( $_POST['export_scope'] ) ? sanitize_key( wp_unslash( $_POST['export_scope'] ) ) : 'all',
			'delimiter' => isset( $_POST['export_delimiter'] ) && ',' === $_POST['export_delimiter'] ? ',' : ';',
			'limit'     => isset( $_POST['export_limit'] ) ? absint( wp_unslash( $_POST['export_limit'] ) ) : 5000,
		);

		WPPA_Export::send( $args );
		exit;
	}

	/**
	 * Группы колонок из запроса.
	 *
	 * @return array
	 */
	private static function export_groups_from_request() {
		$posted = isset( $_POST['export_groups'] ) ? (array) wp_unslash( $_POST['export_groups'] ) : array();
		$posted = array_map( 'sanitize_key', $posted );
		$groups = array_values( array_intersect( array_keys( WPPA_Export::groups() ), $posted ) );

		// Учётную запись выгружаем всегда: без логина и email строки бессмысленны.
		if ( ! in_array( 'account', $groups, true ) ) {
			array_unshift( $groups, 'account' );
		}

		return $groups;
	}
}
