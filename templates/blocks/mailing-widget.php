<?php
/**
 * Блок «Рассылка» — три рассылки куратора: второй этап, прикреплённые файлы и сертификаты.
 *
 * Критерии у обеих одинаковые (команды, прошедшие второй этап: участники,
 * капитан и наставник), но шаблон, очередь и журнал — свои.
 *
 * Доступные переменные: $user, $tabs, $block_title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

if ( ! WPPA_Teams::is_curator( $user->ID ) ) {
	return;
}

$wppa_back   = WPPA_Plugin::tab_url( 'mailing' );
$wppa_checks = get_option( 'wppa_settings', array() );

$wppa_kinds = array(
	'stage2' => array(
		'class' => 'WPPA_Mailing',
		'title' => __( 'Рассылка участникам второго этапа', 'wp-personal-account' ),
		'hint'  => __( 'Письмо о том, что команда прошла во второй этап. Получатели — участники, капитан и наставник команд с отметкой второго этапа.', 'wp-personal-account' ),
	),
	'files'  => array(
		'class' => 'WPPA_Mailing_Files',
		'title' => __( 'Рассылка о прикреплённых файлах', 'wp-personal-account' ),
		'hint'  => __( 'Письмо о том, что куратор прикрепил для команды документы. Получатели — участники, капитан и наставник всех команд: отметка второго этапа не требуется.', 'wp-personal-account' ),
	),
	'certificates' => array(
		'class' => 'WPPA_Mailing_Certificates',
		'title' => __( 'Рассылка о сертификатах', 'wp-personal-account' ),
		'hint'  => __( 'Письмо о выданном сертификате: номер, программа и дата выдачи подставляются из сертификата. Получатели — участники и капитаны, которым сертификат уже выдан; наставникам и командам без сертификатов письмо не уходит.', 'wp-personal-account' ),
	),
);
?>

<h2 class="wppa-title">
	<?php echo isset( $block_title ) && '' !== $block_title ? esc_html( $block_title ) : esc_html__( 'Рассылки', 'wp-personal-account' ); ?>
</h2>

<?php foreach ( $wppa_kinds as $wppa_kind => $wppa_meta ) : ?>
	<?php
	$wppa_cls         = $wppa_meta['class'];
	$wppa_settings    = $wppa_cls::settings();
	$wppa_stats       = $wppa_cls::stats();
	$wppa_recipients  = $wppa_cls::recipients();
	$wppa_sent_before = $wppa_cls::already_sent( $wppa_recipients );
	$wppa_next        = $wppa_cls::next_run();
	$wppa_logs        = $wppa_cls::logs();
	$wppa_preview_row = ! empty( $wppa_recipients ) ? $wppa_recipients[0] : array(
		'first'    => __( 'Иван', 'wp-personal-account' ),
		'last'     => __( 'Петров', 'wp-personal-account' ),
		'name'     => __( 'Иван Петров', 'wp-personal-account' ),
		'email'    => 'ivan@example.com',
		'role'     => __( 'Капитан', 'wp-personal-account' ),
		'team'     => __( 'Студия «Вектор»', 'wp-personal-account' ),
		'date'     => date_i18n( get_option( 'date_format' ) ),
		'number'   => 'WPD-0001',
		'course'   => __( 'Студия «Вектор»', 'wp-personal-account' ),
		'issued'   => date_i18n( get_option( 'date_format' ) ),
		'certs'    => 1,
	);
	$wppa_subject     = $wppa_cls::render( $wppa_settings['subject'], $wppa_preview_row, true );
	$wppa_body        = $wppa_cls::render( $wppa_settings['body'], $wppa_preview_row );
	$wppa_id          = 'wppa-mailing-' . $wppa_kind;
	?>

	<section class="wppa-card wppa-mailing" id="<?php echo esc_attr( $wppa_id ); ?>">
		<h3 class="wppa-subtitle"><?php echo esc_html( $wppa_meta['title'] ); ?></h3>
		<p class="wppa-hint">
			<?php echo esc_html( $wppa_meta['hint'] ); ?>
			<?php
			printf(
				/* translators: 1: получателей, 2: уже получали */
				esc_html__( 'Получателей сейчас %1$d, из них уже получали письмо — %2$d.', 'wp-personal-account' ),
				count( $wppa_recipients ),
				(int) $wppa_sent_before
			);
			?>
		</p>

		<form class="wppa-form" method="post" action="<?php echo esc_url( $wppa_back ); ?>">
			<?php wp_nonce_field( 'wppa_mailing', 'wppa_nonce' ); ?>
			<input type="hidden" name="wppa_action" value="mailing" />
			<input type="hidden" name="mailing_kind" value="<?php echo esc_attr( $wppa_kind ); ?>" />

			<p class="wppa-field">
				<label for="<?php echo esc_attr( $wppa_id ); ?>-subject"><?php esc_html_e( 'Тема', 'wp-personal-account' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $wppa_id ); ?>-subject" name="subject" value="<?php echo esc_attr( $wppa_settings['subject'] ); ?>" />
			</p>

			<p class="wppa-field">
				<label for="<?php echo esc_attr( $wppa_id ); ?>-body"><?php esc_html_e( 'Текст письма', 'wp-personal-account' ); ?></label>
				<textarea id="<?php echo esc_attr( $wppa_id ); ?>-body" name="body" rows="8"><?php echo esc_textarea( $wppa_settings['body'] ); ?></textarea>
			</p>

			<p class="wppa-hint">
				<?php esc_html_e( 'Подстановки:', 'wp-personal-account' ); ?>
				<?php foreach ( (array) $wppa_cls::placeholder_list() as $wppa_placeholder ) : ?>
					<code><?php echo esc_html( $wppa_placeholder ); ?></code>
				<?php endforeach; ?>
			</p>

			<p class="wppa-field">
				<label for="<?php echo esc_attr( $wppa_id ); ?>-interval"><?php esc_html_e( 'Интервал между письмами, минут', 'wp-personal-account' ); ?></label>
				<input type="number" min="0" max="1440" step="1" id="<?php echo esc_attr( $wppa_id ); ?>-interval" name="interval" value="<?php echo esc_attr( (string) $wppa_settings['interval'] ); ?>" />
				<span class="wppa-hint"><?php esc_html_e( '0 — отправить всё сразу, без пауз.', 'wp-personal-account' ); ?></span>
			</p>

			<p class="wppa-field">
				<label for="<?php echo esc_attr( $wppa_id ); ?>-batch"><?php esc_html_e( 'Писем за один запуск', 'wp-personal-account' ); ?></label>
				<input type="number" min="1" max="50" step="1" id="<?php echo esc_attr( $wppa_id ); ?>-batch" name="batch" value="<?php echo esc_attr( (string) $wppa_settings['batch'] ); ?>" />
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

		<details class="wppa-details">
			<summary><?php esc_html_e( 'Предпросмотр письма', 'wp-personal-account' ); ?></summary>
			<div class="wppa-preview">
				<p><strong><?php echo esc_html( $wppa_subject ); ?></strong></p>
				<div class="wppa-preview__body"><?php echo wp_kses_post( $wppa_cls::to_html( $wppa_body ) ); ?></div>
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
		</details>

		<h4 class="wppa-subtitle"><?php esc_html_e( 'Очередь', 'wp-personal-account' ); ?></h4>

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
						<input type="hidden" name="mailing_kind" value="<?php echo esc_attr( $wppa_kind ); ?>" />
						<button type="submit" class="wppa-button" name="wppa_mailing_action" value="resume"><?php esc_html_e( 'Продолжить рассылку', 'wp-personal-account' ); ?></button>
					</form>
				<?php endif; ?>

				<?php if ( $wppa_stats['pending'] > 0 && empty( $wppa_stats['paused'] ) ) : ?>
					<form method="post" action="<?php echo esc_url( $wppa_back ); ?>" class="wppa-inline-form">
						<?php wp_nonce_field( 'wppa_mailing', 'wppa_nonce' ); ?>
						<input type="hidden" name="wppa_action" value="mailing" />
						<input type="hidden" name="mailing_kind" value="<?php echo esc_attr( $wppa_kind ); ?>" />
						<button type="submit" class="wppa-button" name="wppa_mailing_action" value="send_now"><?php esc_html_e( 'Отправить остаток сейчас', 'wp-personal-account' ); ?></button>
					</form>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( $wppa_back ); ?>" class="wppa-inline-form">
					<?php wp_nonce_field( 'wppa_mailing', 'wppa_nonce' ); ?>
					<input type="hidden" name="wppa_action" value="mailing" />
					<input type="hidden" name="mailing_kind" value="<?php echo esc_attr( $wppa_kind ); ?>" />
					<button type="submit" class="wppa-button wppa-button--danger" name="wppa_mailing_action" value="cancel" data-wppa-confirm="<?php esc_attr_e( 'Отменить рассылку и очистить очередь?', 'wp-personal-account' ); ?>"><?php esc_html_e( 'Отменить рассылку', 'wp-personal-account' ); ?></button>
				</form>
			</p>
		<?php endif; ?>

		<h4 class="wppa-subtitle"><?php esc_html_e( 'Журнал', 'wp-personal-account' ); ?></h4>

		<?php if ( empty( $wppa_logs ) ) : ?>
			<div class="wppa-notice wppa-notice--info"><?php esc_html_e( 'Журнал пока пуст.', 'wp-personal-account' ); ?></div>
		<?php else : ?>
			<ul class="wppa-log">
				<?php foreach ( array_slice( (array) $wppa_logs, 0, 10 ) as $wppa_log ) : ?>
					<li class="wppa-log__item">
						<span class="wppa-hint"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $wppa_log['time'] ) ); ?></span>
						<?php echo esc_html( (string) $wppa_log['text'] ); ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<form method="post" action="<?php echo esc_url( $wppa_back ); ?>" class="wppa-inline-form">
				<?php wp_nonce_field( 'wppa_mailing', 'wppa_nonce' ); ?>
				<input type="hidden" name="wppa_action" value="mailing" />
				<input type="hidden" name="mailing_kind" value="<?php echo esc_attr( $wppa_kind ); ?>" />
				<button type="submit" class="wppa-link-button" name="wppa_mailing_action" value="clear_log"><?php esc_html_e( 'Очистить журнал', 'wp-personal-account' ); ?></button>
			</form>
		<?php endif; ?>
	</section>
<?php endforeach; ?>
