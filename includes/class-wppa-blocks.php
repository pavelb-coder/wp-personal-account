<?php
/**
 * Блок Gutenberg «Вход и регистрация» для блочных тем (FSE).
 *
 * Регистрируется динамически: серверный рендер, отдельного save() нет.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Blocks
 */
class WPPA_Blocks {

	/**
	 * Имя блока.
	 */
	const NAME = 'wppa/auth';

	/**
	 * Хуки.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Регистрация блока.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$path = WPPA_PATH . 'blocks/auth';

		// Скрипт редактора регистрируем вручную: нужны зависимости wp-* без сборки.
		wp_register_script(
			'wppa-block-auth-editor',
			WPPA_URL . 'blocks/auth/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			WPPA_VERSION,
			true
		);

		if ( file_exists( $path . '/block.json' ) ) {
			register_block_type(
				$path,
				array(
					'render_callback' => array( __CLASS__, 'render' ),
					'editor_script'   => 'wppa-block-auth-editor',
				)
			);
			return;
		}

		// Запасной путь, если block.json недоступен.
		register_block_type(
			self::NAME,
			array(
				'api_version'     => 3,
				'title'           => __( 'Вход и регистрация (личный кабинет)', 'wp-personal-account' ),
				'category'        => 'widgets',
				'icon'            => 'lock',
				'editor_script'   => 'wppa-block-auth-editor',
				'render_callback' => array( __CLASS__, 'render' ),
				'attributes'      => array(
					'mode'           => array( 'type' => 'string', 'default' => 'both' ),
					'redirect'       => array( 'type' => 'string', 'default' => '' ),
					'hideForMembers' => array( 'type' => 'boolean', 'default' => true ),
					'showTitle'      => array( 'type' => 'boolean', 'default' => false ),
					'title'          => array( 'type' => 'string', 'default' => 'Личный кабинет' ),
				),
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
				'redirect'       => '',
				'hideForMembers' => true,
				'showTitle'      => false,
				'title'          => __( 'Личный кабинет', 'wp-personal-account' ),
			)
		);

		$hide = isset( $attributes['hideForMembers'] ) ? (bool) $attributes['hideForMembers'] : true;

		if ( is_user_logged_in() && $hide ) {
			return '';
		}

		// Стили блока нужны и внутри пассивных шаблонов Site Editor.
		if ( ! wp_style_is( 'wppa-account', 'enqueued' ) ) {
			wp_enqueue_style( 'wppa-account', WPPA_URL . 'assets/css/account.css', array(), WPPA_VERSION );
			$accent = sanitize_hex_color( (string) WPPA_Plugin::setting( 'accent_color', '#2563eb' ) );
			wp_add_inline_style( 'wppa-account', ':root{--wppa-accent:' . ( $accent ? $accent : '#2563eb' ) . ';--wppa-accent-contrast:#ffffff;}' );
		}

		ob_start();
		?>
		<div class="wp-block-wppa-auth">
			<?php if ( ! empty( $attributes['showTitle'] ) && '' !== trim( (string) $attributes['title'] ) ) : ?>
				<h2 class="wppa-title"><?php echo esc_html( $attributes['title'] ); ?></h2>
			<?php endif; ?>
			<?php
			WPPA_Auth_Form::render(
				array(
					'mode'             => isset( $attributes['mode'] ) ? sanitize_key( $attributes['mode'] ) : 'both',
					'redirect'         => isset( $attributes['redirect'] ) ? esc_url_raw( $attributes['redirect'] ) : '',
					'hide_for_members' => $hide ? 1 : 0,
				)
			);
			?>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
