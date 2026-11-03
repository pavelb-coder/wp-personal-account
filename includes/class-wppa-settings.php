<?php
/**
 * Страница настроек в админке.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Settings
 */
class WPPA_Settings {

	/**
	 * Инициализация хуков.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Пункт меню.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_submenu_page(
			WPPA_Admin::SLUG,
			__( 'Настройки', 'wp-personal-account' ),
			__( 'Настройки', 'wp-personal-account' ),
			'manage_options',
			'wppa-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Регистрация опции.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'wppa_settings_group',
			'wppa_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => WPPA_Plugin::defaults(),
			)
		);
	}

	/**
	 * Очистка значений.
	 *
	 * @param array $input Входные данные.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = WPPA_Plugin::defaults();
		$out      = array();

		$out['account_page_id'] = isset( $input['account_page_id'] ) ? absint( $input['account_page_id'] ) : 0;

		$allowed_tabs           = array_keys( WPPA_Template::all_tabs() );
		$tabs                   = isset( $input['enabled_tabs'] ) && is_array( $input['enabled_tabs'] ) ? array_map( 'sanitize_key', $input['enabled_tabs'] ) : array();
		$out['enabled_tabs']    = array_values( array_intersect( $allowed_tabs, $tabs ) );

		$out['registration_enabled'] = empty( $input['registration_enabled'] ) ? 0 : 1;
		$out['delete_data_on_uninstall'] = empty( $input['delete_data_on_uninstall'] ) ? 0 : 1;

		$redirect               = isset( $input['login_redirect'] ) ? esc_url_raw( trim( (string) $input['login_redirect'] ) ) : '';
		$out['login_redirect']  = $redirect;

		$accent                = isset( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : '';
		$out['accent_color']   = $accent ? $accent : $defaults['accent_color'];

		$out['custom_css']     = isset( $input['custom_css'] ) ? wp_strip_all_tags( (string) $input['custom_css'] ) : '';

		return wp_parse_args( $out, $defaults );
	}

	/**
	 * Вывод страницы настроек.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s     = WPPA_Plugin::get_settings();
		$pages = get_pages( array( 'sort_column' => 'post_title' ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Личный кабинет', 'wp-personal-account' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Шорткоды: [wppa_account] — кабинет, [wppa_login] — форма входа, [wppa_register] — форма регистрации.', 'wp-personal-account' ); ?>
			</p>

			<form action="options.php" method="post">
				<?php settings_fields( 'wppa_settings_group' ); ?>

				<h2><?php esc_html_e( 'Основное', 'wp-personal-account' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wppa-page"><?php esc_html_e( 'Страница кабинета', 'wp-personal-account' ); ?></label></th>
						<td>
							<select name="wppa_settings[account_page_id]" id="wppa-page">
								<option value="0"><?php esc_html_e( '— не выбрана —', 'wp-personal-account' ); ?></option>
								<?php foreach ( $pages as $page ) : ?>
									<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( (int) $s['account_page_id'], (int) $page->ID ); ?>>
										<?php echo esc_html( $page->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Страница должна содержать шорткод [wppa_account].', 'wp-personal-account' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Регистрация', 'wp-personal-account' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wppa_settings[registration_enabled]" value="1" <?php checked( 1, (int) $s['registration_enabled'] ); ?> />
								<?php esc_html_e( 'Разрешить регистрацию через форму плагина', 'wp-personal-account' ); ?>
							</label>
							<p class="description">
								<?php
								printf(
									/* translators: %s: ссылка на настройки */
									esc_html__( 'В WordPress регистрация сейчас: %s', 'wp-personal-account' ),
									esc_html( get_option( 'users_can_register' ) ? __( 'включена', 'wp-personal-account' ) : __( 'выключена', 'wp-personal-account' ) )
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppa-redirect"><?php esc_html_e( 'Редирект после входа', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="wppa-redirect" name="wppa_settings[login_redirect]" value="<?php echo esc_attr( $s['login_redirect'] ); ?>" placeholder="<?php echo esc_attr( WPPA_Plugin::account_url() ); ?>" />
							<p class="description"><?php esc_html_e( 'Пусто — остаться на текущей странице.', 'wp-personal-account' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Вкладки кабинета', 'wp-personal-account' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Отображать', 'wp-personal-account' ); ?></th>
						<td>
							<?php foreach ( WPPA_Template::all_tabs() as $slug => $label ) : ?>
								<label style="display:block;margin-bottom:6px;">
									<input type="checkbox" name="wppa_settings[enabled_tabs][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $s['enabled_tabs'], true ) ); ?> />
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Оформление', 'wp-personal-account' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wppa-accent"><?php esc_html_e( 'Акцентный цвет', 'wp-personal-account' ); ?></label></th>
						<td><input type="color" id="wppa-accent" name="wppa_settings[accent_color]" value="<?php echo esc_attr( $s['accent_color'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wppa-css"><?php esc_html_e( 'Дополнительный CSS', 'wp-personal-account' ); ?></label></th>
						<td>
							<textarea class="large-text code" rows="8" id="wppa-css" name="wppa_settings[custom_css]"><?php echo esc_textarea( $s['custom_css'] ); ?></textarea>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Данные', 'wp-personal-account' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Удаление', 'wp-personal-account' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wppa_settings[delete_data_on_uninstall]" value="1" <?php checked( 1, (int) $s['delete_data_on_uninstall'] ); ?> />
								<?php esc_html_e( 'Удалять настройки плагина при деинсталляции', 'wp-personal-account' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
