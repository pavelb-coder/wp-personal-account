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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Скрипт предпросмотра оформления — только на странице настроек плагина.
	 *
	 * @param string $hook Текущий хук.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'wppa-settings' ) ) {
			return;
		}

		wp_enqueue_script(
			'wppa-admin-theme',
			WPPA_URL . 'assets/js/admin-theme.js',
			array(),
			WPPA_VERSION,
			true
		);
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
		// 0 отключает ограничение, отрицательные значения не превращаем в положительные.
		$age = isset( $input['registration_min_age'] ) && is_numeric( $input['registration_min_age'] ) ? (int) $input['registration_min_age'] : 0;
		$out['registration_min_age'] = min( 120, max( 0, $age ) );
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

		// Оформление: цвета, скругления и свой CSS (WPPA_Theme).
		$out = array_merge( $out, WPPA_Theme::sanitize( $input ) );

		// Персональные данные: разделы и поля (подпись, раздел, порядок, показ, обязательность,
		// добавление своих полей и разделов, удаление своих).
		$fields_saved   = isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : array();
		$fields_new     = isset( $input['fields_new'] ) && is_array( $input['fields_new'] ) ? $input['fields_new'] : array();
		$sections_saved = isset( $input['field_sections'] ) && is_array( $input['field_sections'] ) ? $input['field_sections'] : array();
		$sections_new   = isset( $input['field_sections_new'] ) && is_array( $input['field_sections_new'] ) ? $input['field_sections_new'] : array();

		$sections_out     = array();
		$sections_removed = array();

		foreach ( WPPA_Fields::sections() as $section_id => $section ) {
			$row = isset( $sections_saved[ $section_id ] ) && is_array( $sections_saved[ $section_id ] ) ? $sections_saved[ $section_id ] : null;

			// Формы раздела нет в запросе — оставляем как было.
			if ( null === $row ) {
				continue;
			}

			$deleted = ! empty( $row['delete'] ) && 'main' !== $section_id;

			// Свой раздел удаляем совсем, у встроенного ставим отметку.
			if ( $deleted && ! empty( $section['custom'] ) ) {
				$sections_removed[] = $section_id;
				continue;
			}

			$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';

			$sections_out[ $section_id ] = array(
				'label'   => '' !== trim( $label ) ? $label : $section['label'],
				'order'   => isset( $row['order'] ) ? (int) $row['order'] : (int) $section['order'],
				'deleted' => $deleted ? 1 : 0,
			);
		}

		// Новые разделы из пустых строк формы.
		foreach ( $sections_new as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$key   = sanitize_key( isset( $row['key'] ) ? (string) $row['key'] : '' );
			$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';

			if ( '' === $key || '' === trim( $label ) || isset( $sections_out[ $key ] ) || isset( WPPA_Fields::default_sections()[ $key ] ) ) {
				continue;
			}

			$sections_out[ $key ] = array(
				'label'   => $label,
				'order'   => isset( $row['order'] ) ? (int) $row['order'] : 100,
				'deleted' => 0,
			);
		}

		$out['field_sections'] = $sections_out;

		// Куда можно положить поле: разделы, которые существуют после сохранения.
		// Разделы, формы которых не было в запросе, считаются прежними.
		$valid_sections = array();

		foreach ( WPPA_Fields::sections() as $section_id => $section ) {
			if ( in_array( $section_id, $sections_removed, true ) ) {
				continue;
			}

			if ( ! isset( $sections_out[ $section_id ] ) || empty( $sections_out[ $section_id ]['deleted'] ) ) {
				$valid_sections[] = $section_id;
			}
		}

		foreach ( $sections_out as $section_id => $row ) {
			if ( empty( $row['deleted'] ) && ! in_array( $section_id, $valid_sections, true ) ) {
				$valid_sections[] = $section_id;
			}
		}

		$fields_out = array();
		$layout     = WPPA_Fields::default_layout();
		$types      = array_keys( WPPA_Fields::types() );

		foreach ( WPPA_Fields::all() as $field_id => $def ) {
			$row       = isset( $fields_saved[ $field_id ] ) && is_array( $fields_saved[ $field_id ] ) ? $fields_saved[ $field_id ] : array();
			$is_custom = ! empty( $def['custom'] );

			// Своё поле помечено на удаление — значения остаются в базе, но поле больше не выводится.
			if ( $is_custom && ! empty( $row['delete'] ) ) {
				continue;
			}

			$enabled  = empty( $row['enabled'] ) ? 0 : 1;
			$required = $enabled && ! empty( $row['required'] ) ? 1 : 0;
			$label    = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
			$section  = isset( $row['section'] ) ? sanitize_key( (string) $row['section'] ) : '';
			$order    = isset( $row['order'] ) ? (int) $row['order'] : 0;

			if ( '' === trim( $label ) ) {
				$label = isset( $def['label'] ) ? (string) $def['label'] : $field_id;
			}

			if ( ! in_array( $section, $valid_sections, true ) ) {
				$section = 'main';
			}

			if ( $order <= 0 ) {
				$order = isset( $layout[ $field_id ] ) ? (int) $layout[ $field_id ][1] : 500;
			}

			$entry = array(
				'enabled'  => $enabled,
				'required' => $required,
				'label'    => $label,
				'section'  => $section,
				'order'    => $order,
				'custom'   => $is_custom ? 1 : 0,
			);

			if ( $is_custom ) {
				$type = isset( $row['type'] ) ? sanitize_key( (string) $row['type'] ) : '';

				$entry['type']        = in_array( $type, $types, true ) ? $type : ( isset( $def['type'] ) ? (string) $def['type'] : 'text' );
				$entry['placeholder'] = isset( $row['placeholder'] ) ? sanitize_text_field( (string) $row['placeholder'] ) : '';
				$entry['hint']        = isset( $row['hint'] ) ? sanitize_text_field( (string) $row['hint'] ) : '';
				$entry['options']     = isset( $row['options'] ) ? sanitize_textarea_field( (string) $row['options'] ) : '';
			}

			$fields_out[ $field_id ] = $entry;
		}

		// Новые поля из пустых строк формы.
		foreach ( $fields_new as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$key   = preg_replace( '/^custom_/', '', sanitize_key( isset( $row['key'] ) ? (string) $row['key'] : '' ) );
			$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';

			if ( '' === $key || '' === trim( $label ) ) {
				continue;
			}

			$id = 'custom_' . $key;

			if ( isset( $fields_out[ $id ] ) || isset( WPPA_Fields::registry()[ $id ] ) ) {
				continue;
			}

			$type    = isset( $row['type'] ) ? sanitize_key( (string) $row['type'] ) : 'text';
			$section = isset( $row['section'] ) ? sanitize_key( (string) $row['section'] ) : 'main';

			if ( ! in_array( $section, $valid_sections, true ) ) {
				$section = 'main';
			}

			$fields_out[ $id ] = array(
				'enabled'     => empty( $row['enabled'] ) ? 0 : 1,
				'required'    => empty( $row['enabled'] ) || empty( $row['required'] ) ? 0 : 1,
				'label'       => $label,
				'section'     => $section,
				'order'       => isset( $row['order'] ) && (int) $row['order'] > 0 ? (int) $row['order'] : 500,
				'custom'      => 1,
				'type'        => in_array( $type, $types, true ) ? $type : 'text',
				'placeholder' => '',
				'hint'        => '',
				'options'     => '',
			);
		}

		$out['fields'] = $fields_out;

		$out['require_mentor'] = empty( $input['require_mentor'] ) ? 0 : 1;

		// Подключение к сетевой локальной папке.
		$smb_saved = isset( $input['smb'] ) && is_array( $input['smb'] ) ? $input['smb'] : array();
		$smb_path  = isset( $smb_saved['path'] ) ? trim( sanitize_text_field( (string) $smb_saved['path'] ) ) : '';

		$out['smb'] = array(
			'path' => preg_replace( '#[\x00-\x1F]#', '', $smb_path ),
			'user' => isset( $smb_saved['user'] ) ? sanitize_text_field( $smb_saved['user'] ) : '',
			'pass' => isset( $smb_saved['pass'] ) ? (string) $smb_saved['pass'] : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- пароль храним как есть.
		);

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
			'groups_seen' => array_keys( WPPA_Export::groups() ),
			'scope'     => $folder_scope,
			'delimiter' => isset( $folder_saved['delimiter'] ) && ',' === $folder_saved['delimiter'] ? ',' : ';',
			'limit'     => isset( $folder_saved['limit'] ) ? min( 20000, absint( $folder_saved['limit'] ) ) : 5000,
			'on_change'    => empty( $folder_saved['on_change'] ) ? 0 : 1,
			'change_delay' => isset( $folder_saved['change_delay'] ) && array_key_exists( (int) $folder_saved['change_delay'], WPPA_Export_Folder::delays() ) ? (int) $folder_saved['change_delay'] : 5,
		);

		// Копирование файлов команд в сетевую папку: галочка в «Экспорт в папку».
		$out['file_export'] = empty( $input['file_export'] ) ? 0 : 1;

		// Требования к паролю.
		$rules_saved = isset( $input['password_rules'] ) && is_array( $input['password_rules'] ) ? $input['password_rules'] : array();
		$rules_out   = array();

		foreach ( array_keys( WPPA_Password::rules() ) as $rule_id ) {
			$rules_out[ $rule_id ] = empty( $rules_saved[ $rule_id ] ) ? 0 : 1;
		}

		$out['password_rules'] = $rules_out;

		// Сертификаты: бланк WP Diplom, письмо с PDF и уведомление об выдаче.
		$out['certificate_template'] = isset( $input['certificate_template'] ) ? absint( $input['certificate_template'] ) : 0;
		$out['certificate_email']    = empty( $input['certificate_email'] ) ? 0 : 1;

		// Выгрузка PDF в папку: флаг, подпапка и шаблон имени файла.
		$out['certificate_export'] = empty( $input['certificate_export'] ) ? 0 : 1;

		$folder = isset( $input['certificate_export_dir'] ) ? sanitize_text_field( wp_unslash( $input['certificate_export_dir'] ) ) : '';
		$folder = trim( str_replace( array( '\\', '..' ), array( '/', '' ), $folder ), '/ ' );
		$out['certificate_export_dir'] = '' === $folder ? WPPA_Certificate_Export::DEFAULT_FOLDER : $folder;

		$pattern = isset( $input['certificate_filename'] ) ? sanitize_text_field( wp_unslash( $input['certificate_filename'] ) ) : '';
		$out['certificate_filename'] = '' === trim( $pattern ) ? WPPA_Certificate_Export::DEFAULT_TEMPLATE : $pattern;

		// Кнопка со ссылкой.
		$link_saved = isset( $input['link_button'] ) && is_array( $input['link_button'] ) ? $input['link_button'] : array();

		$out['link_button'] = array(
			'url'          => isset( $link_saved['url'] ) ? esc_url_raw( trim( (string) $link_saved['url'] ) ) : '',
			'text'         => isset( $link_saved['text'] ) ? sanitize_text_field( $link_saved['text'] ) : '',
			'note'         => isset( $link_saved['note'] ) ? sanitize_textarea_field( (string) $link_saved['note'] ) : '',
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

			<?php
			$wppa_notice = WPPA_Admin::tool_notice();
			$wppa_mode   = WPPA_Export_Folder::config();
			$wppa_state  = WPPA_Export_Folder::check_folder( $wppa_mode['path'] );
			$wppa_target = $wppa_mode['path'];
			?>

			<?php if ( $wppa_notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( 'success' === $wppa_notice[0] ? 'success' : 'error' ); ?> is-dismissible">
					<p><?php echo esc_html( $wppa_notice[1] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="wppa-admin" style="margin:16px 0 24px;padding:16px 20px;border:1px solid #dcdcde;border-radius:8px;background:#fff;max-width:900px">
				<h2 style="margin-top:0"><?php esc_html_e( 'Сетевая папка', 'wp-personal-account' ); ?></h2>
				<p style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 8px">
					<span class="wppa-badge <?php echo $wppa_state['ok'] ? 'wppa-badge--ok' : 'wppa-badge--warn'; ?>">
						<?php echo $wppa_state['ok'] ? esc_html__( 'Подключение есть', 'wp-personal-account' ) : esc_html__( 'Подключения нет', 'wp-personal-account' ); ?>
					</span>
					<span class="wppa-admin__muted">
						<?php
						printf(
							/* translators: 1: способ, 2: путь */
							esc_html__( '%1$s: %2$s', 'wp-personal-account' ),
							'smb' === $wppa_mode['mode'] ? esc_html__( 'Сетевая папка с авторизацией', 'wp-personal-account' ) : esc_html__( 'Папка на сервере', 'wp-personal-account' ),
							'<code>' . esc_html( '' !== $wppa_target ? $wppa_target : '—' ) . '</code>'
						);
						?>
					</span>
				</p>
				<p class="wppa-admin__muted" style="margin:0 0 12px"><?php echo esc_html( $wppa_state['message'] ); ?></p>

				<div style="display:flex;gap:10px;flex-wrap:wrap">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'wppa_tool' ); ?>
						<input type="hidden" name="action" value="wppa_tool" />
						<input type="hidden" name="wppa_tool" value="folder_verify" />
						<button type="submit" class="button"><?php esc_html_e( 'Проверить подключение к сетевой папке', 'wp-personal-account' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'wppa_tool' ); ?>
						<input type="hidden" name="action" value="wppa_tool" />
						<input type="hidden" name="wppa_tool" value="export_folder_now" />
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Экспортировать', 'wp-personal-account' ); ?></button>
					</form>
				</div>
				<p class="description" style="margin:10px 0 0">
					<?php esc_html_e( 'Кнопки используют сохранённые настройки: если меняли поля ниже — сначала сохраните изменения. Кнопка «Экспортировать» пишет CSV аккаунтов и дополнительно копирует файлы наставников в папки команд: «Команды → название команды». Результат обеих операций попадает в журнал в «Инструментах».', 'wp-personal-account' ); ?>
				</p>
			</div>

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
						<th scope="row"><label for="wppa-reg-min-age"><?php esc_html_e( 'Минимальный возраст при регистрации', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="number" id="wppa-reg-min-age" name="wppa_settings[registration_min_age]" min="0" max="120" step="1" value="<?php echo esc_attr( (string) $s['registration_min_age'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Возраст в полных годах на дату регистрации по часовому поясу сайта. 0 — без ограничения. Если указан возраст, дата рождения обязательна; проверка действует для всех, включая наставников. Для родившихся 29 февраля в невисокосный год день рождения считается 1 марта.', 'wp-personal-account' ); ?></p>
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
									<?php esc_html_e( 'Скрывать панель WordPress (админ-бар) на всех страницах', 'wp-personal-account' ); ?>
								</label>
							</p>
							<p class="description"><?php esc_html_e( 'Панель скрывается на сайте и в админке для всех, кроме администраторов. Для администраторов действуют настройки WordPress. На мобильных экранах в админке остаётся кнопка открытия меню. Доступ в админку это не ограничивает.', 'wp-personal-account' ); ?></p>
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
											<th><?php esc_html_e( 'Подпись', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Раздел', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Порядок', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Показывать', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Обязательное', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Удалить', 'wp-personal-account' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php
										$wppa_sections_list = WPPA_Fields::sections();

										foreach ( WPPA_Fields::schema() as $wppa_field ) :
											$wppa_base = 'wppa_settings[fields][' . $wppa_field['id'] . ']';
											?>
											<tr>
												<td>
													<code><?php echo esc_html( $wppa_field['id'] ); ?></code>
													<?php if ( ! empty( $wppa_field['custom'] ) ) : ?>
														<br /><span class="description"><?php esc_html_e( 'своё поле', 'wp-personal-account' ); ?></span>
														<input type="hidden" name="<?php echo esc_attr( $wppa_base ); ?>[custom]" value="1" />
														<input type="hidden" name="<?php echo esc_attr( $wppa_base ); ?>[type]" value="<?php echo esc_attr( isset( $wppa_field['type'] ) ? $wppa_field['type'] : 'text' ); ?>" />
														<input type="hidden" name="<?php echo esc_attr( $wppa_base ); ?>[placeholder]" value="<?php echo esc_attr( isset( $wppa_field['placeholder'] ) ? $wppa_field['placeholder'] : '' ); ?>" />
														<input type="hidden" name="<?php echo esc_attr( $wppa_base ); ?>[hint]" value="<?php echo esc_attr( isset( $wppa_field['hint'] ) ? $wppa_field['hint'] : '' ); ?>" />
														<input type="hidden" name="<?php echo esc_attr( $wppa_base ); ?>[options]" value="<?php echo esc_attr( isset( $wppa_field['options'] ) ? $wppa_field['options'] : '' ); ?>" />
													<?php endif; ?>
												</td>
												<td>
													<input
														type="text"
														class="regular-text"
														id="wppa-field-<?php echo esc_attr( $wppa_field['id'] ); ?>"
														name="<?php echo esc_attr( $wppa_base ); ?>[label]"
														value="<?php echo esc_attr( $wppa_field['label'] ); ?>"
													/>
												</td>
												<td>
													<select name="<?php echo esc_attr( $wppa_base ); ?>[section]">
														<?php foreach ( $wppa_sections_list as $wppa_section_id => $wppa_section ) : ?>
															<option value="<?php echo esc_attr( $wppa_section_id ); ?>" <?php selected( $wppa_field['section'], $wppa_section_id ); ?>><?php echo esc_html( $wppa_section['label'] ); ?></option>
														<?php endforeach; ?>
													</select>
												</td>
												<td>
													<input type="number" class="small-text" name="<?php echo esc_attr( $wppa_base ); ?>[order]" value="<?php echo esc_attr( (int) $wppa_field['order'] ); ?>" />
												</td>
												<td>
													<input
														type="checkbox"
														name="<?php echo esc_attr( $wppa_base ); ?>[enabled]"
														value="1"
														<?php checked( 1, (int) $wppa_field['enabled'] ); ?>
													/>
												</td>
												<td>
													<input
														type="checkbox"
														name="<?php echo esc_attr( $wppa_base ); ?>[required]"
														value="1"
														<?php checked( 1, (int) $wppa_field['required'] ); ?>
													/>
												</td>
												<td>
													<?php if ( ! empty( $wppa_field['custom'] ) ) : ?>
														<input type="checkbox" name="<?php echo esc_attr( $wppa_base ); ?>[delete]" value="1" />
													<?php else : ?>
														<span class="description">—</span>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
								<p class="description"><?php esc_html_e( 'Своё поле добавляется пустой строкой в таблице «Добавить поле». Удалить можно только своё поле: встроенные достаточно скрыть. Значения удалённого поля остаются в базе, но не выводятся в кабинете и не попадают в выгрузку.', 'wp-personal-account' ); ?></p>

								<h4><?php esc_html_e( 'Добавить поле', 'wp-personal-account' ); ?></h4>
								<table class="widefat striped wppa-admin__fields-new">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Ключ', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Подпись', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Тип', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Раздел', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Порядок', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Показывать', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Обязательное', 'wp-personal-account' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php for ( $wppa_i = 0; $wppa_i < 3; $wppa_i++ ) : ?>
											<?php $wppa_new = 'wppa_settings[fields_new][' . $wppa_i . ']'; ?>
											<tr>
												<td>
													<input type="text" class="regular-text code" name="<?php echo esc_attr( $wppa_new ); ?>[key]" value="" placeholder="snils" />
												</td>
												<td>
													<input type="text" class="regular-text" name="<?php echo esc_attr( $wppa_new ); ?>[label]" value="" placeholder="<?php esc_attr_e( 'СНИЛС', 'wp-personal-account' ); ?>" />
												</td>
												<td>
													<select name="<?php echo esc_attr( $wppa_new ); ?>[type]">
														<?php foreach ( WPPA_Fields::types() as $wppa_type => $wppa_type_label ) : ?>
															<option value="<?php echo esc_attr( $wppa_type ); ?>"><?php echo esc_html( $wppa_type_label ); ?></option>
														<?php endforeach; ?>
													</select>
												</td>
												<td>
													<select name="<?php echo esc_attr( $wppa_new ); ?>[section]">
														<?php foreach ( $wppa_sections_list as $wppa_section_id => $wppa_section ) : ?>
															<option value="<?php echo esc_attr( $wppa_section_id ); ?>"><?php echo esc_html( $wppa_section['label'] ); ?></option>
														<?php endforeach; ?>
													</select>
												</td>
												<td>
													<input type="number" class="small-text" name="<?php echo esc_attr( $wppa_new ); ?>[order]" value="" placeholder="500" />
												</td>
												<td>
													<input type="checkbox" name="<?php echo esc_attr( $wppa_new ); ?>[enabled]" value="1" />
												</td>
												<td>
													<input type="checkbox" name="<?php echo esc_attr( $wppa_new ); ?>[required]" value="1" />
												</td>
											</tr>
										<?php endfor; ?>
									</tbody>
								</table>
								<p class="description"><?php esc_html_e( 'Ключ — латиница, цифры и подчёркивание; из него собирается имя поля и мета-ключ. Пустые строки не сохраняются. Для типа «Список» варианты перечисляются по одному в настройках поля.', 'wp-personal-account' ); ?></p>

								<h4><?php esc_html_e( 'Разделы', 'wp-personal-account' ); ?></h4>
								<table class="widefat striped wppa-admin__sections">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Ключ', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Название', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Порядок', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Удалить', 'wp-personal-account' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $wppa_sections_list as $wppa_section_id => $wppa_section ) : ?>
											<?php $wppa_sbase = 'wppa_settings[field_sections][' . $wppa_section_id . ']'; ?>
											<tr>
												<td>
													<code><?php echo esc_html( $wppa_section_id ); ?></code>
													<?php if ( ! empty( $wppa_section['custom'] ) ) : ?>
														<br /><span class="description"><?php esc_html_e( 'свой раздел', 'wp-personal-account' ); ?></span>
													<?php endif; ?>
												</td>
												<td>
													<input type="text" class="regular-text" name="<?php echo esc_attr( $wppa_sbase ); ?>[label]" value="<?php echo esc_attr( $wppa_section['label'] ); ?>" />
												</td>
												<td>
													<input type="number" class="small-text" name="<?php echo esc_attr( $wppa_sbase ); ?>[order]" value="<?php echo esc_attr( (int) $wppa_section['order'] ); ?>" />
												</td>
												<td>
													<?php if ( 'main' === $wppa_section_id ) : ?>
														<span class="description"><?php esc_html_e( 'основной', 'wp-personal-account' ); ?></span>
													<?php else : ?>
														<input type="checkbox" name="<?php echo esc_attr( $wppa_sbase ); ?>[delete]" value="1" />
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>

								<h4><?php esc_html_e( 'Добавить раздел', 'wp-personal-account' ); ?></h4>
								<table class="widefat striped wppa-admin__sections-new">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Ключ', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Название', 'wp-personal-account' ); ?></th>
											<th><?php esc_html_e( 'Порядок', 'wp-personal-account' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php for ( $wppa_i = 0; $wppa_i < 2; $wppa_i++ ) : ?>
											<?php $wppa_new = 'wppa_settings[field_sections_new][' . $wppa_i . ']'; ?>
											<tr>
												<td><input type="text" class="regular-text code" name="<?php echo esc_attr( $wppa_new ); ?>[key]" value="" placeholder="study" /></td>
												<td><input type="text" class="regular-text" name="<?php echo esc_attr( $wppa_new ); ?>[label]" value="" placeholder="<?php esc_attr_e( 'Учёба', 'wp-personal-account' ); ?>" /></td>
												<td><input type="number" class="small-text" name="<?php echo esc_attr( $wppa_new ); ?>[order]" value="" placeholder="100" /></td>
											</tr>
										<?php endfor; ?>
									</tbody>
								</table>
								<p class="description"><?php esc_html_e( 'Раздел — заголовок в форме регистрации и в профиле. Удалённый раздел исчезает из кабинета, а его поля переходят в «Основные данные». Раздел «Основные данные» удалить нельзя: он принимает поля без раздела. В выгрузке все личные поля остаются в одной группе «Личные данные».', 'wp-personal-account' ); ?></p>
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

				<h2><?php esc_html_e( 'Сертификаты', 'wp-personal-account' ); ?></h2>
				<?php
				$wppa_cert_ready     = class_exists( 'WPPA_Certificates' ) && WPPA_Certificates::available();
				$wppa_cert_templates = $wppa_cert_ready ? WPPA_Certificates::templates() : array();
				$wppa_cert_current   = (int) WPPA_Plugin::setting( WPPA_Certificates::TEMPLATE_KEY, 0 );
				?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wppa-cert-template"><?php esc_html_e( 'Бланк', 'wp-personal-account' ); ?></label></th>
						<td>
							<?php if ( ! $wppa_cert_ready ) : ?>
								<p class="description">
									<?php esc_html_e( 'Сертификаты выдаёт плагин WP Diplom — сейчас он не активен. Активируйте его, и здесь появится выбор бланка, а у куратора во вкладке «Участники» — кнопки «Выдать сертификат».', 'wp-personal-account' ); ?>
								</p>
							<?php else : ?>
								<select id="wppa-cert-template" name="wppa_settings[certificate_template]">
									<option value="0"><?php esc_html_e( 'Бланк WP Diplom по умолчанию', 'wp-personal-account' ); ?></option>
									<?php foreach ( $wppa_cert_templates as $wppa_cert_id => $wppa_cert_title ) : ?>
										<option value="<?php echo esc_attr( (string) (int) $wppa_cert_id ); ?>" <?php selected( $wppa_cert_current, (int) $wppa_cert_id ); ?>>
											<?php echo esc_html( $wppa_cert_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Бланки создаются в разделе «Сертификаты → Бланки» плагина WP Diplom: формат, фон, поля и тексты. Номер и код проверки присваиваются при выдаче.', 'wp-personal-account' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Письмо', 'wp-personal-account' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wppa_settings[certificate_email]" value="1" <?php checked( 1, (int) WPPA_Plugin::setting( WPPA_Certificates::EMAIL_KEY, 0 ) ); ?> />
								<?php esc_html_e( 'Отправлять сертификат участнику письмом сразу при выдаче', 'wp-personal-account' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Письмо с PDF отправляет WP Diplom на email из аккаунта участника. Без галочки сертификат просто появляется в кабинете.', 'wp-personal-account' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Выгрузка в папку', 'wp-personal-account' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wppa_settings[certificate_export]" value="1" <?php checked( 1, (int) WPPA_Plugin::setting( WPPA_Certificate_Export::SETTING, 1 ) ); ?> />
								<?php esc_html_e( 'Сохранять PDF каждого выданного сертификата в отдельную папку', 'wp-personal-account' ); ?>
							</label>
							<p class="description">
								<?php
								printf(
									/* translators: %s: путь к папке */
									esc_html__( 'Файлы ложатся в папку %s. Если сетевая папка не задана в настройках ниже, используется папка uploads сайта.', 'wp-personal-account' ),
									'<code>' . esc_html( WPPA_Certificate_Export::display_path() ) . '</code>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppa-cert-export-dir"><?php esc_html_e( 'Подпапка', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="text" id="wppa-cert-export-dir" name="wppa_settings[certificate_export_dir]" value="<?php echo esc_attr( (string) WPPA_Plugin::setting( WPPA_Certificate_Export::FOLDER_KEY, WPPA_Certificate_Export::DEFAULT_FOLDER ) ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Имя папки внутри сетевой папки или внутри uploads. Пустое значение вернёт «Сертификаты».', 'wp-personal-account' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppa-cert-filename"><?php esc_html_e( 'Имя файла', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="text" id="wppa-cert-filename" name="wppa_settings[certificate_filename]" value="<?php echo esc_attr( (string) WPPA_Plugin::setting( WPPA_Certificate_Export::TEMPLATE_KEY, WPPA_Certificate_Export::DEFAULT_TEMPLATE ) ); ?>" class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Подстановки:', 'wp-personal-account' ); ?>
								<?php foreach ( WPPA_Certificate_Export::placeholder_list() as $wppa_cert_placeholder ) : ?>
									<code><?php echo esc_html( $wppa_cert_placeholder ); ?></code>
								<?php endforeach; ?>
								<?php esc_html_e( '— по умолчанию файл называется «Фамилия Имя Отчество». Расширение .pdf добавляется само; если файл с таким именем уже есть, к нему добавляется номер сертификата.', 'wp-personal-account' ); ?>
							</p>
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
						<th scope="row"><label for="wppa-link-note"><?php esc_html_e( 'Комментарий', 'wp-personal-account' ); ?></label></th>
						<td>
							<textarea id="wppa-link-note" name="wppa_settings[link_button][note]" rows="3" class="large-text"><?php echo esc_textarea( $wppa_link['note'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Необязательный текст под кнопкой: например, куда ведёт ссылка или что понадобится для входа. Выводится под кнопкой в любом её состоянии — и открытом, и закрытом. Переносы строк сохраняются, разметка вырезается.', 'wp-personal-account' ); ?></p>
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
				?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Файлы команд', 'wp-personal-account' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wppa_settings[file_export]" value="1" <?php checked( 1, (int) WPPA_Plugin::setting( 'file_export', 1 ) ); ?> />
								<?php esc_html_e( 'Копировать файлы наставников в папки команд', 'wp-personal-account' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Снимите галочку, чтобы файлы команд оставались только в кабинете: при прикреплении ничего не копируется, а кнопка «Экспортировать» пишет только CSV аккаунтов. Кураторские файлы не выгружаются никогда.', 'wp-personal-account' ); ?>
							</p>
						</td>
					</tr>
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
									<?php esc_html_e( 'Папка на сервере', 'wp-personal-account' ); ?>
								</label>
								<label>
									<input type="radio" name="wppa_settings[export_folder][mode]" value="smb" <?php checked( $wppa_folder['mode'], 'smb' ); ?> />
									<?php esc_html_e( 'Сетевая папка с логином и паролем', 'wp-personal-account' ); ?>
								</label>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Во втором случае используется путь, логин и пароль из блока «Сетевая локальная папка» ниже.', 'wp-personal-account' ); ?></p>
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
						<th scope="row"><?php esc_html_e( 'Экспорт при изменениях', 'wp-personal-account' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wppa_settings[export_folder][on_change]" value="1" <?php checked( $wppa_folder['on_change'], 1 ); ?> />
								<?php esc_html_e( 'Выгружать, когда меняются данные аккаунтов', 'wp-personal-account' ); ?>
							</label>
							<p>
								<label for="wppa-folder-change-delay"><?php esc_html_e( 'Не чаще, чем', 'wp-personal-account' ); ?></label>
								<select id="wppa-folder-change-delay" name="wppa_settings[export_folder][change_delay]">
									<?php foreach ( WPPA_Export_Folder::delays() as $wppa_delay => $wppa_delay_label ) : ?>
										<option value="<?php echo esc_attr( $wppa_delay ); ?>" <?php selected( (int) $wppa_folder['change_delay'], (int) $wppa_delay ); ?>>
											<?php echo esc_html( $wppa_delay_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</p>
							<p class="description">
								<?php esc_html_e( 'Регистрация, сохранение профиля, смена email, состав и настройки команд. Изменения подряд объединяются в одну выгрузку: новый файл не пишется на каждое сохранение. Работает вместе с расписанием и не отменяет его.', 'wp-personal-account' ); ?>
							</p>
							<?php $wppa_pending = WPPA_Export_Folder::pending(); ?>
							<?php if ( $wppa_pending ) : ?>
								<p class="description">
									<?php
									printf(
										/* translators: 1: число изменений, 2: время */
										esc_html__( 'Ожидает выгрузки: %1$d изменений, ближайшая — %2$s.', 'wp-personal-account' ),
										count( $wppa_pending ),
										esc_html( WPPA_Export_Folder::next_change() ? wp_date( 'd.m.Y H:i', WPPA_Export_Folder::next_change() ) : __( 'сразу', 'wp-personal-account' ) )
									);
									?>
								</p>
							<?php endif; ?>
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
							<p class="description">
								<?php
								printf(
									/* translators: 1: число групп, 2: число колонок */
									esc_html__( 'Сейчас в файл попадает %1$d групп и %2$d колонок. Новые группы (например, «Баллы за тесты») добавляются в выгрузку сами; снятая галочка сохраняется.', 'wp-personal-account' ),
									count( (array) $wppa_folder['groups'] ),
									count( WPPA_Export::columns( (array) $wppa_folder['groups'] ) )
								);
								?>
							</p>
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

				<h2><?php esc_html_e( 'Сетевая локальная папка', 'wp-personal-account' ); ?></h2>
				<?php $wppa_smb = WPPA_SMB::config(); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wppa-smb-path"><?php esc_html_e( 'Путь', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="text" class="large-text code" id="wppa-smb-path" name="wppa_settings[smb][path]" value="<?php echo esc_attr( $wppa_smb['path'] ); ?>" placeholder="\\fileserver\exchange\reports" />
							<p class="description">
								<?php esc_html_e( 'Подойдёт UNC-путь \\сервер\папка, адрес smb://сервер/папка/подпапка или обычный путь /mnt/share — плагин определит тип сам.', 'wp-personal-account' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppa-smb-user"><?php esc_html_e( 'Логин', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="wppa-smb-user" name="wppa_settings[smb][user]" value="<?php echo esc_attr( $wppa_smb['user'] ); ?>" autocomplete="off" placeholder="WORKGROUP\svc_wppa" />
							<p class="description"><?php esc_html_e( 'Домен можно указать через обратную косую черту: DOMAIN\пользователь.', 'wp-personal-account' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppa-smb-pass"><?php esc_html_e( 'Пароль', 'wp-personal-account' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="wppa-smb-pass" name="wppa_settings[smb][pass]" value="<?php echo esc_attr( $wppa_smb['pass'] ); ?>" autocomplete="new-password" />
							<p class="description">
								<?php
								printf(
									/* translators: %s: имя константы */
									esc_html__( 'Чтобы не держать пароль в базе, задайте в wp-config.php: %s — значение из константы важнее сохранённого.', 'wp-personal-account' ),
									'<code>define( \'' . esc_html( WPPA_SMB::PASSWORD_CONSTANT ) . '\', \'пароль\' );</code>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Файлы наставников', 'wp-personal-account' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Файлы, которые наставники прикрепляют к командам, автоматически копируются в эту папку — каждый в свою подпапку по названию команды и её номеру. Файлы куратора не выгружаются; прикреплённые в кабинете файлы остаются на месте, папка — это копия.', 'wp-personal-account' ); ?>
							</p>
							<p class="description">
								<?php
								printf(
									/* translators: %s: пример пути */
									esc_html__( 'Например: %s', 'wp-personal-account' ),
									'<code>' . esc_html( rtrim( (string) $wppa_smb['path'], '/\\' ) . DIRECTORY_SEPARATOR . 'Студия «Вектор» (12)' ) . '</code>'
								);
								?>
							</p>
							<p>
								<span class="wppa-badge <?php echo WPPA_Team_Files_Export::enabled() ? 'wppa-badge--ok' : 'wppa-badge--warn'; ?>">
									<?php echo WPPA_Team_Files_Export::enabled() ? esc_html__( 'Выгрузка включена', 'wp-personal-account' ) : esc_html__( 'Выгрузка выключена', 'wp-personal-account' ); ?>
								</span>
								<span class="description"><?php esc_html_e( 'Включается автоматически, как только задан путь. Отключить можно фильтром wppa_team_files_export.', 'wp-personal-account' ); ?></span>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Проверка', 'wp-personal-account' ); ?></th>
						<td>
							<?php
							$wppa_smb_test = WPPA_SMB::test( $wppa_smb );
							$wppa_mount    = WPPA_SMB::mount_command( $wppa_smb );
							?>
							<p>
								<span class="wppa-badge <?php echo $wppa_smb_test['ok'] ? 'wppa-badge--ok' : 'wppa-badge--warn'; ?>">
									<?php echo $wppa_smb_test['ok'] ? esc_html__( 'Доступ есть', 'wp-personal-account' ) : esc_html__( 'Доступа нет', 'wp-personal-account' ); ?>
								</span>
								<span class="description"><?php echo esc_html( $wppa_smb_test['message'] ); ?></span>
							</p>
							<p class="description"><?php esc_html_e( 'Если утилиты smbclient нет, шару можно смонтировать в систему. Пароль при этом выносится в файл учётных данных (chmod 600), чтобы не светиться в списке процессов:', 'wp-personal-account' ); ?></p>
							<p><code><?php echo esc_html( 'cat > ' . $wppa_mount['credentials_file'] . " <<'EOF'\n" . $wppa_mount['credentials'] . 'EOF' ); ?></code></p>
							<p><code><?php echo esc_html( $wppa_mount['command'] ); ?></code></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Оформление', 'wp-personal-account' ); ?></h2>
				<p class="description" style="max-width:820px">
					<?php esc_html_e( 'Цвета кабинета хранятся в CSS-переменных (--wppa-accent, --wppa-bg, --wppa-surface, --wppa-text, --wppa-muted, --wppa-border, --wppa-radius) и применяются к шорткодам, виджету и блоку Gutenberg сразу после сохранения. Значения по умолчанию можно вернуть одной галочкой ниже.', 'wp-personal-account' ); ?>
				</p>
				<?php
				$wppa_theme     = WPPA_Theme::get();
				$wppa_presets   = WPPA_Theme::presets();
				$wppa_defaults  = WPPA_Theme::defaults();
				$wppa_heading   = WPPA_Theme::heading();
				$wppa_vars      = WPPA_Theme::vars( $wppa_theme );
				$wppa_preview   = '';
				foreach ( $wppa_vars as $wppa_var_name => $wppa_var_value ) {
					$wppa_preview .= $wppa_var_name . ':' . $wppa_var_value . ';';
				}
				?>
				<style>
					.wppa-theme-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px 24px; }
					.wppa-theme-group { margin: 14px 0 8px; font-size: 13px; }
					.wppa-theme-group:first-of-type { margin-top: 0; }
					.wppa-theme-field { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
					.wppa-theme-field input[type="color"] { width: 46px; height: 32px; padding: 2px; border-radius: 6px; }
					.wppa-theme-field code { background: #f0f0f1; padding: 2px 6px; border-radius: 4px; }
					.wppa-theme-hint { display: block; color: #646970; font-size: 12px; margin-top: 4px; }
					.wppa-theme-presets { display: flex; gap: 8px; flex-wrap: wrap; margin: 0 0 6px; }
					.wppa-theme-preview { margin-top: 8px; padding: 18px; border: 1px solid var(--wppa-border, #e2e8f0); border-radius: var(--wppa-radius, 14px); background: var(--wppa-bg, #f8fafc); max-width: 520px; }
					.wppa-theme-preview__card { background: var(--wppa-surface, #fff); border: 1px solid var(--wppa-border, #e2e8f0); border-radius: var(--wppa-radius, 14px); padding: 16px; color: var(--wppa-text, #0f172a); }
					.wppa-theme-preview__title { margin: 0 0 6px; font-size: 1rem; font-weight: 650; color: var(--wppa-text, #0f172a); }
					.wppa-theme-preview__heading { margin: 0 0 var(--wppa-heading-gap, 18px); font-size: calc(var(--wppa-heading-size, 28px) * .62); font-weight: var(--wppa-heading-weight, 700); line-height: 1.25; color: var(--wppa-heading-color, var(--wppa-text, #0f172a)); text-transform: var(--wppa-heading-case, none); text-align: var(--wppa-heading-align, left); }
					.wppa-theme-preview__muted { margin: 0 0 12px; font-size: .86rem; color: var(--wppa-muted, #64748b); }
					.wppa-theme-preview__tabs { display: flex; gap: 8px; flex-wrap: wrap; margin: 0 0 14px; }
					.wppa-theme-preview__tab { padding: 6px 12px; border-radius: 999px; font-size: .84rem; background: var(--wppa-bg, #f8fafc); color: var(--wppa-muted, #64748b); border: 1px solid var(--wppa-border, #e2e8f0); }
					.wppa-theme-preview__tab.is-active { background: var(--wppa-accent, #2563eb); color: var(--wppa-accent-contrast, #fff); border-color: var(--wppa-accent, #2563eb); }
					.wppa-theme-preview__btn { display: inline-block; padding: 8px 16px; border: 0; border-radius: calc(var(--wppa-radius, 14px) / 2); background: var(--wppa-accent, #2563eb); color: var(--wppa-accent-contrast, #fff); font-size: .88rem; cursor: default; }
					.wppa-theme-preview__range { display: flex; align-items: center; gap: 10px; }
				</style>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Готовые наборы', 'wp-personal-account' ); ?></th>
						<td>
							<div class="wppa-theme-presets">
								<?php foreach ( $wppa_presets as $wppa_preset_id => $wppa_preset ) : ?>
									<button
										type="button"
										class="button"
										data-wppa-preset="<?php echo esc_attr( wp_json_encode( $wppa_preset['colors'] ) ); ?>"
									><?php echo esc_html( $wppa_preset['label'] ); ?></button>
								<?php endforeach; ?>
							</div>
							<span class="wppa-theme-hint"><?php esc_html_e( 'Набор подставляет цвета в поля ниже — сохраните настройки, чтобы применить.', 'wp-personal-account' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Цвета', 'wp-personal-account' ); ?></th>
						<td>
							<?php foreach ( WPPA_Theme::groups() as $wppa_group ) : ?>
								<p class="wppa-theme-group"><strong><?php echo esc_html( $wppa_group['label'] ); ?></strong></p>
								<div class="wppa-theme-grid">
									<?php foreach ( $wppa_group['keys'] as $wppa_key ) : ?>
										<?php
										$wppa_colors = WPPA_Theme::colors();

										if ( ! isset( $wppa_colors[ $wppa_key ] ) ) {
											continue;
										}

										$wppa_color      = $wppa_colors[ $wppa_key ];
										$wppa_value      = (string) $wppa_theme[ $wppa_key ];
										$wppa_is_optional = '' === (string) $wppa_defaults[ $wppa_key ];
										$wppa_var        = '--wppa-' . str_replace( array( 'accent_color', 'accent_hover_color', 'accent_contrast_color', 'color_' ), array( 'accent', 'accent-hover', 'accent-contrast', '' ), $wppa_key );
										$wppa_fallback   = isset( $wppa_vars[ $wppa_var ] ) ? $wppa_vars[ $wppa_var ] : '';
										$wppa_field_id   = 'wppa-theme-' . $wppa_key;
										?>
										<div>
											<label for="<?php echo esc_attr( $wppa_field_id ); ?>"><strong><?php echo esc_html( $wppa_color['label'] ); ?></strong></label>
											<div class="wppa-theme-field">
												<?php if ( $wppa_is_optional ) : ?>
													<input
														type="text"
														class="regular-text code wppa-theme-input"
														id="<?php echo esc_attr( $wppa_field_id ); ?>"
														name="wppa_settings[<?php echo esc_attr( $wppa_key ); ?>]"
														value="<?php echo esc_attr( $wppa_value ); ?>"
														placeholder="<?php echo esc_attr( $wppa_fallback ); ?>"
														data-wppa-var="<?php echo esc_attr( $wppa_var ); ?>"
														data-wppa-fallback="<?php echo esc_attr( $wppa_fallback ); ?>"
														style="width:130px"
													/>
												<?php else : ?>
													<input
														type="color"
														class="wppa-theme-input"
														id="<?php echo esc_attr( $wppa_field_id ); ?>"
														name="wppa_settings[<?php echo esc_attr( $wppa_key ); ?>]"
														value="<?php echo esc_attr( $wppa_value ); ?>"
														data-wppa-var="<?php echo esc_attr( $wppa_var ); ?>"
														data-wppa-fallback="<?php echo esc_attr( $wppa_fallback ); ?>"
													/>
												<?php endif; ?>
												<code><?php echo esc_html( $wppa_var ); ?></code>
											</div>
											<span class="wppa-theme-hint"><?php echo esc_html( $wppa_color['hint'] ); ?></span>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppa-heading-text"><?php esc_html_e( 'Заголовок кабинета', 'wp-personal-account' ); ?></label></th>
						<td>
							<p style="margin:0 0 8px">
								<label>
									<input type="checkbox" name="wppa_settings[heading_show]" value="1" <?php checked( 1, (int) $wppa_heading['heading_show'] ); ?> />
									<?php esc_html_e( 'Показывать заголовок над кабинетом', 'wp-personal-account' ); ?>
								</label>
							</p>
							<input
								type="text"
								class="regular-text"
								id="wppa-heading-text"
								name="wppa_settings[heading_text]"
								value="<?php echo esc_attr( $wppa_heading['heading_text'] ); ?>"
								placeholder="<?php echo esc_attr( WPPA_Theme::heading_defaults()['heading_text'] ); ?>"
								data-wppa-heading-text="1"
							/>
							<span class="wppa-theme-hint"><?php esc_html_e( 'Пустой текст — заголовок не выводится (останется только заголовок страницы из темы).', 'wp-personal-account' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Стиль надписи', 'wp-personal-account' ); ?></th>
						<td>
							<div class="wppa-theme-grid">
								<div>
									<label for="wppa-heading-tag"><strong><?php esc_html_e( 'Тег', 'wp-personal-account' ); ?></strong></label>
									<div class="wppa-theme-field">
										<select id="wppa-heading-tag" name="wppa_settings[heading_tag]" class="wppa-theme-var" data-wppa-var="--wppa-heading-tag">
											<?php foreach ( WPPA_Theme::heading_choices()['heading_tag'] as $wppa_tag ) : ?>
												<option value="<?php echo esc_attr( $wppa_tag ); ?>" <?php selected( $wppa_heading['tag'], $wppa_tag ); ?>><?php echo esc_html( $wppa_tag ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<span class="wppa-theme-hint"><?php esc_html_e( 'Для SEO обычно h1; если на странице уже есть h1 — выберите h2.', 'wp-personal-account' ); ?></span>
								</div>
								<div>
									<label for="wppa-heading-size"><strong><?php esc_html_e( 'Размер', 'wp-personal-account' ); ?>, px</strong></label>
									<div class="wppa-theme-field">
										<input type="number" min="12" max="72" step="1" id="wppa-heading-size" name="wppa_settings[heading_size]" value="<?php echo esc_attr( $wppa_heading['size'] ); ?>" class="small-text wppa-theme-var" data-wppa-var="--wppa-heading-size" data-wppa-unit="px" />
									</div>
								</div>
								<div>
									<label for="wppa-heading-weight"><strong><?php esc_html_e( 'Насыщенность', 'wp-personal-account' ); ?></strong></label>
									<div class="wppa-theme-field">
										<select id="wppa-heading-weight" name="wppa_settings[heading_weight]" class="wppa-theme-var" data-wppa-var="--wppa-heading-weight">
											<?php foreach ( WPPA_Theme::heading_choices()['heading_weight'] as $wppa_weight ) : ?>
												<option value="<?php echo esc_attr( $wppa_weight ); ?>" <?php selected( (string) $wppa_heading['weight'], (string) $wppa_weight ); ?>><?php echo esc_html( $wppa_weight ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								</div>
								<div>
									<label for="wppa-heading-case"><strong><?php esc_html_e( 'Регистр', 'wp-personal-account' ); ?></strong></label>
									<div class="wppa-theme-field">
										<select id="wppa-heading-case" name="wppa_settings[heading_case]" class="wppa-theme-var" data-wppa-var="--wppa-heading-case">
											<?php
											$wppa_cases = array(
												'none'       => __( 'Как есть', 'wp-personal-account' ),
												'uppercase'  => __( 'ВСЕ ЗАГЛАВНЫЕ', 'wp-personal-account' ),
												'capitalize' => __( 'С Заглавной', 'wp-personal-account' ),
												'lowercase'  => __( 'все строчные', 'wp-personal-account' ),
											);
											foreach ( $wppa_cases as $wppa_case_value => $wppa_case_label ) :
												?>
												<option value="<?php echo esc_attr( $wppa_case_value ); ?>" <?php selected( $wppa_heading['case'], $wppa_case_value ); ?>><?php echo esc_html( $wppa_case_label ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								</div>
								<div>
									<label for="wppa-heading-align"><strong><?php esc_html_e( 'Выравнивание', 'wp-personal-account' ); ?></strong></label>
									<div class="wppa-theme-field">
										<select id="wppa-heading-align" name="wppa_settings[heading_align]" class="wppa-theme-var" data-wppa-var="--wppa-heading-align">
											<?php
											$wppa_aligns = array(
												'left'   => __( 'По левому краю', 'wp-personal-account' ),
												'center' => __( 'По центру', 'wp-personal-account' ),
												'right'  => __( 'По правому краю', 'wp-personal-account' ),
											);
											foreach ( $wppa_aligns as $wppa_align_value => $wppa_align_label ) :
												?>
												<option value="<?php echo esc_attr( $wppa_align_value ); ?>" <?php selected( $wppa_heading['align'], $wppa_align_value ); ?>><?php echo esc_html( $wppa_align_label ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								</div>
								<div>
									<label for="wppa-heading-color"><strong><?php esc_html_e( 'Цвет надписи', 'wp-personal-account' ); ?></strong></label>
									<div class="wppa-theme-field">
										<input
											type="text"
											class="regular-text code wppa-theme-input"
											id="wppa-heading-color"
											name="wppa_settings[heading_color]"
											value="<?php echo esc_attr( $wppa_heading['heading_color'] ); ?>"
											placeholder="<?php echo esc_attr( $wppa_vars['--wppa-text'] ); ?>"
											data-wppa-var="--wppa-heading-color"
											data-wppa-fallback="<?php echo esc_attr( $wppa_vars['--wppa-text'] ); ?>"
											style="width:130px"
										/>
									</div>
									<span class="wppa-theme-hint"><?php esc_html_e( 'Пусто — цвет основного текста.', 'wp-personal-account' ); ?></span>
								</div>
								<div>
									<label for="wppa-heading-gap"><strong><?php esc_html_e( 'Отступ снизу', 'wp-personal-account' ); ?>, px</strong></label>
									<div class="wppa-theme-field">
										<input type="number" min="0" max="64" step="2" id="wppa-heading-gap" name="wppa_settings[heading_gap]" value="<?php echo esc_attr( $wppa_heading['gap'] ); ?>" class="small-text wppa-theme-var" data-wppa-var="--wppa-heading-gap" data-wppa-unit="px" />
									</div>
								</div>
							</div>
							<p style="margin:12px 0 0">
								<label>
									<input type="checkbox" name="wppa_settings[hide_page_title]" value="1" <?php checked( 1, (int) $wppa_heading['hide_page_title'] ); ?> />
									<?php esc_html_e( 'Скрыть заголовок страницы, который выводит тема (только на странице кабинета)', 'wp-personal-account' ); ?>
								</label>
							</p>
							<span class="wppa-theme-hint"><?php esc_html_e( 'Стиль применяется и к заголовкам внутри кабинета (форм входа, профиля, команды, файлов). Заголовок темы скрывается только при выбранной «Странице кабинета» в разделе «Основное».', 'wp-personal-account' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppa-theme-radius"><?php esc_html_e( 'Скругление карточек', 'wp-personal-account' ); ?></label></th>
						<td>
							<div class="wppa-theme-preview__range">
								<input
									type="range"
									id="wppa-theme-radius"
									name="wppa_settings[radius]"
									min="<?php echo esc_attr( WPPA_Theme::RADIUS_MIN ); ?>"
									max="<?php echo esc_attr( WPPA_Theme::RADIUS_MAX ); ?>"
									step="2"
									value="<?php echo esc_attr( $wppa_theme['radius'] ); ?>"
									data-wppa-var="--wppa-radius"
								/>
								<output for="wppa-theme-radius"><code><?php echo esc_html( $wppa_theme['radius'] . 'px' ); ?></code></output>
							</div>
							<span class="wppa-theme-hint"><?php esc_html_e( 'От 0 (строгие прямоугольники) до 32 px. Половина значения уходит на кнопки и вкладки.', 'wp-personal-account' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Предпросмотр', 'wp-personal-account' ); ?></th>
						<td>
							<div class="wppa-theme-preview" id="wppa-theme-preview" style="<?php echo esc_attr( $wppa_preview ); ?>">
								<p class="wppa-theme-preview__heading" id="wppa-theme-preview-heading"><?php echo esc_html( '' !== $wppa_heading['text'] ? $wppa_heading['text'] : WPPA_Theme::heading_defaults()['heading_text'] ); ?></p>
								<div class="wppa-theme-preview__card">
									<p class="wppa-theme-preview__title"><?php esc_html_e( 'Моя команда', 'wp-personal-account' ); ?></p>
									<p class="wppa-theme-preview__muted"><?php esc_html_e( 'Так выглядят подсказки и второстепенный текст.', 'wp-personal-account' ); ?></p>
									<div class="wppa-theme-preview__tabs">
										<span class="wppa-theme-preview__tab is-active"><?php esc_html_e( 'Обзор', 'wp-personal-account' ); ?></span>
										<span class="wppa-theme-preview__tab"><?php esc_html_e( 'Профиль', 'wp-personal-account' ); ?></span>
										<span class="wppa-theme-preview__tab"><?php esc_html_e( 'Файлы', 'wp-personal-account' ); ?></span>
									</div>
									<button type="button" class="wppa-theme-preview__btn"><?php esc_html_e( 'Сохранить', 'wp-personal-account' ); ?></button>
								</div>
							</div>
							<span class="wppa-theme-hint"><?php esc_html_e( 'Предпросмотр обновляется, как только вы меняете цвет или скругление — до сохранения.', 'wp-personal-account' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppa-css"><?php esc_html_e( 'Дополнительный CSS', 'wp-personal-account' ); ?></label></th>
						<td>
							<textarea class="large-text code" rows="8" id="wppa-css" name="wppa_settings[custom_css]"><?php echo esc_textarea( $s['custom_css'] ); ?></textarea>
							<span class="wppa-theme-hint"><?php esc_html_e( 'Печатается после переменных: правила отсюда перекрывают стили плагина. Теги вырезаются, остаётся только CSS.', 'wp-personal-account' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Сброс', 'wp-personal-account' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wppa_settings[theme_reset]" value="1" />
								<?php esc_html_e( 'Вернуть оформление по умолчанию (цвета, скругление и свой CSS)', 'wp-personal-account' ); ?>
							</label>
							<span class="wppa-theme-hint">
								<?php
								if ( WPPA_Theme::is_default() ) {
									esc_html_e( 'Сейчас используется оформление по умолчанию.', 'wp-personal-account' );
								} else {
									esc_html_e( 'Сейчас заданы свои цвета: галочка и «Сохранить» вернут исходные значения.', 'wp-personal-account' );
								}
								?>
							</span>
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
