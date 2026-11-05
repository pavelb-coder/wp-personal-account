<?php
/**
 * Админ-панель: страница «Рассылка участникам второго этапа».
 *
 * Доступные переменные: $notice, $settings, $stats, $logs, $recipients,
 * $sent_before, $placeholders, $next, $queue.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_queue_items = isset( $queue['items'] ) ? (array) $queue['items'] : array();
$wppa_preview     = ! empty( $recipients ) ? $recipients[0] : array(
	'first' => 'Иван',
	'name'  => 'Иван Петров',
	'last'  => 'Петров',
	'email' => 'ivan@example.com',
	'role'  => __( 'Капитан', 'wp-personal-account' ),
	'team'  => __( 'Студия «Вектор»', 'wp-personal-account' ),
	'date'  => date_i18n( get_option( 'date_format' ) ),
);
$wppa_subject     = WPPA_Mailing::render( $settings['subject'], $wppa_preview, true );
$wppa_body        = WPPA_Mailing::render( $settings['body'], $wppa_preview );
?>
<div class="wrap wppa-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Рассылка участникам второго этапа', 'wp-personal-account' ); ?></h1>
	<hr class="wp-header-end" />

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'success' === $notice[0] ? 'success' : ( 'error' === $notice[0] ? 'error' : 'warning' ) ); ?> is-dismissible">
			<p><?php echo esc_html( $notice[1] ); ?></p>
		</div>
	<?php endif; ?>

	<div class="notice notice-info">
		<p>
			<?php
			printf(
				/* translators: 1: получателей, 2: команд, 3: уже получили */
				esc_html__( 'Письма уходят участникам, капитанам и наставникам команд, отмеченных «прошла во второй этап»: получателей сейчас %1$d, из них уже получали письмо — %2$d. Отправка идёт очередью: первое письмо сразу, каждое следующее — через заданный интервал, по несколько писем за один запуск WP-Cron. Рассылку можно отменить в любой момент.', 'wp-personal-account' ),
				count( $recipients ),
				(int) $sent_before
			);
			?>
		</p>
	</div>

	<div class="wppa-admin__grid">
		<section class="wppa-admin__panel">
			<h2><?php esc_html_e( 'Шаблон письма', 'wp-personal-account' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wppa_mailing' ); ?>
				<input type="hidden" name="action" value="wppa_mailing" />

				<p>
					<label for="wppa-mailing-subject"><strong><?php esc_html_e( 'Тема', 'wp-personal-account' ); ?></strong></label><br />
					<input type="text" class="widefat" id="wppa-mailing-subject" name="subject" value="<?php echo esc_attr( $settings['subject'] ); ?>" />
				</p>

				<p>
					<label for="wppa-mailing-body"><strong><?php esc_html_e( 'Текст письма', 'wp-personal-account' ); ?></strong></label><br />
					<textarea class="widefat" rows="12" id="wppa-mailing-body" name="body"><?php echo esc_textarea( $settings['body'] ); ?></textarea>
				</p>

				<p class="wppa-admin__muted">
					<?php esc_html_e( 'Подстановки:', 'wp-personal-account' ); ?>
					<?php foreach ( (array) $placeholders as $wppa_placeholder ) : ?>
						<code><?php echo esc_html( $wppa_placeholder ); ?></code>
					<?php endforeach; ?>
				</p>

				<p>
					<label for="wppa-mailing-interval"><strong><?php esc_html_e( 'Интервал между письмами, минут', 'wp-personal-account' ); ?></strong></label><br />
					<input type="number" min="0" max="1440" step="1" id="wppa-mailing-interval" name="interval" value="<?php echo esc_attr( (string) $settings['interval'] ); ?>" />
					<span class="wppa-admin__muted"><?php esc_html_e( '0 — отправить всё сразу, без пауз. Каждое следующее письмо уходит не раньше, чем через этот интервал: так письма не попадут в спам и хостинг не упрётся в лимит.', 'wp-personal-account' ); ?></span>
				</p>

				<p>
					<label for="wppa-mailing-batch"><strong><?php esc_html_e( 'Писем за один запуск', 'wp-personal-account' ); ?></strong></label><br />
					<input type="number" min="1" max="50" step="1" id="wppa-mailing-batch" name="batch" value="<?php echo esc_attr( (string) $settings['batch'] ); ?>" />
				</p>

				<p>
					<label>
						<input type="checkbox" name="skip_sent" value="1" <?php checked( 1, (int) $settings['skip_sent'] ); ?> />
						<?php esc_html_e( 'Не отправлять повторно тем, кто уже получал письмо', 'wp-personal-account' ); ?>
					</label>
				</p>

				<p class="wppa-admin__actions">
					<button type="submit" class="button" name="wppa_mailing_action" value="save"><?php esc_html_e( 'Сохранить шаблон', 'wp-personal-account' ); ?></button>
					<button type="submit" class="button button-primary" name="wppa_mailing_action" value="start" <?php disabled( 0, count( $recipients ) ); ?>><?php esc_html_e( 'Поставить в очередь', 'wp-personal-account' ); ?></button>
				</p>
			</form>

			<h2><?php esc_html_e( 'Предпросмотр', 'wp-personal-account' ); ?></h2>
			<div class="wppa-admin__preview">
				<p><strong><?php echo esc_html( $wppa_subject ); ?></strong></p>
				<div class="wppa-admin__preview-body">
					<?php echo wp_kses_post( WPPA_Mailing::to_html( $wppa_body ) ); ?>
				</div>
				<p class="wppa-admin__muted">
					<?php
					printf(
						/* translators: %s: email-адрес */
						esc_html__( 'Пример на получателе %s', 'wp-personal-account' ),
						esc_html( $wppa_preview['email'] )
					);
					?>
				</p>
			</div>
		</section>

		<section class="wppa-admin__panel">
			<h2><?php esc_html_e( 'Очередь', 'wp-personal-account' ); ?></h2>

			<?php if ( empty( $stats['active'] ) ) : ?>
				<p class="wppa-admin__muted"><?php esc_html_e( 'Рассылка не запущена. Настройте шаблон и нажмите «Поставить в очередь».', 'wp-personal-account' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Всего писем', 'wp-personal-account' ); ?></td>
							<td><strong><?php echo esc_html( (string) $stats['total'] ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Отправлено', 'wp-personal-account' ); ?></td>
							<td><?php echo esc_html( (string) $stats['sent'] ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Осталось', 'wp-personal-account' ); ?></td>
							<td><?php echo esc_html( (string) $stats['pending'] ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Ошибок', 'wp-personal-account' ); ?></td>
							<td><?php echo esc_html( (string) $stats['failed'] ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Шаг', 'wp-personal-account' ); ?></td>
							<td>
								<?php
								printf(
									/* translators: %s: интервал в минутах */
									esc_html__( '%s мин.', 'wp-personal-account' ),
									esc_html( (string) ( isset( $queue['interval'] ) ? (int) $queue['interval'] : 0 ) )
								);
								?>
							</td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Следующее письмо', 'wp-personal-account' ); ?></td>
							<td>
								<?php
								if ( ! empty( $stats['done'] ) ) {
									esc_html_e( 'рассылка завершена', 'wp-personal-account' );
								} elseif ( $next > 0 ) {
									echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $next ) );
								} else {
									esc_html_e( 'не запланировано', 'wp-personal-account' );
								}
								?>
							</td>
						</tr>
					</tbody>
				</table>

				<?php if ( ! empty( $stats['paused'] ) ) : ?>
					<div class="notice notice-error inline">
						<p><?php echo esc_html( $stats['paused'] ); ?></p>
					</div>
				<?php endif; ?>

				<p class="wppa-admin__actions">
					<?php if ( ! empty( $stats['paused'] ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wppa-inline">
							<?php wp_nonce_field( 'wppa_mailing' ); ?>
							<input type="hidden" name="action" value="wppa_mailing" />
							<button type="submit" class="button" name="wppa_mailing_action" value="resume"><?php esc_html_e( 'Продолжить рассылку', 'wp-personal-account' ); ?></button>
						</form>
					<?php endif; ?>

					<?php if ( $stats['pending'] > 0 && empty( $stats['paused'] ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wppa-inline">
							<?php wp_nonce_field( 'wppa_mailing' ); ?>
							<input type="hidden" name="action" value="wppa_mailing" />
							<button type="submit" class="button" name="wppa_mailing_action" value="send_now"><?php esc_html_e( 'Отправить остаток сейчас', 'wp-personal-account' ); ?></button>
						</form>
					<?php endif; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wppa-inline">
						<?php wp_nonce_field( 'wppa_mailing' ); ?>
						<input type="hidden" name="action" value="wppa_mailing" />
						<button type="submit" class="button button-link-delete" name="wppa_mailing_action" value="cancel"><?php esc_html_e( 'Отменить рассылку', 'wp-personal-account' ); ?></button>
					</form>
				</p>

				<?php if ( ! empty( $wppa_queue_items ) ) : ?>
					<details class="wppa-admin__details">
						<summary><?php esc_html_e( 'Письма в очереди', 'wp-personal-account' ); ?></summary>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Получатель', 'wp-personal-account' ); ?></th>
									<th><?php esc_html_e( 'Команда', 'wp-personal-account' ); ?></th>
									<th><?php esc_html_e( 'Когда', 'wp-personal-account' ); ?></th>
									<th><?php esc_html_e( 'Состояние', 'wp-personal-account' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $wppa_queue_items as $wppa_item ) : ?>
									<tr>
										<td>
											<?php echo esc_html( $wppa_item['name'] ); ?><br />
											<span class="wppa-admin__muted"><?php echo esc_html( $wppa_item['email'] ); ?></span>
										</td>
										<td><?php echo esc_html( $wppa_item['team'] ); ?></td>
										<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $wppa_item['due'] ) ); ?></td>
										<td>
											<?php
											switch ( isset( $wppa_item['status'] ) ? $wppa_item['status'] : 'pending' ) {
												case 'sent':
													esc_html_e( 'отправлено', 'wp-personal-account' );
													break;
												case 'failed':
													echo esc_html( 'ошибка' . ( ! empty( $wppa_item['error'] ) ? ': ' . $wppa_item['error'] : '' ) );
													break;
												default:
													esc_html_e( 'ждёт отправки', 'wp-personal-account' );
											}
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</details>
				<?php endif; ?>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Получатели', 'wp-personal-account' ); ?></h2>

			<?php if ( empty( $recipients ) ) : ?>
				<p class="wppa-admin__muted"><?php esc_html_e( 'Команд второго этапа пока нет — отметьте их на странице «Команды».', 'wp-personal-account' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Получатель', 'wp-personal-account' ); ?></th>
							<th><?php esc_html_e( 'Роль', 'wp-personal-account' ); ?></th>
							<th><?php esc_html_e( 'Команда', 'wp-personal-account' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recipients as $wppa_recipient ) : ?>
							<tr>
								<td>
									<?php echo esc_html( $wppa_recipient['name'] ); ?><br />
									<span class="wppa-admin__muted"><?php echo esc_html( $wppa_recipient['email'] ); ?></span>
								</td>
								<td><?php echo esc_html( $wppa_recipient['role'] ); ?></td>
								<td><?php echo esc_html( $wppa_recipient['team'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Журнал рассылки', 'wp-personal-account' ); ?></h2>

			<?php if ( empty( $logs ) ) : ?>
				<p class="wppa-admin__muted"><?php esc_html_e( 'Записей пока нет.', 'wp-personal-account' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<tbody>
						<?php foreach ( $logs as $wppa_log ) : ?>
							<tr>
								<td class="wppa-admin__muted"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $wppa_log['time'] ) ); ?></td>
								<td><?php echo esc_html( $wppa_log['text'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wppa-inline">
					<?php wp_nonce_field( 'wppa_mailing' ); ?>
					<input type="hidden" name="action" value="wppa_mailing" />
					<button type="submit" class="button button-link-delete" name="wppa_mailing_action" value="clear_log"><?php esc_html_e( 'Очистить журнал', 'wp-personal-account' ); ?></button>
				</form>
			<?php endif; ?>
		</section>
	</div>
</div>
