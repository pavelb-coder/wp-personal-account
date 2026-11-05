<?php
/**
 * Блок «Рассылка» — письмо участникам второго этапа (кабинет куратора).
 *
 * Куратор правит тему и текст письма, ставит рассылку в очередь, отменяет её,
 * отправляет остаток досрочно и смотрит журнал. Всё — из кабинета, без админки.
 *
 * Доступные переменные: $user, $tabs, $block_title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

if ( ! WPPA_Teams::is_curator( $user->ID ) ) {
	return;
}

$wppa_settings    = WPPA_Mailing::settings();
$wppa_stats       = WPPA_Mailing::stats();
$wppa_recipients  = WPPA_Mailing::recipients();
$wppa_sent_before = WPPA_Mailing::already_sent( $wppa_recipients );
$wppa_next        = WPPA_Mailing::next_run();
$wppa_logs        = WPPA_Mailing::logs();
$wppa_back        = WPPA_Plugin::tab_url( 'mailing' );
$wppa_preview_row = ! empty( $wppa_recipients ) ? $wppa_recipients[0] : array(
	'first' => __( 'Иван', 'wp-personal-account' ),
	'last'  => __( 'Петров', 'wp-personal-account' ),
	'name'  => __( 'Иван Петров', 'wp-personal-account' ),
	'email' => 'ivan@example.com',
	'role'  => __( 'Капитан', 'wp-personal-account' ),
	'team'  => __( 'Студия «Вектор»', 'wp-personal-account' ),
	'date'  => date_i18n( get_option( 'date_format' ) ),
);
$wppa_subject     = WPPA_Mailing::render( $wppa_settings['subject'], $wppa_preview_row, true );
$wppa_body        = WPPA_Mailing::render( $wppa_settings['body'], $wppa_preview_row );
?>

<h2 class="wppa-title">
	<?php echo isset( $block_title ) && '' !== $block_title ? esc_html( $block_title ) : esc_html__( 'Рассылка участникам второго этапа', 'wp-personal-account' ); ?>
</h2>

<p class="wppa-hint">
	<?php
	printf(
		/* translators: 1: получателей, 2: уже получали */
		esc_html__( 'Письмо уходит участникам, капитанам и наставникам команд, отмеченных «прошла во второй этап»: получателей сейчас %1$d, из них уже получали письмо — %2$d. Отправка идёт очередью: каждое следующее письмо — через заданный интервал.', 'wp-personal-account' ),
		count( $wppa_recipients ),
		(int) $wppa_sent_before
	);
	?>
</p>

<section class="wppa-card">
	<h3 class="wppa-subtitle"><?php esc_html_e( 'Шаблон письма', 'wp-personal-account' ); ?></h3>

	<form class="wppa-form" method="post" action="<?php echo esc_url( $wppa_back ); ?>">
		<?php wp_nonce_field( 'wppa_mailing', 'wppa_nonce' ); ?>
		<input type="hidden" name="wppa_action" value="mailing" />

		<p class="wppa-field">
			<label for="wppa-mailing-subject"><?php esc_html_e( 'Тема', 'wp-personal-account' ); ?></label>
			<input type="text" id="wppa-mailing-subject" name="subject" value="<?php echo esc_attr( $wppa_settings['subject'] ); ?>" />
		</p>

		<p class="wppa-field">
			<label for="wppa-mailing-body"><?php esc_html_e( 'Текст письма', 'wp-personal-account' ); ?></label>
			<textarea id="wppa-mailing-body" name="body" rows="10"><?php echo esc_textarea( $wppa_settings['body'] ); ?></textarea>
		</p>

		<p class="wppa-hint">
			<?php esc_html_e( 'Подстановки:', 'wp-personal-account' ); ?>
			<?php foreach ( (array) WPPA_Mailing::placeholder_list() as $wppa_placeholder ) : ?>
				<code><?php echo esc_html( $wppa_placeholder ); ?></code>
			<?php endforeach; ?>
		</p>

		<p class="wppa-field">
			<label for="wppa-mailing-interval"><?php esc_html_e( 'Интервал между письмами, минут', 'wp-personal-account' ); ?></label>
			<input type="number" min="0" max="1440" step="1" id="wppa-mailing-interval" name="interval" value="<?php echo esc_attr( (string) $wppa_settings['interval'] ); ?>" />
			<span class="wppa-hint"><?php esc_html_e( '0 — отправить всё сразу, без пауз.', 'wp-personal-account' ); ?></span>
		</p>

		<p class="wppa-field">
			<label for="wppa-mailing-batch"><?php esc_html_e( 'Писем за один запуск', 'wp-personal-account' ); ?></label>
			<input type="number" min="1" max="50" step="1" id="wppa-mailing-batch" name="batch" value="<?php echo esc_attr( (string) $wppa_settings['batch'] ); ?>" />
		</p>

		<p class="wppa-field">
			<label>
				<input type="checkbox" name="skip_sent" value="1" <?php checked( 1, (int) $wppa_settings['skip_sent'] ); ?> />
				<?php esc_html_e( 'Не отправлять повторно тем, кто уже получал письмо', 'wp-personal-account' ); ?>
			</label>
		</p>

		<p class="wppa-actions">
			<button type="submit" class="wppa-button" name="wppa_mailing_action" value="save"><?php esc_html_e( 'Сохранить шаблон', 'wp-personal-account' ); ?></button>
			<button type="submit" class="wppa-button wppa-button--primary" name="wppa_mailing_action" value="start" <?php disabled( 0, count( $wppa_recipients ) ); ?>><?php esc_html_e( 'Поставить в очередь', 'wp-personal-account' ); ?></button>
		</p>
	</form>

	<h3 class="wppa-subtitle"><?php esc_html_e( 'Предпросмотр', 'wp-personal-account' ); ?></h3>
	<div class="wppa-preview">
		<p><strong><?php echo esc_html( $wppa_subject ); ?></strong></p>
		<div class="wppa-preview__body"><?php echo wp_kses_post( WPPA_Mailing::to_html( $wppa_body ) ); ?></div>
		<p class="wppa-hint">
			<?php
			printf(
				/* translators: %s: email-адрес */
				esc_html__( 'Пример на получателе %s', 'wp-personal-account' ),
				esc_html( $wppa_preview_row['email'] )
			);
			?>
		</p>
	</div>
</section>

<section class="wppa-card">
	<h3 class="wppa-subtitle"><?php esc_html_e( 'Очередь рассылки', 'wp-personal-account' ); ?></h3>

	<?php if ( empty( $wppa_stats['active'] ) ) : ?>
		<div class="wppa-notice wppa-notice--info"><?php esc_html_e( 'Рассылка не запущена. Проверьте шаблон и нажмите «Поставить в очередь».', 'wp-personal-account' ); ?></div>
	<?php else : ?>
		<div class="wppa-table-wrap">
			<table class="wppa-table">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Всего писем', 'wp-personal-account' ); ?></td>
						<td><strong><?php echo esc_html( (string) $wppa_stats['total'] ); ?></strong></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Отправлено', 'wp-personal-account' ); ?></td>
						<td><?php echo esc_html( (string) $wppa_stats['sent'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Осталось', 'wp-personal-account' ); ?></td>
						<td><?php echo esc_html( (string) $wppa_stats['pending'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Ошибок', 'wp-personal-account' ); ?></td>
						<td><?php echo esc_html( (string) $wppa_stats['failed'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Пропущено (отметка снята)', 'wp-personal-account' ); ?></td>
						<td><?php echo esc_html( (string) $wppa_stats['skipped'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Следующее письмо', 'wp-personal-account' ); ?></td>
						<td>
							<?php
							if ( ! empty( $wppa_stats['done'] ) ) {
								esc_html_e( 'рассылка завершена', 'wp-personal-account' );
							} elseif ( $wppa_next > 0 ) {
								echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $wppa_next ) );
							} else {
								esc_html_e( 'не запланировано', 'wp-personal-account' );
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<?php if ( ! empty( $wppa_stats['paused'] ) ) : ?>
			<div class="wppa-notice wppa-notice--info"><?php echo esc_html( $wppa_stats['paused'] ); ?></div>
		<?php endif; ?>

		<p class="wppa-actions">
			<?php if ( ! empty( $wppa_stats['paused'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( $wppa_back ); ?>" class="wppa-inline-form">
					<?php wp_nonce_field( 'wppa_mailing', 'wppa_nonce' ); ?>
					<input type="hidden" name="wppa_action" value="mailing" />
					<button type="submit" class="wppa-button" name="wppa_mailing_action" value="resume"><?php esc_html_e( 'Продолжить рассылку', 'wp-personal-account' ); ?></button>
				</form>
			<?php endif; ?>

			<?php if ( $wppa_stats['pending'] > 0 && empty( $wppa_stats['paused'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( $wppa_back ); ?>" class="wppa-inline-form">
					<?php wp_nonce_field( 'wppa_mailing', 'wppa_nonce' ); ?>
					<input type="hidden" name="wppa_action" value="mailing" />
					<button type="submit" class="wppa-button" name="wppa_mailing_action" value="send_now"><?php esc_html_e( 'Отправить остаток сейчас', 'wp-personal-account' ); ?></button>
				</form>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( $wppa_back ); ?>" class="wppa-inline-form">
				<?php wp_nonce_field( 'wppa_mailing', 'wppa_nonce' ); ?>
				<input type="hidden" name="wppa_action" value="mailing" />
				<button type="submit" class="wppa-button wppa-button--danger" name="wppa_mailing_action" value="cancel" data-wppa-confirm="<?php esc_attr_e( 'Отменить рассылку и очистить очередь?', 'wp-personal-account' ); ?>"><?php esc_html_e( 'Отменить рассылку', 'wp-personal-account' ); ?></button>
			</form>
		</p>
	<?php endif; ?>
</section>

<section class="wppa-card">
	<h3 class="wppa-subtitle"><?php esc_html_e( 'Журнал рассылки', 'wp-personal-account' ); ?></h3>

	<?php if ( empty( $wppa_logs ) ) : ?>
		<div class="wppa-notice wppa-notice--info"><?php esc_html_e( 'Журнал пока пуст.', 'wp-personal-account' ); ?></div>
	<?php else : ?>
		<ul class="wppa-log">
			<?php foreach ( array_slice( (array) $wppa_logs, 0, 20 ) as $wppa_log ) : ?>
				<li class="wppa-log__item">
					<span class="wppa-hint"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $wppa_log['time'] ) ); ?></span>
					<?php echo esc_html( (string) $wppa_log['text'] ); ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<form method="post" action="<?php echo esc_url( $wppa_back ); ?>" class="wppa-inline-form">
			<?php wp_nonce_field( 'wppa_mailing', 'wppa_nonce' ); ?>
			<input type="hidden" name="wppa_action" value="mailing" />
			<button type="submit" class="wppa-link-button" name="wppa_mailing_action" value="clear_log"><?php esc_html_e( 'Очистить журнал', 'wp-personal-account' ); ?></button>
		</form>
	<?php endif; ?>
</section>
