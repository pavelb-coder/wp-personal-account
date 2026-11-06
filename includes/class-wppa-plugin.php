<?php
/**
 * Ядро плагина: хуки, ассеты, роутинг запросов.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Plugin
 */
class WPPA_Plugin {

	/**
	 * Query-параметры плагина (регистрируются, чтобы WordPress их не выбрасывал).
	 */
	const QUERY_VARS = array( 'wppa_view', 'wppa_tab', 'wppa_notice', 'wppa_team_join', 'wppa_action', 'wppa_team_action', 'wppa_key', 'wppa_login' );

	/**
	 * Минимальная длина пароля.
	 */
	const MIN_PASSWORD_LENGTH = 16;

	/**
	 * Экземпляр (singleton).
	 *
	 * @var WPPA_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Инициализация на plugins_loaded.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'instance' ) );
	}

	/**
	 * Получить экземпляр класса.
	 *
	 * @return WPPA_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Конструктор.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'template_redirect', array( $this, 'handle_requests' ), 5 );
		add_action( 'wp', array( __CLASS__, 'maybe_hide_admin_bar' ), 5 );
		add_action( 'init', array( 'WPPA_Export_Folder', 'reschedule' ), 20 );
		add_action( WPPA_Export_Folder::CRON_HOOK, array( 'WPPA_Export_Folder', 'run_cron' ) );

		// Выгрузка по изменению: регистрация, профиль, поля, команды.
		WPPA_Export_Folder::init();

		WPPA_Shortcodes::init();
		WPPA_Teams::init();
		WPPA_Curator_Files::init();
		WPPA_Participants::init();
		WPPA_Team_Files_Export::init();
		WPPA_Mailing::init();
		WPPA_Mailing_Files::init();
		WPPA_Builder::init();
		add_action( 'widgets_init', array( 'WPPA_Auth_Widget', 'register' ) );
		WPPA_Blocks::init();

		if ( is_admin() ) {
			WPPA_Admin::init();
			WPPA_Settings::init();
		}

		add_filter( 'plugin_action_links_' . plugin_basename( WPPA_FILE ), array( $this, 'action_links' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
	}

	/**
	 * Публичные query-параметры плагина.
	 *
	 * Нужны, чтобы WordPress не выбрасывал их каноническим редиректом
	 * (например, ?wppa_view=register на главной странице).
	 *
	 * @param array $vars Параметры.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		return array_merge( (array) $vars, self::QUERY_VARS );
	}

	/**
	 * Текущий запрошенный экран гостя (login|register) с учётом прав.
	 *
	 * @return string
	 */
	public static function requested_view() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только выбор отображаемой формы.
		$view = isset( $_GET['wppa_view'] ) ? sanitize_key( wp_unslash( $_GET['wppa_view'] ) ) : '';

		if ( 'register' === $view ) {
			return self::registration_open() ? 'register' : 'login';
		}

		return 'login' === $view ? 'login' : '';
	}

	/**
	 * Локализация.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-personal-account', false, dirname( plugin_basename( WPPA_FILE ) ) . '/languages' );
	}

	/**
	 * Ссылка «Настройки» в списке плагинов.
	 *
	 * @param array $links Ссылки.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=wppa-settings' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Настройки', 'wp-personal-account' ) . '</a>' );
		return $links;
	}

	/**
	 * Значения настроек по умолчанию.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'account_page_id'      => 0,
			'enabled_tabs'         => array( 'dashboard', 'profile', 'team', 'files', 'participants', 'mailing', 'security' ),
			'participant_min_percent' => 60,
			'registration_enabled' => 1,
			'hide_admin_bar'       => 1,
			'registration_start'   => '',
			'registration_end'     => '',
			'login_redirect'       => '',
			'accent_color'         => '#2563eb',
			'accent_hover_color'   => '',
			'accent_contrast_color' => '',
			'color_bg'             => '#f8fafc',
			'color_surface'        => '#ffffff',
			'color_text'           => '#0f172a',
			'color_muted'          => '#64748b',
			'color_border'         => '#e2e8f0',
			'color_surface_alt'    => '#f1f5f9',
			'color_success'        => '#047857',
			'color_warning'        => '#b45309',
			'color_danger'         => '#b91c1c',
			'color_info'           => '#0284c7',
			'radius'               => 14,
			'heading_text'         => __( 'Личный кабинет', 'wp-personal-account' ),
			'heading_show'         => 1,
			'heading_tag'          => 'h1',
			'heading_size'         => 22,
			'heading_weight'       => 700,
			'heading_case'         => 'none',
			'heading_align'        => 'left',
			'heading_gap'          => 18,
			'heading_color'        => '',
			'hide_page_title'      => 1,
			'custom_css'           => '',
			'max_team_members'     => 10,
			'min_team_members'     => 2,
			'require_mentor'       => 1,
			'fields'               => array(),
			'field_sections'       => array(),
			'password_rules'       => array(),
			'link_button'          => array(),
			'export_folder'        => array(),
			'file_export'          => 1,
			'smb'                  => array(),
			'delete_data_on_uninstall' => 0,
		);
	}

	/**
	 * Все настройки плагина.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( 'wppa_settings', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Одна настройка.
	 *
	 * @param string $key     Ключ.
	 * @param mixed  $default Запасное значение.
	 * @return mixed
	 */
	public static function setting( $key, $default = '' ) {
		$settings = self::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Минимальная длина пароля (по умолчанию 16).
	 *
	 * @return int
	 */
	public static function min_password_length() {
		/**
		 * Фильтр минимальной длины пароля.
		 *
		 * @param int $length Длина.
		 */
		return max( 1, (int) apply_filters( 'wppa_min_password_length', self::MIN_PASSWORD_LENGTH ) );
	}

	/**
	 * Слишком короткий пароль?
	 *
	 * @param string $password Пароль.
	 * @return bool
	 */
	public static function password_too_short( $password ) {
		return strlen( (string) $password ) < self::min_password_length();
	}

	/**
	 * Экран гостя с учётом параметра ?wppa_view.
	 *
	 * login | register | lostpassword | resetpass | '' (по умолчанию).
	 *
	 * @return string
	 */
	public static function guest_view() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только выбор формы.
		$view = isset( $_GET['wppa_view'] ) ? sanitize_key( wp_unslash( $_GET['wppa_view'] ) ) : '';

		if ( 'resetpass' === $view ) {
			return 'resetpass';
		}

		if ( 'lostpassword' === $view ) {
			return 'lostpassword';
		}

		return self::requested_view();
	}

	/**
	 * URL формы «Забыли пароль?».
	 *
	 * @return string
	 */
	public static function lost_password_url() {
		return self::account_url( array( 'wppa_view' => 'lostpassword' ) );
	}

	/**
	 * Открыта ли регистрация: включена в настройках плагина и в настройках WordPress.
	 *
	 * @return bool
	 */
	public static function registration_open() {
		return (bool) self::setting( 'registration_enabled', 1 ) && (bool) get_option( 'users_can_register' );
	}

	/**
	 * Открыт ли личный кабинет на текущем экране.
	 *
	 * Учитываются и страница из настроек, и шорткод/блок кабинета на любой другой странице.
	 *
	 * @return bool
	 */
	public static function is_account_view() {
		if ( self::is_account_screen() ) {
			return true;
		}

		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		if ( has_shortcode( (string) $post->post_content, 'wppa_account' ) ) {
			return true;
		}

		return function_exists( 'has_block' ) && has_block( 'wppa/auth', $post );
	}

	/**
	 * Скрыть панель WordPress в личном кабинете.
	 *
	 * Панель нужна администратору в админке, а в кабинете мешает: перекрывает шапку
	 * и подсказывает участнику лишние ссылки. Скрывается для всех, кто в кабинете.
	 *
	 * @return void
	 */
	public static function maybe_hide_admin_bar() {
		if ( is_admin() || ! is_user_logged_in() ) {
			return;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}

		if ( ! (bool) self::setting( 'hide_admin_bar', 1 ) ) {
			return;
		}

		if ( ! self::is_account_view() ) {
			return;
		}

		show_admin_bar( false );
	}

	/**
	 * Окно доступности регистрации: с какого и по какое время открыта форма.
	 *
	 * state: none (без ограничений) | before | open | after.
	 *
	 * @return array
	 */
	public static function registration_window() {
		$start    = WPPA_Time::to_timestamp( self::setting( 'registration_start', '' ) );
		$end      = WPPA_Time::to_timestamp( self::setting( 'registration_end', '' ) );
		$now      = time();
		$state    = 'open';
		$message  = '';
		$countdown = 0;
		$countdown_of = '';

		if ( $start && $now < $start ) {
			$state        = 'before';
			$countdown    = $start;
			$countdown_of = 'open';
			$message      = sprintf(
				/* translators: %s: дата и время открытия */
				__( 'Регистрация откроется %s.', 'wp-personal-account' ),
				WPPA_Time::format_date( $start )
			);
		} elseif ( $end && $now > $end ) {
			$state   = 'after';
			$message = sprintf(
				/* translators: %s: дата и время закрытия */
				__( 'Регистрация закрыта %s.', 'wp-personal-account' ),
				WPPA_Time::format_date( $end )
			);
		}

		return array(
			'state'        => $state,
			'start'        => (int) $start,
			'end'          => (int) $end,
			'message'      => $message,
			'countdown'    => (int) $countdown,
			'countdown_of' => $countdown_of,
			'limited'      => (bool) ( $start || $end ),
		);
	}

	/**
	 * Регистрация принимает заявки прямо сейчас: включена и открыта по времени.
	 *
	 * @return bool
	 */
	public static function registration_live() {
		if ( ! self::registration_open() ) {
			return false;
		}

		$window = self::registration_window();

		return 'open' === $window['state'];
	}

	/**
	 * Ссылка на форму регистрации.
	 *
	 * @return string
	 */
	public static function register_url() {
		return self::account_url( array( 'wppa_view' => 'register' ) );
	}

	/**
	 * Ссылка на форму входа.
	 *
	 * @return string
	 */
	public static function login_url() {
		return self::account_url( array( 'wppa_view' => 'login' ) );
	}

	/**
	 * ID страницы личного кабинета.
	 *
	 * @return int
	 */
	public static function account_page_id() {
		$page_id = (int) self::setting( 'account_page_id', 0 );

		if ( $page_id && 'page' === get_post_type( $page_id ) && 'publish' === get_post_status( $page_id ) ) {
			return $page_id;
		}
		return 0;
	}

	/**
	 * URL личного кабинета (с добавлением query-аргументов).
	 *
	 * @param array $args Дополнительные аргументы.
	 * @return string
	 */
	public static function account_url( $args = array() ) {
		$page_id = self::account_page_id();
		$url     = $page_id ? get_permalink( $page_id ) : home_url( '/' );

		if ( ! $url ) {
			$url = home_url( '/' );
		}

		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/**
	 * URL конкретной вкладки кабинета.
	 *
	 * @param string $tab  Вкладка.
	 * @param array  $args Аргументы.
	 * @return string
	 */
	public static function tab_url( $tab, $args = array() ) {
		$args = array_merge( array( 'wppa_tab' => $tab ), $args );
		return self::account_url( $args );
	}

	/**
	 * Является ли текущий запрос страницей личного кабинета.
	 *
	 * @return bool
	 */
	public static function is_account_screen() {
		$page_id = self::account_page_id();
		return ( $page_id && is_page( $page_id ) );
	}

	/**
	 * Нужно ли подключать ассеты на текущей странице.
	 *
	 * @return bool
	 */
	private function needs_assets() {
		if ( self::is_account_screen() ) {
			return true;
		}

		if ( function_exists( 'is_active_widget' ) && is_active_widget( false, false, 'wppa_auth' ) ) {
			return true;
		}

		$post = get_post();
		if ( $post instanceof WP_Post ) {
			if ( function_exists( 'has_block' ) && has_block( 'wppa/auth', $post ) ) {
				return true;
			}

			foreach ( array( 'wppa_account', 'wppa_login', 'wppa_register', 'wppa_auth' ) as $tag ) {
				if ( has_shortcode( $post->post_content, $tag ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Регистрация и подключение CSS/JS.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'wppa-account',
			WPPA_URL . 'assets/css/account.css',
			array(),
			WPPA_VERSION
		);

		wp_register_script(
			'wppa-account',
			WPPA_URL . 'assets/js/account.js',
			array(),
			WPPA_VERSION,
			true
		);

		if ( ! $this->needs_assets() ) {
			return;
		}

		// Цвета, скругления и свой CSS из настроек оформления.
		wp_enqueue_style( 'wppa-account' );
		wp_add_inline_style( 'wppa-account', WPPA_Theme::css() );

		wp_enqueue_script( 'wppa-account' );
		wp_localize_script(
			'wppa-account',
			'wppaL10n',
			array(
				'passwordMismatch' => __( 'Пароли не совпадают.', 'wp-personal-account' ),
				'confirmLogout'    => __( 'Выйти из аккаунта?', 'wp-personal-account' ),
				'toggleShow'       => __( 'Показать пароль', 'wp-personal-account' ),
				'toggleHide'       => __( 'Скрыть пароль', 'wp-personal-account' ),
				'copied'           => __( 'Скопировано', 'wp-personal-account' ),
				'copyFailed'       => __( 'Не удалось', 'wp-personal-account' ),
			)
		);
	}

	/**
	 * Обработка POST-форм и GET-подтверждений.
	 *
	 * @return void
	 */
	public function handle_requests() {
		if ( ! empty( $_GET['wppa_team_join'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- приглашение по токену.
			WPPA_Teams::handle_join( sanitize_text_field( wp_unslash( $_GET['wppa_team_join'] ) ) );
		}

		if ( ! empty( $_GET['wppa_confirm_email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- одноразовый токен подтверждения.
			WPPA_Profile::handle_email_confirm( sanitize_text_field( wp_unslash( $_GET['wppa_confirm_email'] ) ) );
		}

		if ( empty( $_POST['wppa_action'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['wppa_action'] ) );
		switch ( $action ) {
			case 'login':
				WPPA_Auth::handle_login();
				break;
			case 'register':
				WPPA_Auth::handle_register();
				break;
			case 'team':
				WPPA_Teams::handle_request();
				break;
			case 'curator_file':
				WPPA_Curator_Files::handle_request();
				break;
			case 'participant_threshold':
				WPPA_Participants::handle_threshold();
				break;
			case 'mailing':
				WPPA_Mailing::handle_request();
				break;
			case 'profile':
				WPPA_Profile::handle_update();
				break;
			case 'password':
				WPPA_Profile::handle_password();
				break;
			case 'email':
				WPPA_Profile::handle_email_request();
				break;
			case 'lostpassword':
				WPPA_Reset::handle_request();
				break;
			case 'resetpass':
				WPPA_Reset::handle_reset();
				break;
			default:
				break;
		}
	}

	/**
	 * Миграция настроек между версиями.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = (string) get_option( 'wppa_version', '' );

		if ( WPPA_VERSION === $installed ) {
			return;
		}

		$settings = self::get_settings();
		$defaults = self::defaults();

		// Новые вкладки, появившиеся в обновлении, включаем автоматически.
		$enabled = array_values( array_unique( array_merge( (array) $settings['enabled_tabs'], (array) $defaults['enabled_tabs'] ) ) );
		$allowed = array_keys( WPPA_Template::all_tabs() );
		$settings['enabled_tabs'] = array_values( array_intersect( $allowed, $enabled ) );

		// Новые числовые настройки.
		if ( empty( $settings['max_team_members'] ) ) {
			$settings['max_team_members'] = $defaults['max_team_members'];
		}

		if ( empty( $settings['min_team_members'] ) ) {
			$settings['min_team_members'] = $defaults['min_team_members'];
		}

		// Блок «Быстрые действия» удалён в 2.15.1 — вычищаем его из раскладок.
		WPPA_Builder::drop_block( 'quick-actions' );

		// Функции 3.37.0–3.40.0 откачены: блок «Участники и баллы» и его флаг
		// больше не нужны — чистим раскладку, чтобы вкладка «Файлы» не осталась пустой.
		if ( ! get_option( 'wppa_layout_cleaned' ) ) {
			WPPA_Builder::drop_unknown();
			delete_option( 'wppa_participants_seeded' );
			update_option( 'wppa_layout_cleaned', 1 );
		}

		// «Участники и баллы» — отдельная вкладка: блок переезжает с «Файлов» один раз.
		if ( ! get_option( 'wppa_participants_tab' ) ) {
			WPPA_Builder::drop_block( 'participants-widget' );
			WPPA_Builder::seed_block( 'participants-widget', 'participants' );
			delete_option( 'wppa_participants_block_seeded' );
			update_option( 'wppa_participants_tab', 1 );
		}

		// Кнопка со ссылкой: добавляем в «Обзор» один раз для существующих установок.
		if ( ! get_option( 'wppa_link_button_seeded' ) ) {
			WPPA_Builder::seed_block( 'link-button', 'dashboard' );
			update_option( 'wppa_link_button_seeded', 1 );
		}

		update_option( 'wppa_settings', $settings );
		update_option( 'wppa_version', WPPA_VERSION );
	}

	/**
	 * Создание страницы кабинета при активации.
	 *
	 * @return void
	 */
	public static function activate() {
		$settings = self::get_settings();

		update_option( 'wppa_settings', $settings );
		update_option( 'wppa_version', WPPA_VERSION );

		$page_id = (int) $settings['account_page_id'];
		if ( ! $page_id || ! get_post( $page_id ) ) {
			$existing = get_page_by_path( 'personal-account' );
			if ( $existing instanceof WP_Post ) {
				$page_id = (int) $existing->ID;
			} else {
				$page_id = wp_insert_post(
					array(
						'post_title'   => __( 'Личный кабинет', 'wp-personal-account' ),
						'post_name'    => 'personal-account',
						'post_content' => '[wppa_account]',
						'post_status'  => 'publish',
						'post_type'    => 'page',
					)
				);
			}

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$settings['account_page_id'] = (int) $page_id;
				update_option( 'wppa_settings', $settings );
			}
		}
	}

	/**
	 * Деактивация: только сбрасываем кэш, данные сохраняем.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Данные не удаляются — очистка выполняется в uninstall.php по настройке.
	}
}
