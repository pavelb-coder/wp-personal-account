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

		$wrapper = 'wp-block-wppa-' . ( 'both' === $mode ? 'auth' : $mode );

		// Вариант «кнопка-ссылка» — для шапки сайта и меню.
		if ( 'button' === $attributes['display'] && 'both' !== $mode ) {
			return self::render_button( $mode, $attributes, $wrapper );
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrapper ); ?>">
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
	 * @param string $wrapper    CSS-класс обёртки.
	 * @return string
	 */
	private static function render_button( $mode, $attributes, $wrapper ) {
		if ( 'register' === $mode && ! WPPA_Plugin::registration_open() ) {
			return '';
		}

		$text = isset( $attributes['buttonText'] ) ? trim( (string) $attributes['buttonText'] ) : '';

		if ( '' === $text ) {
			$text = 'register' === $mode ? __( 'Регистрация', 'wp-personal-account' ) : __( 'Войти', 'wp-personal-account' );
		}

		$url = 'register' === $mode ? WPPA_Plugin::register_url() : WPPA_Plugin::login_url();

		return sprintf(
			'<div class="%1$s"><a class="wppa-button wppa-button--primary" href="%2$s">%3$s</a></div>',
			esc_attr( $wrapper ),
			esc_url( $url ),
			esc_html( $text )
		);
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
