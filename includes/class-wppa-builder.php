<?php
/**
 * Конструктор личного кабинета: реестр блоков, раскладка вкладок, рендер.
 *
 * Раскладка хранится в опции wppa_layout:
 * array(
 *   'dashboard' => array(
 *     array( 'type' => 'block', 'id' => 'greeting', 'enabled' => 1, 'title' => '', 'audience' => 'all' ),
 *     array( 'type' => 'custom', 'id' => 'custom-1', 'enabled' => 1, 'title' => 'Инструкция', 'html' => '...', 'audience' => 'all' ),
 *   ),
 *   ...
 * )
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Builder
 */
class WPPA_Builder {

	/**
	 * Опция с раскладкой.
	 */
	const OPTION = 'wppa_layout';

	/**
	 * Хуки.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Регистрация опции.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'wppa_layout_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	/**
	 * Скрипт конструктора.
	 *
	 * @param string $hook Хук страницы.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'wppa-builder' ) ) {
			return;
		}

		wp_enqueue_script(
			'wppa-admin-builder',
			WPPA_URL . 'assets/js/admin-builder.js',
			array(),
			WPPA_VERSION,
			true
		);

		wp_localize_script(
			'wppa-admin-builder',
			'wppaBuilder',
			array(
				'confirmRemove' => __( 'Убрать этот блок из вкладки?', 'wp-personal-account' ),
			)
		);
	}

	/**
	 * Страница «Конструктор» в админке.
	 *
	 * @return void
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		WPPA_Admin::render(
			'builder',
			array(
				'notice' => WPPA_Admin::tool_notice(),
			)
		);
	}

	/**
	 * Реестр доступных блоков.
	 *
	 * @return array
	 */
	public static function blocks() {
		$blocks = array(
			'greeting'        => array(
				'label' => __( 'Приветствие', 'wp-personal-account' ),
				'tabs'  => array( 'dashboard' ),
				'desc'  => __( 'Имя пользователя и заголовок вкладки', 'wp-personal-account' ),
			),
			'profile-summary' => array(
				'label' => __( 'Сводка профиля', 'wp-personal-account' ),
				'tabs'  => array( 'dashboard' ),
				'desc'  => __( 'Дата регистрации, email, команда', 'wp-personal-account' ),
			),
			'profile-form'    => array(
				'label' => __( 'Форма профиля', 'wp-personal-account' ),
				'tabs'  => array( 'profile' ),
				'desc'  => __( 'Имя, фамилия, отображаемое имя, «о себе»', 'wp-personal-account' ),
			),
			'team-widget'     => array(
				'label' => __( 'Команда', 'wp-personal-account' ),
				'tabs'  => array( 'team' ),
				'desc'  => __( 'Состав команды, приглашения, управление', 'wp-personal-account' ),
			),
			'password-form'   => array(
				'label' => __( 'Смена пароля', 'wp-personal-account' ),
				'tabs'  => array( 'security' ),
				'desc'  => __( 'Текущий и новый пароль', 'wp-personal-account' ),
			),
			'email-form'      => array(
				'label' => __( 'Смена email', 'wp-personal-account' ),
				'tabs'  => array( 'security' ),
				'desc'  => __( 'Запрос нового адреса с подтверждением по почте', 'wp-personal-account' ),
			),
			'link-button'     => array(
				'label'        => __( 'Кнопка со ссылкой', 'wp-personal-account' ),
				'tabs'         => array( 'dashboard', 'profile', 'team', 'security' ),
				'default_tabs' => array( 'dashboard' ),
				'desc'         => __( 'Внешняя ссылка с таймером и условием по составу команды', 'wp-personal-account' ),
			),
			'session-card'    => array(
				'label' => __( 'Сессия и выход', 'wp-personal-account' ),
				'tabs'  => array( 'security' ),
				'desc'  => __( 'Кнопка выхода из аккаунта', 'wp-personal-account' ),
			),
		);

		/**
		 * Фильтр реестра блоков конструктора.
		 *
		 * @param array $blocks Блоки.
		 */
		return apply_filters( 'wppa_blocks', $blocks );
	}

	/**
	 * Раскладка по умолчанию.
	 *
	 * @return array
	 */
	public static function defaults() {
		$layout = array();

		foreach ( array_keys( WPPA_Template::all_tabs() ) as $tab ) {
			$layout[ $tab ] = array();
		}

		foreach ( self::blocks() as $id => $block ) {
			$default_tabs = isset( $block['default_tabs'] ) ? (array) $block['default_tabs'] : (array) $block['tabs'];

			foreach ( $default_tabs as $tab ) {
				if ( ! isset( $layout[ $tab ] ) ) {
					$layout[ $tab ] = array();
				}

				$layout[ $tab ][] = array(
					'type'     => 'block',
					'id'       => $id,
					'enabled'  => 1,
					'title'    => '',
					'audience' => 'all',
					'html'     => '',
				);
			}
		}

		return $layout;
	}

	/**
	 * Текущая раскладка (сохранённая или по умолчанию).
	 *
	 * @return array
	 */
	public static function layout() {
		$saved = get_option( self::OPTION, null );
		$out   = array();

		foreach ( array_keys( WPPA_Template::all_tabs() ) as $tab ) {
			if ( is_array( $saved ) && array_key_exists( $tab, $saved ) ) {
				$out[ $tab ] = (array) $saved[ $tab ];
				continue;
			}

			$defaults    = self::defaults();
			$out[ $tab ] = isset( $defaults[ $tab ] ) ? $defaults[ $tab ] : array();
		}

		return $out;
	}

	/**
	 * Очистка раскладки при сохранении.
	 *
	 * @param mixed $input Значение из формы.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$registry = self::blocks();
		$out      = array();

		foreach ( array_keys( WPPA_Template::all_tabs() ) as $tab ) {
			$out[ $tab ] = array();
		}

		if ( ! is_array( $input ) ) {
			return $out;
		}

		foreach ( $input as $tab => $items ) {
			$tab = sanitize_key( $tab );

			if ( ! array_key_exists( $tab, $out ) || ! is_array( $items ) ) {
				continue;
			}

			$count = 0;

			foreach ( $items as $item ) {
				if ( ++$count > 50 || ! is_array( $item ) ) {
					break;
				}

				$type = isset( $item['type'] ) ? sanitize_key( $item['type'] ) : 'block';
				$id   = isset( $item['id'] ) ? sanitize_key( $item['id'] ) : '';

				if ( 'block' === $type ) {
					if ( ! isset( $registry[ $id ] ) || ! in_array( $tab, (array) $registry[ $id ]['tabs'], true ) ) {
						continue;
					}
				} elseif ( 'custom' !== $type ) {
					continue;
				}

				$audience = isset( $item['audience'] ) ? sanitize_key( $item['audience'] ) : 'all';

				$out[ $tab ][] = array(
					'type'     => $type,
					'id'       => $id,
					'enabled'  => empty( $item['enabled'] ) ? 0 : 1,
					'title'    => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
					'audience' => in_array( $audience, array( 'all', 'admins' ), true ) ? $audience : 'all',
					'html'     => isset( $item['html'] ) ? wp_kses_post( $item['html'] ) : '',
				);
			}
		}

		return $out;
	}

	/**
	 * Однократно добавить блок в раскладку существующей установки.
	 *
	 * @param string $block_id ID блока.
	 * @param string $tab      Вкладка.
	 * @return bool true — блок добавлен.
	 */
	public static function seed_block( $block_id, $tab = 'dashboard' ) {
		$saved = get_option( self::OPTION, null );

		if ( ! is_array( $saved ) ) {
			return false;
		}

		foreach ( $saved as $items ) {
			foreach ( (array) $items as $item ) {
				if ( isset( $item['id'] ) && $block_id === $item['id'] ) {
					return false;
				}
			}
		}

		if ( ! array_key_exists( $tab, $saved ) ) {
			$saved[ $tab ] = array();
		}

		array_unshift(
			$saved[ $tab ],
			array(
				'type'     => 'block',
				'id'       => $block_id,
				'enabled'  => 1,
				'title'    => '',
				'audience' => 'all',
				'html'     => '',
			)
		);

		update_option( self::OPTION, $saved );

		return true;
	}

	/**
	 * Удалить блок из сохранённой раскладки во всех вкладках.
	 *
	 * @param string $block_id ID блока.
	 * @return bool true — раскладка изменилась.
	 */
	public static function drop_block( $block_id ) {
		$saved = get_option( self::OPTION, null );

		if ( ! is_array( $saved ) ) {
			return false;
		}

		$changed = false;

		foreach ( $saved as $tab => $items ) {
			$kept = array();

			foreach ( (array) $items as $item ) {
				if ( isset( $item['id'] ) && $block_id === $item['id'] ) {
					$changed = true;
					continue;
				}

				$kept[] = $item;
			}

			$saved[ $tab ] = $kept;
		}

		if ( $changed ) {
			update_option( self::OPTION, $saved );
		}

		return $changed;
	}

	/**
	 * Сброс раскладки к стандартной.
	 *
	 * @return void
	 */
	public static function reset() {
		delete_option( self::OPTION );
	}

	/* ---------------------------------------------------------------------
	 * Рендер
	 * ------------------------------------------------------------------ */

	/**
	 * Рендер всех блоков вкладки.
	 *
	 * @param string $tab  Слаг вкладки.
	 * @param array  $args Данные (user, tabs).
	 * @return void
	 */
	public static function render_tab( $tab, $args = array() ) {
		$layout = self::layout();
		$items  = isset( $layout[ $tab ] ) ? (array) $layout[ $tab ] : array();
		$shown  = 0;

		foreach ( $items as $item ) {
			if ( empty( $item['enabled'] ) ) {
				continue;
			}

			$audience = isset( $item['audience'] ) ? $item['audience'] : 'all';

			if ( 'admins' === $audience && ! current_user_can( 'manage_options' ) ) {
				continue;
			}

			$html = self::render_block( $item, $args );

			if ( '' === trim( $html ) ) {
				continue;
			}

			++$shown;

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- разметка блоков уже экранирована в шаблонах.
			echo $html;
		}

		if ( 0 === $shown && current_user_can( 'manage_options' ) ) {
			WPPA_Template::render(
				'notice',
				array(
					'type' => 'info',
					'text' => __( 'В этой вкладке выключены все блоки. Включите их в разделе «Конструктор».', 'wp-personal-account' ),
				)
			);
		}
	}

	/**
	 * Рендер одного блока.
	 *
	 * @param array $item Элемент раскладки.
	 * @param array $args Данные.
	 * @return string
	 */
	public static function render_block( $item, $args = array() ) {
		$title = isset( $item['title'] ) ? (string) $item['title'] : '';

		if ( 'custom' === $item['type'] ) {
			$html = isset( $item['html'] ) ? (string) $item['html'] : '';

			if ( '' === trim( $html ) ) {
				return '';
			}

			$out = '<section class="wppa-card wppa-custom-block">';

			if ( '' !== $title ) {
				$out .= '<h3 class="wppa-subtitle">' . esc_html( $title ) . '</h3>';
			}

			$out .= '<div class="wppa-custom-block__content">' . wp_kses_post( $html ) . '</div></section>';

			return $out;
		}

		$id   = isset( $item['id'] ) ? $item['id'] : '';
		$file = self::locate( $id );

		if ( ! $file ) {
			return '';
		}

		$args['block_title'] = $title;
		$args['block_id']    = $id;

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- контролируемый набор переменных шаблона.
		extract( $args, EXTR_SKIP );

		ob_start();
		include $file;

		return (string) ob_get_clean();
	}

	/**
	 * Путь к шаблону блока (с переопределением в теме).
	 *
	 * @param string $id ID блока.
	 * @return string
	 */
	public static function locate( $id ) {
		$id = preg_replace( '/[^a-z0-9\-]/i', '', (string) $id );

		if ( '' === $id ) {
			return '';
		}

		$theme = locate_template( array( 'wp-personal-account/blocks/' . $id . '.php' ) );

		if ( $theme ) {
			return $theme;
		}

		$file = WPPA_PATH . 'templates/blocks/' . $id . '.php';

		return file_exists( $file ) ? $file : '';
	}
}
