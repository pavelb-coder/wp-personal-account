<?php
/**
 * Виджет «Вход и регистрация» для главной страницы и любых областей виджетов.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Auth_Widget
 */
class WPPA_Auth_Widget extends WP_Widget {

	/**
	 * Конструктор.
	 */
	public function __construct() {
		parent::__construct(
			'wppa_auth',
			__( 'Личный кабинет: вход и регистрация', 'wp-personal-account' ),
			array(
				'description'                 => __( 'Компактная форма входа и регистрации для главной страницы или сайдбара.', 'wp-personal-account' ),
				'classname'                   => 'wppa-auth-widget',
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Регистрация виджета.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! class_exists( 'WP_Widget' ) ) {
			return;
		}

		register_widget( __CLASS__ );
	}

	/**
	 * Значения по умолчанию.
	 *
	 * @return array
	 */
	private function defaults() {
		return array(
			'title'            => __( 'Личный кабинет', 'wp-personal-account' ),
			'mode'             => 'both',
			'redirect'         => '',
			'hide_for_members' => 1,
			'hide_other'       => 0,
		);
	}

	/**
	 * Вывод на сайте.
	 *
	 * @param array $args     Аргументы области виджетов.
	 * @param array $instance Настройки виджета.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->defaults() );
		$instance = self::normalize( $instance );

		if ( is_user_logged_in() && ! empty( $instance['hide_for_members'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- разметка области виджетов.
		echo isset( $args['before_widget'] ) ? $args['before_widget'] : '';

		if ( '' !== $instance['title'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo ( isset( $args['before_title'] ) ? $args['before_title'] : '' ) . esc_html( $instance['title'] ) . ( isset( $args['after_title'] ) ? $args['after_title'] : '' );
		}

		WPPA_Auth_Form::render( $instance );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo isset( $args['after_widget'] ) ? $args['after_widget'] : '';
	}

	/**
	 * Форма настроек в админке.
	 *
	 * @param array $instance Текущие настройки.
	 * @return void
	 */
	public function form( $instance ) {
		$instance = self::normalize( wp_parse_args( (array) $instance, $this->defaults() ) );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Заголовок', 'wp-personal-account' ); ?></label>
			<input
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				type="text"
				value="<?php echo esc_attr( $instance['title'] ); ?>"
			/>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>"><?php esc_html_e( 'Что показывать', 'wp-personal-account' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'mode' ) ); ?>">
				<option value="both" <?php selected( $instance['mode'], 'both' ); ?>><?php esc_html_e( 'Вход и регистрация', 'wp-personal-account' ); ?></option>
				<option value="login" <?php selected( $instance['mode'], 'login' ); ?>><?php esc_html_e( 'Только вход', 'wp-personal-account' ); ?></option>
				<option value="register" <?php selected( $instance['mode'], 'register' ); ?>><?php esc_html_e( 'Только регистрация', 'wp-personal-account' ); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'redirect' ) ); ?>"><?php esc_html_e( 'Редирект после входа', 'wp-personal-account' ); ?></label>
			<input
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'redirect' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'redirect' ) ); ?>"
				type="url"
				placeholder="<?php echo esc_attr( WPPA_Plugin::account_url() ); ?>"
				value="<?php echo esc_attr( $instance['redirect'] ); ?>"
			/>
		</p>

		<p>
			<input
				type="checkbox"
				class="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'hide_for_members' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'hide_for_members' ) ); ?>"
				value="1"
				<?php checked( 1, (int) $instance['hide_for_members'] ); ?>
			/>
			<label for="<?php echo esc_attr( $this->get_field_id( 'hide_for_members' ) ); ?>"><?php esc_html_e( 'Скрывать от авторизованных', 'wp-personal-account' ); ?></label>
		</p>
		<?php
	}

	/**
	 * Сохранение настроек.
	 *
	 * @param array $new_instance Новые значения.
	 * @param array $old_instance Старые значения.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return self::normalize( wp_parse_args( (array) $new_instance, $this->defaults() ) );
	}

	/**
	 * Нормализация настроек.
	 *
	 * @param array $instance Значения.
	 * @return array
	 */
	public static function normalize( $instance ) {
		$defaults = array(
			'title'            => __( 'Личный кабинет', 'wp-personal-account' ),
			'mode'             => 'both',
			'redirect'         => '',
			'hide_for_members' => 1,
			'hide_other'       => 0,
		);

		$mode = isset( $instance['mode'] ) ? sanitize_key( $instance['mode'] ) : 'both';

		return array(
			'title'            => isset( $instance['title'] ) ? sanitize_text_field( $instance['title'] ) : $defaults['title'],
			'mode'             => in_array( $mode, array( 'both', 'login', 'register' ), true ) ? $mode : 'both',
			'redirect'         => isset( $instance['redirect'] ) ? esc_url_raw( $instance['redirect'] ) : '',
			'hide_for_members' => empty( $instance['hide_for_members'] ) ? 0 : 1,
		);
	}
}

/**
 * Общий рендер форм входа/регистрации — используется виджетом и шорткодом.
 *
 * @package WP_Personal_Account
 */
class WPPA_Auth_Form {

	/**
	 * Параметры по умолчанию.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'title'            => '',
			'mode'             => 'both',
			'redirect'         => '',
			'hide_for_members' => 1,
			'hide_other'       => 0,
		);
	}

	/**
	 * Вывод формы.
	 *
	 * @param array $args Параметры.
	 * @return void
	 */
	public static function render( $args = array() ) {
		$args = wp_parse_args( $args, self::defaults() );

		$registration = WPPA_Plugin::registration_open();
		$mode         = in_array( $args['mode'], array( 'both', 'login', 'register' ), true ) ? $args['mode'] : 'both';
		$requested    = WPPA_Plugin::requested_view();
		$active       = 'login';

		// Переход по кнопке «Регистрация» (?wppa_view=register) должен переключать форму,
		// даже если виджет/шорткод настроен только на вход.
		if ( 'register' === $requested ) {
			$mode   = 'both' === $mode ? 'both' : 'register';
			$active = 'register';
		} elseif ( 'login' === $requested ) {
			$mode   = 'both' === $mode ? 'both' : 'login';
			$active = 'login';
		}

		if ( 'register' === $mode && ! $registration ) {
			$mode   = 'login';
			$active = 'login';
		}

		if ( is_user_logged_in() ) {
			WPPA_Template::render(
				'logged-in',
				array(
					'message' => __( 'Вы уже авторизованы.', 'wp-personal-account' ),
				)
			);
			return;
		}

		WPPA_Template::render(
			'auth-widget',
			array(
				'mode'         => $mode,
				'registration' => $registration,
				'redirect'     => isset( $args['redirect'] ) ? $args['redirect'] : '',
				'active'       => $active,
				'hide_other'   => ! empty( $args['hide_other'] ),
			)
		);
	}
}
