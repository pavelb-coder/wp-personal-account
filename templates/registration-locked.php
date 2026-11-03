<?php
/**
 * Заглушка формы регистрации: окно регистрации ещё не открыто или уже закрыто.
 *
 * Доступные переменные: $window (результат WPPA_Plugin::registration_window()), $title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_window = isset( $window ) && is_array( $window ) ? $window : WPPA_Plugin::registration_window();
$wppa_title  = isset( $title ) && '' !== $title ? $title : __( 'Регистрация', 'wp-personal-account' );
?>
<div class="wppa-form-wrap wppa-register-locked">
	<h2 class="wppa-title"><?php echo esc_html( $wppa_title ); ?></h2>

	<?php
	if ( 'before' === $wppa_window['state'] ) {
		WPPA_Template::render(
			'notice',
			array(
				'type' => 'info',
				'text' => sprintf(
					/* translators: %s: дата и время открытия */
					__( 'Форма регистрации откроется %s.', 'wp-personal-account' ),
					WPPA_Time::format_date( $wppa_window['start'] )
				),
			)
		);
	} else {
		WPPA_Template::render(
			'notice',
			array(
				'type' => 'info',
				'text' => sprintf(
					/* translators: %s: дата и время закрытия */
					__( 'Регистрация закончилась %s. Если нужен доступ — напишите администратору сайта.', 'wp-personal-account' ),
					WPPA_Time::format_date( $wppa_window['end'] )
				),
			)
		);
	}
	?>

	<?php if ( 'before' === $wppa_window['state'] && $wppa_window['countdown'] ) : ?>
		<p class="wppa-countdown-row">
			<span class="wppa-countdown-row__label"><?php esc_html_e( 'До открытия:', 'wp-personal-account' ); ?></span>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- разметка собирается с экранированием внутри.
			echo WPPA_Time::countdown_html( $wppa_window['countdown'], 'open', true );
			?>
		</p>
	<?php endif; ?>

	<p class="wppa-hint"><?php esc_html_e( 'Регистрация ограничена по времени: форма появится здесь автоматически, когда откроется.', 'wp-personal-account' ); ?></p>
</div>
