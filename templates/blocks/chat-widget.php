<?php
/** Чат наставника с кураторами; $user — текущий пользователь кабинета. */
defined( 'ABSPATH' ) || exit;
$wppa_chat_user_id = (int) $user->ID;
if ( ! is_user_logged_in() || get_current_user_id() !== $wppa_chat_user_id || ! WPPA_Chat::can_use( $wppa_chat_user_id ) ) { return; }
$wppa_chat_is_curator = WPPA_Teams::is_curator( $wppa_chat_user_id );
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- параметры только выбирают переписку и страницу; доступ проверяется ниже.
$wppa_chat_mentor_id = $wppa_chat_is_curator ? ( isset( $_GET['wppa_chat_mentor'] ) && is_scalar( $_GET['wppa_chat_mentor'] ) ? absint( wp_unslash( $_GET['wppa_chat_mentor'] ) ) : 0 ) : $wppa_chat_user_id;
$wppa_chat_page = isset( $_GET['wppa_chat_page'] ) && is_scalar( $_GET['wppa_chat_page'] ) ? min( 10000, max( 1, absint( wp_unslash( $_GET['wppa_chat_page'] ) ) ) ) : 1;
// phpcs:enable
$wppa_chat_allowed = WPPA_Chat::can_view( $wppa_chat_mentor_id, $wppa_chat_user_id );
$wppa_chat_mentors = $wppa_chat_is_curator ? WPPA_Chat::mentors() : array();
$wppa_chat_mentor = $wppa_chat_allowed ? get_userdata( $wppa_chat_mentor_id ) : false;
$wppa_chat_data = $wppa_chat_allowed && $wppa_chat_mentor ? WPPA_Chat::messages( $wppa_chat_mentor_id, $wppa_chat_user_id, $wppa_chat_page ) : array( 'items' => array(), 'more' => false );
// Переход по ссылке со страницы кабинета отмечает открытый диалог прочитанным и без JavaScript.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce дополнительно проверяется перед изменением отметки.
$wppa_chat_view_nonce = isset( $_GET['wppa_chat_view_nonce'] ) && is_string( $_GET['wppa_chat_view_nonce'] ) ? wp_unslash( $_GET['wppa_chat_view_nonce'] ) : '';
if ( $wppa_chat_is_curator && $wppa_chat_allowed && 1 === $wppa_chat_page && wp_verify_nonce( $wppa_chat_view_nonce, 'wppa_chat_view_' . $wppa_chat_mentor_id ) ) {
	WPPA_Chat::mark_read( $wppa_chat_mentor_id, $wppa_chat_user_id, $wppa_chat_data['items'] );
}
$wppa_chat_unread = $wppa_chat_is_curator ? WPPA_Chat::unread( $wppa_chat_user_id, $wppa_chat_mentors ) : array();
?>
<section class="wppa-card wppa-chat">
	<h2 class="wppa-title"><?php echo isset( $block_title ) && '' !== $block_title ? esc_html( $block_title ) : esc_html__( 'Сообщения', 'wp-personal-account' ); ?></h2>
	<?php if ( $wppa_chat_is_curator ) : ?>
		<p class="wppa-hint"><?php esc_html_e( 'Выберите наставника. Переписку видят все кураторы и выбранный наставник.', 'wp-personal-account' ); ?></p>
		<div data-wppa-unread-list aria-live="polite"><?php echo WPPA_Chat::unread_html( $wppa_chat_user_id, $wppa_chat_unread, $wppa_chat_mentors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- значения экранируются в методе. ?></div>
		<?php if ( $wppa_chat_mentors ) : ?>
			<div class="wppa-chat__select">
				<label for="wppa-chat-mentor"><?php esc_html_e( 'Наставник', 'wp-personal-account' ); ?></label>
				<select id="wppa-chat-mentor" data-wppa-chat-select>
					<option value=""><?php esc_html_e( '— выберите наставника —', 'wp-personal-account' ); ?></option>
					<?php foreach ( $wppa_chat_mentors as $wppa_chat_choice ) : ?>
						<option value="<?php echo esc_url( WPPA_Chat::thread_url( $wppa_chat_choice->ID ) ); ?>"<?php selected( $wppa_chat_choice->ID, $wppa_chat_mentor_id ); ?>><?php echo esc_html( $wppa_chat_choice->display_name . ' (' . $wppa_chat_choice->user_email . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<noscript><ul class="wppa-chat__mentor-list">
				<?php foreach ( $wppa_chat_mentors as $wppa_chat_choice ) : ?>
					<li><a href="<?php echo esc_url( WPPA_Chat::thread_url( $wppa_chat_choice->ID ) ); ?>"><?php echo esc_html( $wppa_chat_choice->display_name . ' (' . $wppa_chat_choice->user_email . ')' ); ?></a></li>
				<?php endforeach; ?>
			</ul></noscript>
		<?php else : ?>
			<p class="wppa-hint"><?php esc_html_e( 'Действующих наставников пока нет.', 'wp-personal-account' ); ?></p>
		<?php endif; ?>
	<?php else : ?>
		<p class="wppa-hint"><?php esc_html_e( 'Эту переписку видите вы и все кураторы. Другим наставникам и участникам она недоступна.', 'wp-personal-account' ); ?></p>
	<?php endif; ?>
	<?php if ( $wppa_chat_allowed && $wppa_chat_mentor ) : ?>
		<h3 class="wppa-subtitle"><?php echo esc_html( $wppa_chat_is_curator ? sprintf( __( 'Переписка: %s', 'wp-personal-account' ), $wppa_chat_mentor->display_name ) : __( 'Переписка с кураторами', 'wp-personal-account' ) ); ?></h3>
		<div class="wppa-chat__history" data-wppa-chat-history data-mentor="<?php echo esc_attr( (string) $wppa_chat_mentor_id ); ?>" data-page="<?php echo esc_attr( (string) $wppa_chat_page ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wppa_chat_ajax' ) ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" role="log" aria-live="polite">
			<?php echo WPPA_Chat::messages_html( $wppa_chat_data['items'], $wppa_chat_mentor_id, $wppa_chat_user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- значения экранируются в методе. ?>
		</div>
		<div class="wppa-chat__pagination">
			<a class="wppa-link" data-wppa-chat-older <?php echo $wppa_chat_data['more'] ? '' : 'hidden'; ?> href="<?php echo esc_url( WPPA_Chat::thread_url( $wppa_chat_is_curator ? $wppa_chat_mentor_id : 0, $wppa_chat_page + 1 ) ); ?>"><?php esc_html_e( 'Более ранние сообщения', 'wp-personal-account' ); ?></a>
			<?php if ( $wppa_chat_page > 1 ) : ?><a class="wppa-link" href="<?php echo esc_url( WPPA_Chat::thread_url( $wppa_chat_is_curator ? $wppa_chat_mentor_id : 0, $wppa_chat_page - 1 ) ); ?>"><?php esc_html_e( 'Более новые сообщения', 'wp-personal-account' ); ?></a><?php endif; ?>
		</div>
		<form class="wppa-form wppa-chat__form" method="post" action="<?php echo esc_url( WPPA_Chat::thread_url( $wppa_chat_is_curator ? $wppa_chat_mentor_id : 0 ) ); ?>" data-wppa-chat-form>
			<?php wp_nonce_field( 'wppa_chat_send', 'wppa_nonce' ); ?>
			<input type="hidden" name="wppa_action" value="chat" />
			<input type="hidden" name="mentor_id" value="<?php echo esc_attr( (string) $wppa_chat_mentor_id ); ?>" />
			<label for="wppa-chat-message"><?php esc_html_e( 'Сообщение', 'wp-personal-account' ); ?></label>
			<textarea id="wppa-chat-message" name="message" rows="3" maxlength="2000" required></textarea>
			<button class="wppa-button wppa-button--primary" type="submit"><?php esc_html_e( 'Отправить', 'wp-personal-account' ); ?></button>
			<span class="wppa-chat__status" role="status" aria-live="polite"></span>
		</form>
		<p class="wppa-hint"><?php esc_html_e( 'Только текст, до 2000 символов. Последние сообщения обновляются автоматически.', 'wp-personal-account' ); ?></p>
	<?php elseif ( $wppa_chat_is_curator && $wppa_chat_mentor_id ) : ?>
		<p class="wppa-notice wppa-notice--error"><?php esc_html_e( 'Переписка недоступна.', 'wp-personal-account' ); ?></p>
	<?php endif; ?>
</section>
<?php wp_enqueue_script( 'wppa-chat', WPPA_URL . 'assets/js/chat.js', array(), WPPA_VERSION, true ); ?>
