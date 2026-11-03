<?php
/**
 * Блоки Gutenberg личного кабинета для блочных тем (FSE).
 *
 * Блоки:
 * - wppa/auth     — вход и регистрация одним блоком;
 * - wppa/login    — только вход;
 * - wppa/register — только регистрация.
 *
 * Каждый блок выводит либо форму, либо кнопку-ссылку (атрибут display).
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Blocks
 */
class WPPA_Blocks {

	/**
	 * Имя составного блока.
	 */
	const NAME = 'wppa/auth';

	/**
	 * Реестр блоков: каталог => параметры.
	 *
	 * @return array
	 */
	public static function blocks() {
		return array(
			'auth'     => array(
				'name'  => 'wppa/auth',
				'mode'  => 'both',
				'title' => __( 'Вход и регистрация (личный кабинет)', 'wp-personal-account' ),
			),
			'login'    => array(
				'name'  => 'wppa/login',
				'mode'  => 'login',
				'title' => __( 'Вход (личный кабинет)', 'wp-personal-account' ),
			),
			'register' => array(
				'name'  => 'wppa/register',
				'mode'  => 'register',
				'title' => __( 'Регистрация (личный кабинет)', 'wp-personal-account' ),
			),
		);
	}

	/**
	 * Хуки.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Регистрация блоков.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		foreach ( self::blocks() as $dir => $block ) {
			self::register_block( $dir, $block );
		}
	}

	/**
	 * Регистрация одного блока.
	 *
	 * @param string $dir   Каталог блока.
	 * @param array  $block Параметры.
	 * @return void
	 */
	private static function register_block( $dir, $block ) {
		$handle = 'wppa-block-' . $dir . '-editor';
		$path   = WPPA_PATH . 'blocks/' . $dir;

		// Скрипт редактора регистрируем вручную: нужны зависимости wp-* без сборки.
		wp_register_script(
			$handle,
			WPPA_URL . 'blocks/' . $dir . '/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			WPPA_VERSION,
			true
		);

		$args = array(
			'render_callback' => array( __CLASS__, 'render' ),
			'editor_script'   => $handle,
		);

		if ( file_exists( $path . '/block.json' ) ) {
			register_block_type( $path, $args );
			return;
		}

		// Запасной путь, если block.json недоступен.
		register_block_type(
			$block['name'],
			array_merge(
				$args,
				array(
					'api_version' => 3,
					'title'       => $block['title'],
					'category'    => 'widgets',
					'icon'        => 'login' === $block['mode'] ? 'admin-users' : ( 'register' === $block['mode'] ? 'admin-users' : 'lock' ),
					'attributes'  => array(
						'mode'           => array( 'type' => 'string', 'default' => $block['mode'] ),
						'display'        => array( 'type' => 'string', 'default' => 'form' ),
						'buttonText'     => array( 'type' => 'string', 'default' => '' ),
						'redirect'       => array( 'type' => 'string', 'default' => '' ),
						'hideForMembers' => array( 'type' => 'boolean', 'default' => true ),
						'showTitle'      => array( 'type' => 'boolean', 'default' => false ),
						'title'          => array( 'type' => 'string', 'default' => '' ),
						'hideOther'      => array( 'type' => 'boolean', 'default' => false ),
					),
				)
			)
		);
	}

	/**
	 * Серверный рендер блока.
	 *
	 * @param array $attributes Атрибуты блока.
	 * @return string
	 */
	public static function render( $attributes = array() ) {
		$attributes = wp_parse_args(
			(array) $attributes,
			array(
				'mode'           => 'both',
				'display'        => 'form',
				'buttonText'     => '',
				'redirect'       => '',
				'hideForMembers' => true,
				'showTitle'      => false,
				'title'          => '',
				'hideOther'      => false,
			)
		);

		$mode = in_array( $attributes['mode'], array( 'both', 'login', 'register' ), true ) ? $attributes['mode'] : 'both';
		$hide = isset( $attributes['hideForMembers'] ) ? (bool) $attributes['hideForMembers'] : true;

		if ( is_user_logged_in() && $hide ) {
			return '';
		}

		// Стили блока нужны и внутри пассивных шаблонов Site Editor.
		self::enqueue_styles();

		$title = isset( $attributes['title'] ) ? (string) $attributes['title'] : '';

		if ( '' === trim( $title ) ) {
			$title = 'register' === $mode ? __( 'Регистрация', 'wp-personal-account' ) : ( 'login' === $mode ? __( 'Вход', 'wp-personal-account' ) : __( 'Личный кабинет', 'wp-personal-account' ) );
		}

		// Вариант «кнопка-ссылка» — для шапки сайта и меню.
		if ( 'button' === $attributes['display'] && 'both' !== $mode ) {
			return self::render_button( $mode, $attributes );
		}

		ob_start();
		?>
		<div <?php echo self::wrapper_attributes( $mode, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- готовые атрибуты WordPress. ?>>
			<?php if ( ! empty( $attributes['showTitle'] ) ) : ?>
				<h2 class="wppa-title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<?php
			WPPA_Auth_Form::render(
				array(
					'mode'             => $mode,
					'redirect'         => isset( $attributes['redirect'] ) ? esc_url_raw( $attributes['redirect'] ) : '',
					'hide_for_members' => $hide ? 1 : 0,
					'hide_other'       => ! empty( $attributes['hideOther'] ),
				)
			);
			?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Вывод кнопки-ссылки на форму входа или регистрации.
	 *
	 * @param string $mode       login|register.
	 * @param array  $attributes Атрибуты блока.
	 * @return string
	 */
	private static function render_button( $mode, $attributes ) {
		if ( 'register' === $mode && ! WPPA_Plugin::registration_open() ) {
			return '';
		}

		$text = isset( $attributes['buttonText'] ) ? trim( (string) $attributes['buttonText'] ) : '';

		if ( '' === $text ) {
			$text = 'register' === $mode ? __( 'Регистрация', 'wp-personal-account' ) : __( 'Войти', 'wp-personal-account' );
		}

		$url   = 'register' === $mode ? WPPA_Plugin::register_url() : WPPA_Plugin::login_url();
		$style = isset( $attributes['buttonStyle'] ) && 'ghost' === $attributes['buttonStyle'] ? 'ghost' : 'primary';
		$window = 'register' === $mode ? WPPA_Plugin::registration_window() : array(
			'state'     => 'open',
			'message'   => '',
			'countdown' => 0,
		);

		$button_classes = array( 'wppa-button', 'wppa-button--' . $style );

		$size = isset( $attributes['buttonSize'] ) ? (string) $attributes['buttonSize'] : 'md';
		if ( in_array( $size, array( 'sm', 'lg' ), true ) ) {
			$button_classes[] = 'wppa-button--' . $size;
		}

		if ( ! empty( $attributes['buttonFullWidth'] ) ) {
			$button_classes[] = 'wppa-button--block';
		}

		$classes = esc_attr( implode( ' ', $button_classes ) );
		$wrapper = self::wrapper_attributes( $mode, $attributes );

		// Регистрация ограничена по времени: вне окна кнопка неактивна, показываем отсчёт.
		if ( 'register' === $mode && 'open' !== $window['state'] ) {
			$button_classes[] = 'wppa-button--disabled';

			$out = sprintf(
				'<div %1$s><span class="%2$s" role="link" aria-disabled="true" tabindex="-1">%3$s</span>',
				$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_attr( implode( ' ', $button_classes ) ),
				esc_html( $text )
			);

			if ( '' !== $window['message'] ) {
				$out .= '<p class="wppa-hint wppa-hint--block">' . esc_html( $window['message'] );

				if ( $window['countdown'] ) {
					$out .= ' ' . WPPA_Time::countdown_html( $window['countdown'], 'open', true );
				}

				$out .= '</p>';
			}

			return $out . '</div>';
		}

		return sprintf(
			'<div %1$s><a class="%2$s" href="%3$s">%4$s</a></div>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$classes,
			esc_url( $url ),
			esc_html( $text )
		);
	}

	/**
	 * Атрибуты обёртки блока: классы оформления, инлайн-переменные и
	 * настройки из supports (цвет, отступы, рамка, типографика).
	 *
	 * @param string $mode       both|login|register.
	 * @param array  $attributes Атрибуты блока.
	 * @return string
	 */
	private static function wrapper_attributes( $mode, $attributes ) {
		$classes = array( 'wp-block-wppa-' . ( 'both' === $mode ? 'auth' : $mode ) );

		$appearance = isset( $attributes['appearance'] ) ? (string) $attributes['appearance'] : 'plain';
		$classes[]  = 'wppa-block--' . ( in_array( $appearance, array( 'plain', 'card', 'bordered' ), true ) ? $appearance : 'plain' );

		$width = isset( $attributes['maxWidth'] ) ? (string) $attributes['maxWidth'] : 'auto';
		if ( in_array( $width, array( 'small', 'medium', 'large', 'full' ), true ) ) {
			$classes[] = 'wppa-block--w-' . $width;
		}

		$size = isset( $attributes['buttonSize'] ) ? (string) $attributes['buttonSize'] : 'md';
		if ( in_array( $size, array( 'sm', 'lg' ), true ) ) {
			$classes[] = 'wppa-block--btn-' . $size;
		}

		if ( ! empty( $attributes['buttonFullWidth'] ) ) {
			$classes[] = 'wppa-block--btn-full';
		}

		$styles = array();

		$accent = isset( $attributes['accentColor'] ) ? sanitize_hex_color( (string) $attributes['accentColor'] ) : '';
		if ( $accent ) {
			$styles[] = '--wppa-accent:' . $accent;
		}

		$radius = isset( $attributes['cornerRadius'] ) ? (int) $attributes['cornerRadius'] : 0;
		if ( $radius > 0 ) {
			$styles[] = '--wppa-radius:' . min( 40, $radius ) . 'px';
		}

		$extra = array( 'class' => implode( ' ', $classes ) );

		if ( $styles ) {
			$extra['style'] = implode( ';', $styles ) . ';';
		}

		if ( function_exists( 'get_block_wrapper_attributes' ) ) {
			return get_block_wrapper_attributes( $extra );
		}

		// Запасной вариант, если функция недоступна.
		$out = 'class="' . esc_attr( $extra['class'] ) . '"';

		if ( ! empty( $extra['style'] ) ) {
			$out .= ' style="' . esc_attr( $extra['style'] ) . '"';
		}

		return $out;
	}

	/**
	 * Подключение стилей кабинета.
	 *
	 * @return void
	 */
	private static function enqueue_styles() {
		if ( wp_style_is( 'wppa-account', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style( 'wppa-account', WPPA_URL . 'assets/css/account.css', array(), WPPA_VERSION );

		$accent = sanitize_hex_color( (string) WPPA_Plugin::setting( 'accent_color', '#2563eb' ) );
		wp_add_inline_style( 'wppa-account', ':root{--wppa-accent:' . ( $accent ? $accent : '#2563eb' ) . ';--wppa-accent-contrast:#ffffff;}' );
	}
}
