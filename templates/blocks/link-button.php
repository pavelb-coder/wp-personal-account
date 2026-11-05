<?php
/**
 * Блок «Кнопка со ссылкой»: внешняя ссылка с окном дат и условием по составу команды.
 *
 * Шаблон можно переопределить в теме: wp-personal-account/blocks/link-button.php
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_link = WPPA_Link_Button::state();

// Наставнику и куратору кнопка не выдаётся: блок не выводим совсем — ни открытым,
// ни серым с сообщением.
if ( in_array( $wppa_link['status'], array( 'mentor', 'curator' ), true ) ) {
	return;
}

$wppa_text = $wppa_link['text'];
?>
<section class="wppa-card wppa-link-block<?php echo 'ready' === $wppa_link['status'] ? '' : ' wppa-link-block--locked'; ?>">
	<?php if ( ! empty( $block_title ) ) : ?>
		<h3 class="wppa-subtitle"><?php echo esc_html( $block_title ); ?></h3>
	<?php endif; ?>

	<?php if ( $wppa_link['is_open'] ) : ?>
		<a
			class="wppa-button wppa-button--primary wppa-link-block__button"
			href="<?php echo esc_url( $wppa_link['url'] ); ?>"
			<?php echo $wppa_link['new_tab'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
		><?php echo esc_html( $wppa_text ); ?></a>
	<?php else : ?>
		<span
			class="wppa-button wppa-link-block__button wppa-link-block__button--disabled"
			role="link"
			aria-disabled="true"
			tabindex="-1"
		><?php echo esc_html( $wppa_text ); ?></span>
	<?php endif; ?>

	<?php if ( '' !== $wppa_link['message'] ) : ?>
		<p class="wppa-hint wppa-hint--block wppa-link-block__message">
			<?php echo esc_html( $wppa_link['message'] ); ?>
		</p>
	<?php endif; ?>

	<?php if ( '' !== $wppa_link['note'] ) : ?>
		<p class="wppa-link-block__note">
			<?php echo nl2br( esc_html( $wppa_link['note'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- текст экранирован, добавлены только переносы. ?>
		</p>
	<?php endif; ?>

	<?php if ( $wppa_link['countdown'] ) : ?>
		<p class="wppa-link-block__timer">
			<span class="wppa-link-block__timer-label">
				<?php
				echo esc_html(
					'start' === $wppa_link['countdown_of']
						? __( 'До открытия:', 'wp-personal-account' )
						: __( 'Осталось:', 'wp-personal-account' )
				);
				?>
			</span>
			<span
				class="wppa-countdown"
				data-wppa-countdown="<?php echo esc_attr( (int) $wppa_link['countdown'] ); ?>"
				data-wppa-countdown-mode="<?php echo 'start' === $wppa_link['countdown_of'] ? 'open' : 'close'; ?>"
				<?php echo 'start' === $wppa_link['countdown_of'] ? 'data-wppa-reload="1"' : ''; ?>
			><?php echo esc_html( WPPA_Link_Button::countdown_text( $wppa_link['countdown'] - time() ) ); ?></span>
		</p>
	<?php endif; ?>

	<?php if ( 'unconfigured' === $wppa_link['status'] && current_user_can( 'manage_options' ) ) : ?>
		<p class="wppa-hint wppa-hint--block">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wppa-settings' ) ); ?>">
				<?php esc_html_e( 'Настройки → «Кнопка со ссылкой»', 'wp-personal-account' ); ?>
			</a>
		</p>
	<?php endif; ?>
</section>
