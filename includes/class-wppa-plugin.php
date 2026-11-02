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
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'template_redirect', array( $this, 'handle_requests' ), 5 );

		WPPA_Shortcodes::init();
		WPPA_Settings::init();

		add_filter( 'plugin_action_links_' . plugin_basename( WPPA_FILE ), array( $this, 'action_links' ) );
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
			'enabled_tabs'         => array( 'dashboard', 'profile', 'orders', 'addresses', 'security' ),
			'registration_enabled' => 1,
			'login_redirect'       => '',
			'accent_color'         => '#2563eb',
			'custom_css'           => '',
			'orders_per_page'      => 10,
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

		$post = get_post();
		if ( $post instanceof WP_Post ) {
			foreach ( array( 'wppa_account', 'wppa_login', 'wppa_register' ) as $tag ) {
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

		$accent = self::setting( 'accent_color', '#2563eb' );
		$accent = sanitize_hex_color( $accent );
		$accent = $accent ? $accent : '#2563eb';

		$inline = ':root{--wppa-accent:' . $accent . ';--wppa-accent-contrast:#ffffff;}';
		$custom = (string) self::setting( 'custom_css', '' );
		if ( '' !== trim( $custom ) ) {
			$inline .= "\n" . wp_strip_all_tags( $custom );
		}

		wp_enqueue_style( 'wppa-account' );
		wp_add_inline_style( 'wppa-account', $inline );

		wp_enqueue_script( 'wppa-account' );
		wp_localize_script(
			'wppa-account',
			'wppaL10n',
			array(
				'passwordMismatch' => __( 'Пароли не совпадают.', 'wp-personal-account' ),
				'confirmLogout'    => __( 'Выйти из аккаунта?', 'wp-personal-account' ),
				'toggleShow'       => __( 'Показать пароль', 'wp-personal-account' ),
				'toggleHide'       => __( 'Скрыть пароль', 'wp-personal-account' ),
			)
		);
	}

	/**
	 * Обработка POST-форм и GET-подтверждений.
	 *
	 * @return void
	 */
	public function handle_requests() {
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
			case 'profile':
				WPPA_Profile::handle_update();
				break;
			case 'password':
				WPPA_Profile::handle_password();
				break;
			case 'email':
				WPPA_Profile::handle_email_request();
				break;
			case 'addresses':
				WPPA_Addresses::handle_save();
				break;
			default:
				break;
		}
	}

	/**
	 * Создание страницы кабинета при активации.
	 *
	 * @return void
	 */
	public static function activate() {
		$settings = self::get_settings();
		update_option( 'wppa_settings', $settings );

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
