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
		$out['hide_admin_bar']       = empty( $input['hide_admin_bar'] ) ? 0 : 1;
		$out['registration_start']   = isset( $input['registration_start'] ) ? WPPA_Time::sanitize_datetime( $input['registration_start'] ) : '';
		$out['registration_end']     = isset( $input['registration_end'] ) ? WPPA_Time::sanitize_datetime( $input['registration_end'] ) : '';
		$out['delete_data_on_uninstall'] = empty( $input['delete_data_on_uninstall'] ) ? 0 : 1;

		$redirect               = isset( $input['login_redirect'] ) ? esc_url_raw( trim( (string) $input['login_redirect'] ) ) : '';
		$out['login_redirect']  = $redirect;

		$max_team              = isset( $input['max_team_members'] ) ? absint( $input['max_team_members'] ) : 10;
		$out['max_team_members'] = min( 100, max( 2, $max_team ) );

		$min_team              = isset( $input['min_team_members'] ) ? absint( $input['min_team_members'] ) : 2;
		$out['min_team_members'] = min( $out['max_team_members'], max( 1, $min_team ) );

		$accent                = isset( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : '';
		$out['accent_color']   = $accent ? $accent : $defaults['accent_color'];

		$out['custom_css']     = isset( $input['custom_css'] ) ? wp_strip_all_tags( (string) $input['custom_css'] ) : '';

		// Поля персональных данных: показывать / обязательно.
		$fields_saved = isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : array();
		$fields_out   = array();

		foreach ( array_keys( WPPA_Fields::all() ) as $field_id ) {
			$row      = isset( $fields_saved[ $field_id ] ) && is_array( $fields_saved[ $field_id ] ) ? $fields_saved[ $field_id ] : array();
			$enabled  = empty( $row['enabled'] ) ? 0 : 1;
			$required = $enabled && ! empty( $row['required'] ) ? 1 : 0;

			$fields_out[ $field_id ] = array(
				'enabled'  => $enabled,
				'required' => $required,
			);
		}

		$out['fields'] = $fields_out;

		$out['require_mentor'] = empty( $input['require_mentor'] ) ? 0 : 1;

		// Доступ к SMB.
		$smb_saved = isset( $input['smb'] ) && is_array( $input['smb'] ) ? $input['smb'] : array();

		// Из «smb://fileserver/share» берём только имя сервера.
		$smb_host = isset( $smb_saved['host'] ) ? trim( (string) $smb_saved['host'] ) : '';
		$smb_host = preg_replace( '#^(smb|smbclient)://#i', '', $smb_host );
		$smb_host = (string) strtok( $smb_host, '/\\' );

		$out['smb'] = array(
			'host'   => preg_replace( '#[^A-Za-z0-9._\-]+#', '', $smb_host ),
			'share'  => isset( $smb_saved['share'] ) ? preg_replace( '#[^A-Za-z0-9А-Яа-яЁё._\-]+#u', '', (string) $smb_saved['share'] ) : '',
			'dir'    => isset( $smb_saved['dir'] ) ? trim( (string) $smb_saved['dir'], "/\\ " ) : '',
			'user'   => isset( $smb_saved['user'] ) ? sanitize_text_field( $smb_saved['user'] ) : '',
			'pass'   => isset( $smb_saved['pass'] ) ? (string) $smb_saved['pass'] : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- пароль храним как есть.
			'domain' => isset( $smb_saved['domain'] ) ? sanitize_text_field( $smb_saved['domain'] ) : '',
			'port'   => isset( $smb_saved['port'] ) ? min( 65535, absint( $smb_saved['port'] ) ) : 445,
		);

		$out['smb']['dir'] = preg_replace( '#\.{2,}#', '', (string) $out['smb']['dir'] );

		// Выгрузка в сетевую папку.
		$folder_saved = isset( $input['export_folder'] ) && is_array( $input['export_folder'] ) ? $input['export_folder'] : array();
		$folder_path  = isset( $folder_saved['path'] ) ? sanitize_text_field( $folder_saved['path'] ) : '';
		$folder_path  = preg_replace( '/[^A-Za-z0-9А-Яа-яЁё._\-\/\\\\: ]+/u', '', (string) $folder_path );

		$folder_groups = isset( $folder_saved['groups'] ) && is_array( $folder_saved['groups'] ) ? array_map( 'sanitize_key', $folder_saved['groups'] ) : array();
		$folder_groups = array_values( array_intersect( array_keys( WPPA_Export::groups() ), $folder_groups ) );

		if ( ! in_array( 'account', $folder_groups, true ) ) {
			array_unshift( $folder_groups, 'account' );
		}

		// Шаблон имени: токены {date}, {time} сохраняем как есть.
		$folder_file = isset( $folder_saved['file'] ) ? sanitize_text_field( $folder_saved['file'] ) : 'wppa-accounts-{date}.csv';
		$folder_file = preg_replace( '/[^A-Za-z0-9А-Яа-яЁё._\-{}]+/u', '-', (string) $folder_file );
		$folder_file = '' === trim( $folder_file, '-' ) ? 'wppa-accounts-{date}.csv' : $folder_file;

		$folder_schedule = isset( $folder_saved['schedule'] ) ? sanitize_key( $folder_saved['schedule'] ) : 'daily';
		$folder_schedule = isset( WPPA_Export_Folder::schedules()[ $folder_schedule ] ) ? $folder_schedule : 'daily';
		$folder_scope     = isset( $folder_saved['scope'] ) ? sanitize_key( $folder_saved['scope'] ) : 'all';
		$folder_scope     = isset( WPPA_Export::scopes()[ $folder_scope ] ) ? $folder_scope : 'all';
		$folder_time     = WPPA_Export_Folder::sanitize_time( isset( $folder_saved['time'] ) ? $folder_saved['time'] : '03:00' );

		$out['export_folder'] = array(
			'enabled'   => empty( $folder_saved['enabled'] ) ? 0 : 1,
			'mode'      => isset( $folder_saved['mode'] ) && 'smb' === $folder_saved['mode'] ? 'smb' : 'path',
			'path'      => trim( $folder_path ),
			'file'      => $folder_file,
			'schedule'  => $folder_schedule,
			'time'      => $folder_time,
			'keep'      => isset( $folder_saved['keep'] ) ? min( 365, absint( $folder_saved['keep'] ) ) : 30,
			'groups'    => $folder_groups,
			'scope'     => $folder_scope,
			'delimiter' => isset( $folder_saved['delimiter'] ) && ',' === $folder_saved['delimiter'] ? ',' : ';',
			'limit'     => isset( $folder_saved['limit'] ) ? min( 20000, absint( $folder_saved['limit'] ) ) : 5000,
		);

		// Требования к паролю.
		$rules_saved = isset( $input['password_rules'] ) && is_array( $input['password_rules'] ) ? $input['password_rules'] : array();
		$rules_out   = array();

		foreach ( array_keys( WPPA_Password::rules() ) as $rule_id ) {
			$rules_out[ $rule_id ] = empty( $rules_saved[ $rule_id ] ) ? 0 : 1;
		}

		$out['password_rules'] = $rules_out;

		// Кнопка со ссылкой.
		$link_saved = isset( $input['link_button'] ) && is_array( $input['link_button'] ) ? $input['link_button'] : array();

		$out['link_button'] = array(
			'url'          => isset( $link_saved['url'] ) ? esc_url_raw( trim( (string) $link_saved['url'] ) ) : '',
			'text'         => isset( $link_saved['text'] ) ? sanitize_text_field( $link_saved['text'] ) : '',
			'start'        => isset( $link_saved['start'] ) ? WPPA_Link_Button::sanitize_datetime( $link_saved['start'] ) : '',
			'end'          => isset( $link_saved['end'] ) ? WPPA_Link_Button::sanitize_datetime( $link_saved['end'] ) : '',
			'new_tab'      => empty( $link_saved['new_tab'] ) ? 0 : 1,
			'require_team' => empty( $link_saved['require_team'] ) ? 0 : 1,
		);

		// Ссылка допускается только на http(s).
		if ( '' !== $out['link_button']['url'] && ! preg_match( '#^https?://#i', $out['link_button']['url'] ) ) {
			$out['link_button']['url'] = '';
		}

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
						<th scope="row"><?php esc_html_e( 'Приём заявок', 'wp-personal-account' ); ?></th>
						<td>
							<label for="wppa-reg-start"><?php esc_html_e( 'с', 'wp-personal-account' ); ?></label>
							<input type="datetime-local" id="wppa-reg-start" name="wppa_settings[registration_start]" value="<?php echo esc_attr( $s['registration_start'] ); ?>" />
							<label for="wppa-reg-end"><?php esc_html_e( 'по', 'wp-personal-account' ); ?></label>
							<input type="datetime-local" id="wppa-reg-end" name="wppa_settings[registration_end]" value="<?php echo esc_attr( $s['registration_end'] ); ?>" />
							<p class="description">
								<?php
								printf(
									/* translators: %s: часовой пояс сайта */
									esc_html__( 'Время по часовому поясу сайта (%s). Пустое поле — без ограничения. До начала и после окончания кнопка «Регистрация» неактивна и показывает отсчёт, а форма заменяется заглушкой; отправка формы на сервере также блокируется.', 'wp-personal-account' ),
									esc_html( function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC' )
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Панель WordPress', 'wp-personal-account' ); ?></th>
						<td>
							<p>
								<label>
									<input type="checkbox" name="wppa_settings[hide_admin_bar]" value="1" <?php checked( 1, (int) $s['hide_admin_bar'] ); ?> />
									<?php esc_html_e( 'Скрывать панель WordPress (админ-бар) в личном кабинете', 'wp-personal-account' ); ?>
								</label>
							</p>
							<p class="description"><?php esc_html_e( 'Скрывается только на страницах кабинета — в остальных разделах сайта панель остаётся. Доступ в админку это не ограничивает: если нужно запретить участникам вход в wp-admin, используйте отдельный плагин или роль.', 'wp-personal-account' ); ?></p>
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

				<h2><?php esc_html_e( 'Команды', 'wp-personal-account' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wppa-max-team"><?php esc_html_e( 'Максимум участников', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="number" min="2" max="100" id="wppa-max-team" name="wppa_settings[max_team_members]" value="<?php echo esc_attr( (int) $s['max_team_members'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Включая капитана. Сама вкладка «Команда» управляется в блоке «Вкладки кабинета».', 'wp-personal-account' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Наставник', 'wp-personal-account' ); ?></th>
						<td>
							<p>
								<label>
									<input type="checkbox" name="wppa_settings[require_mentor]" value="1" <?php checked( WPPA_Teams::require_mentor() ); ?> />
									<?php esc_html_e( 'Требовать наставника: команда без наставника считается несформированной', 'wp-personal-account' ); ?>
								</label>
							</p>
							<p class="description">
								<?php esc_html_e( 'Действует во всех статусах команды: отметка «недобор» в кабинете и в админке, а также условие «команда сформирована» у кнопки со ссылкой. Наставник по-прежнему не занимает место в лимите и не идёт в зачёт минимума участников.', 'wp-personal-account' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Требования к паролю', 'wp-personal-account' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Правила проверки пароля', 'wp-personal-account' ); ?></legend>
								<?php
								$wppa_enabled_rules = WPPA_Password::enabled_rules();
								$wppa_all_rules     = WPPA_Password::rules();
								?>
								<?php foreach ( $wppa_all_rules as $wppa_rule_id => $wppa_rule ) : ?>
									<p>
										<label>
											<input
												type="checkbox"
												name="wppa_settings[password_rules][<?php echo esc_attr( $wppa_rule_id ); ?>]"
												value="1"
												<?php checked( isset( $wppa_enabled_rules[ $wppa_rule_id ] ) ); ?>
											/>
											<?php echo esc_html( $wppa_rule['label'] ); ?>
										</label>
									</p>
								<?php endforeach; ?>
								<p class="description">
									<?php esc_html_e( 'Правила действуют при регистрации и при смене пароля: и на сервере, и в браузере (подсказки под полем). Минимальная длина пароля задаётся отдельно — константой MIN_PASSWORD_LENGTH и фильтром wppa_min_password_length.', 'wp-personal-account' ); ?>
								</p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Персональные данные', 'wp-personal-account' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Поля регистрации и профиля', 'wp-personal-account' ); ?></legend>
								<table class="widefat striped wppa-admin__fields">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Поле', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Показывать', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Обязательное', 'wp-personal-account' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( WPPA_Fields::schema() as $wppa_field ) : ?>
											<tr>
												<td><?php echo esc_html( $wppa_field['label'] ); ?></td>
												<td>
													<input
														type="checkbox"
														id="wppa-field-<?php echo esc_attr( $wppa_field['id'] ); ?>"
														name="wppa_settings[fields][<?php echo esc_attr( $wppa_field['id'] ); ?>][enabled]"
														value="1"
														<?php checked( $wppa_field['enabled'], 1 ); ?>
													/>
												</td>
												<td>
													<input
														type="checkbox"
														name="wppa_settings[fields][<?php echo esc_attr( $wppa_field['id'] ); ?>][required]"
														value="1"
														<?php checked( $wppa_field['required'], 1 ); ?>
													/>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
								<p class="description"><?php esc_html_e( 'Эти поля выводятся в форме регистрации и в профиле. Снятая галочка «Обязательное» делает поле необязательным; поле, исключённое из показа, скрывается везде и его значения не выводятся.', 'wp-personal-account' ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppa-min-team"><?php esc_html_e( 'Минимум участников', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="number" min="1" max="100" id="wppa-min-team" name="wppa_settings[min_team_members]" value="<?php echo esc_attr( (int) $s['min_team_members'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Сколько человек должно быть в команде, чтобы она считалась набранной. Не может быть больше максимума. Команды ниже минимума получают отметку «недобор» в кабинете и в админке.', 'wp-personal-account' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Кнопка со ссылкой', 'wp-personal-account' ); ?></h2>
				<?php $wppa_link = WPPA_Link_Button::config(); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wppa-link-url"><?php esc_html_e( 'Ссылка', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="url" class="regular-text code" id="wppa-link-url" name="wppa_settings[link_button][url]" value="<?php echo esc_attr( $wppa_link['url'] ); ?>" placeholder="https://" />
							<p class="description"><?php esc_html_e( 'Внешний адрес, только http или https. Пусто — блок в кабинете скрыт.', 'wp-personal-account' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppa-link-text"><?php esc_html_e( 'Текст кнопки', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="wppa-link-text" name="wppa_settings[link_button][text]" value="<?php echo esc_attr( $wppa_link['text'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Период активности', 'wp-personal-account' ); ?></th>
						<td>
							<label for="wppa-link-start"><?php esc_html_e( 'С', 'wp-personal-account' ); ?></label>
							<input type="datetime-local" id="wppa-link-start" name="wppa_settings[link_button][start]" value="<?php echo esc_attr( $wppa_link['start'] ); ?>" />
							<label for="wppa-link-end"><?php esc_html_e( 'по', 'wp-personal-account' ); ?></label>
							<input type="datetime-local" id="wppa-link-end" name="wppa_settings[link_button][end]" value="<?php echo esc_attr( $wppa_link['end'] ); ?>" />
							<p class="description">
								<?php
								printf(
									/* translators: %s: часовой пояс сайта */
									esc_html__( 'Время по часовому поясу сайта (%s). Пустое поле — без ограничения. До начала и после окончания кнопка видна, но неактивна: показывается отсчёт или сообщение о закрытии.', 'wp-personal-account' ),
									esc_html( function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC' )
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Условия', 'wp-personal-account' ); ?></th>
						<td>
							<p>
								<label>
									<input type="checkbox" name="wppa_settings[link_button][require_team]" value="1" <?php checked( $wppa_link['require_team'] ); ?> />
									<?php esc_html_e( 'Только когда команда сформирована — набран минимум участников', 'wp-personal-account' ); ?>
								</label>
							</p>
							<p>
								<label>
									<input type="checkbox" name="wppa_settings[link_button][new_tab]" value="1" <?php checked( $wppa_link['new_tab'] ); ?> />
									<?php esc_html_e( 'Открывать ссылку в новой вкладке', 'wp-personal-account' ); ?>
								</label>
							</p>
							<p class="description">
								<?php
								printf(
									/* translators: %d: текущий минимум участников */
									esc_html__( 'Минимум участников задаётся ниже и сейчас равен %d. Наставник в зачёт не идёт.', 'wp-personal-account' ),
									(int) WPPA_Teams::min_members()
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Экспорт в папку', 'wp-personal-account' ); ?></h2>
				<?php
				$wppa_folder = WPPA_Export_Folder::config();
				$wppa_smb    = WPPA_SMB::config();
				?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Автоматическая выгрузка', 'wp-personal-account' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wppa_settings[export_folder][enabled]" value="1" <?php checked( $wppa_folder['enabled'] ); ?> />
								<?php esc_html_e( 'Писать CSV в папку по расписанию', 'wp-personal-account' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Способ записи', 'wp-personal-account' ); ?></th>
						<td>
							<fieldset>
								<label style="margin-right:16px">
									<input type="radio" name="wppa_settings[export_folder][mode]" value="path" <?php checked( $wppa_folder['mode'], 'path' ); ?> />
									<?php esc_html_e( 'Папка на сервере (в том числе смонтированная шара)', 'wp-personal-account' ); ?>
								</label>
								<label>
									<input type="radio" name="wppa_settings[export_folder][mode]" value="smb" <?php checked( $wppa_folder['mode'], 'smb' ); ?> />
									<?php esc_html_e( 'SMB напрямую с логином и паролем', 'wp-personal-account' ); ?>
								</label>
							</fieldset>
							<p class="description">
								<?php
								printf(
									/* translators: 1: php-smbclient, 2: smbclient */
									esc_html__( 'Второй способ работает, если на сервере есть расширение %1$s или утилита %2$s. Иначе — только монтирование.', 'wp-personal-account' ),
									'<code>php-smbclient</code>',
									'<code>smbclient</code>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppa-folder-path"><?php esc_html_e( 'Путь к папке', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="text" class="large-text code" id="wppa-folder-path" name="wppa_settings[export_folder][path]" value="<?php echo esc_attr( $wppa_folder['path'] ); ?>" placeholder="/mnt/share/wppa" />
							<p class="description">
								<?php esc_html_e( 'Путь на сервере, доступный процессу PHP. Для сетевой шары SMB её нужно смонтировать заранее, например:', 'wp-personal-account' ); ?>
								<code>mount -t cifs //server/share /mnt/share -o username=user,password=pass,uid=www-data</code>.
								<?php esc_html_e( 'На Windows подойдёт UNC-путь \\server\share\wppa. Схемы вида smb:// не поддерживаются.', 'wp-personal-account' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppa-folder-file"><?php esc_html_e( 'Имя файла', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="text" class="regular-text code" id="wppa-folder-file" name="wppa_settings[export_folder][file]" value="<?php echo esc_attr( $wppa_folder['file'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Подстановки: {date}, {time}, {datetime}, {site}. Расширение .csv добавится само.', 'wp-personal-account' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Расписание', 'wp-personal-account' ); ?></th>
						<td>
							<select name="wppa_settings[export_folder][schedule]">
								<?php foreach ( WPPA_Export_Folder::schedules() as $wppa_schedule_key => $wppa_schedule_label ) : ?>
									<option value="<?php echo esc_attr( $wppa_schedule_key ); ?>" <?php selected( $wppa_folder['schedule'], $wppa_schedule_key ); ?>><?php echo esc_html( $wppa_schedule_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<label for="wppa-folder-time" style="margin-left:10px"><?php esc_html_e( 'время', 'wp-personal-account' ); ?></label>
							<input type="time" id="wppa-folder-time" name="wppa_settings[export_folder][time]" value="<?php echo esc_attr( $wppa_folder['time'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Время по часовому поясу сайта, учитывается для «раз в день» и «раз в неделю». Запуск делает WP-Cron при обращении к сайту.', 'wp-personal-account' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppa-folder-keep"><?php esc_html_e( 'Хранить файлов', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="number" class="small-text" id="wppa-folder-keep" name="wppa_settings[export_folder][keep]" value="<?php echo esc_attr( $wppa_folder['keep'] ); ?>" min="0" max="365" />
							<p class="description"><?php esc_html_e( 'Старые CSV сверх этого числа удаляются из папки. 0 — не удалять ничего.', 'wp-personal-account' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Состав выгрузки', 'wp-personal-account' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Группы колонок', 'wp-personal-account' ); ?></legend>
								<?php foreach ( WPPA_Export::groups() as $wppa_group_key => $wppa_group_label ) : ?>
									<label style="margin-right:12px">
										<input
											type="checkbox"
											name="wppa_settings[export_folder][groups][]"
											value="<?php echo esc_attr( $wppa_group_key ); ?>"
											<?php checked( in_array( $wppa_group_key, (array) $wppa_folder['groups'], true ) ); ?>
											<?php echo 'account' === $wppa_group_key ? 'disabled' : ''; ?>
										/>
										<?php
										if ( 'account' === $wppa_group_key ) {
											echo '<input type="hidden" name="wppa_settings[export_folder][groups][]" value="account" />';
										}
										echo esc_html( $wppa_group_label );
										?>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p>
								<label for="wppa-folder-scope"><?php esc_html_e( 'Кого', 'wp-personal-account' ); ?></label>
								<select id="wppa-folder-scope" name="wppa_settings[export_folder][scope]">
									<?php foreach ( WPPA_Export::scopes() as $wppa_scope_key => $wppa_scope_label ) : ?>
										<option value="<?php echo esc_attr( $wppa_scope_key ); ?>" <?php selected( $wppa_folder['scope'], $wppa_scope_key ); ?>><?php echo esc_html( $wppa_scope_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<label for="wppa-folder-delimiter" style="margin-left:10px"><?php esc_html_e( 'Разделитель', 'wp-personal-account' ); ?></label>
								<select id="wppa-folder-delimiter" name="wppa_settings[export_folder][delimiter]">
									<option value=";" <?php selected( $wppa_folder['delimiter'], ';' ); ?>>;</option>
									<option value="," <?php selected( $wppa_folder['delimiter'], ',' ); ?>>,</option>
								</select>
								<label for="wppa-folder-limit" style="margin-left:10px"><?php esc_html_e( 'Максимум строк', 'wp-personal-account' ); ?></label>
								<input type="number" class="small-text" id="wppa-folder-limit" name="wppa_settings[export_folder][limit]" value="<?php echo esc_attr( $wppa_folder['limit'] ); ?>" min="1" max="20000" />
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Авторизация SMB', 'wp-personal-account' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wppa-smb-host"><?php esc_html_e( 'Сервер', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="text" class="regular-text code" id="wppa-smb-host" name="wppa_settings[smb][host]" value="<?php echo esc_attr( $wppa_smb['host'] ); ?>" placeholder="fileserver" />
							<label for="wppa-smb-share" style="margin-left:10px"><?php esc_html_e( 'шара', 'wp-personal-account' ); ?></label>
							<input type="text" class="regular-text code" id="wppa-smb-share" name="wppa_settings[smb][share]" value="<?php echo esc_attr( $wppa_smb['share'] ); ?>" placeholder="exchange" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppa-smb-dir"><?php esc_html_e( 'Папка внутри шары', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="text" class="regular-text code" id="wppa-smb-dir" name="wppa_settings[smb][dir]" value="<?php echo esc_attr( $wppa_smb['dir'] ); ?>" placeholder="reports/wppa" />
							<p class="description"><?php esc_html_e( 'Можно оставить пустым — файл запишется в корень шары.', 'wp-personal-account' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Учётная запись', 'wp-personal-account' ); ?></th>
						<td>
							<p>
								<label for="wppa-smb-domain"><?php esc_html_e( 'Домен', 'wp-personal-account' ); ?></label>
								<input type="text" class="regular-text" id="wppa-smb-domain" name="wppa_settings[smb][domain]" value="<?php echo esc_attr( $wppa_smb['domain'] ); ?>" placeholder="WORKGROUP" />
							</p>
							<p>
								<label for="wppa-smb-user"><?php esc_html_e( 'Пользователь', 'wp-personal-account' ); ?></label>
								<input type="text" class="regular-text" id="wppa-smb-user" name="wppa_settings[smb][user]" value="<?php echo esc_attr( $wppa_smb['user'] ); ?>" autocomplete="off" />
							</p>
							<p>
								<label for="wppa-smb-pass"><?php esc_html_e( 'Пароль', 'wp-personal-account' ); ?></label>
								<input type="password" class="regular-text" id="wppa-smb-pass" name="wppa_settings[smb][pass]" value="<?php echo esc_attr( $wppa_smb['pass'] ); ?>" autocomplete="new-password" />
							</p>
							<p class="description">
								<?php
								printf(
									/* translators: %s: имя константы */
									esc_html__( 'Пароль виден только тому, кто открыл настройки. Чтобы держать его вне базы данных, задайте в wp-config.php: %s — значение из константы важнее сохранённого.', 'wp-personal-account' ),
									'<code>define( \'' . esc_html( WPPA_SMB::PASSWORD_CONSTANT ) . '\', \'пароль\' );</code>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Проверка', 'wp-personal-account' ); ?></th>
						<td>
							<?php $wppa_smb_test = WPPA_SMB::test( $wppa_smb ); ?>
							<p>
								<span class="wppa-badge <?php echo $wppa_smb_test['ok'] ? 'wppa-badge--ok' : 'wppa-badge--warn'; ?>">
									<?php echo $wppa_smb_test['ok'] ? esc_html__( 'Доступ есть', 'wp-personal-account' ) : esc_html__( 'Доступа нет', 'wp-personal-account' ); ?>
								</span>
								<span class="description"><?php echo esc_html( $wppa_smb_test['message'] ); ?></span>
							</p>
							<?php $wppa_mount = WPPA_SMB::mount_command( $wppa_smb ); ?>
							<p class="description">
								<?php esc_html_e( 'Если ни расширения, ни утилиты нет — смонтируйте шару с этими учётными данными:', 'wp-personal-account' ); ?>
							</p>
							<p><code><?php echo esc_html( 'cat > ' . $wppa_mount['credentials_file'] . " <<'EOF'\n" . $wppa_mount['credentials'] . 'EOF' ); ?></code></p>
							<p><code><?php echo esc_html( $wppa_mount['command'] ); ?></code></p>
							<p class="description"><?php esc_html_e( 'Пароль вынесен в отдельный файл учётных данных (chmod 600), иначе он был бы виден в списке процессов.', 'wp-personal-account' ); ?></p>
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
