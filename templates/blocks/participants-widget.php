<?php
/**
 * Блок «Участники и баллы» — только для куратора.
 *
 * Список участников команд без наставников, их баллы и процент за тесты,
 * и настройка минимального процента правильных ответов: участники ниже
 * порога помечаются.
 *
 * Доступные переменные: $user (WP_User).
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_viewer = isset( $user ) && $user instanceof WP_User ? $user : wp_get_current_user();

// Блок кураторский: участникам, капитанам и наставникам он не показывается.
if ( ! WPPA_Participants::can_manage( $wppa_viewer->ID ) ) {
	return;
}

$wppa_rows   = WPPA_Participants::rows( $wppa_viewer->ID );
$wppa_scores = WPPA_Participants::scores( $wppa_rows );
$wppa_threshold = WPPA_Participants::min_percent();
$wppa_below     = WPPA_Participants::count_below( $wppa_rows, $wppa_scores );

$wppa_certs_ready = class_exists( 'WPPA_Certificates' ) && WPPA_Certificates::available();

// Команды для массовой выдачи: только те, что попали в список.
$wppa_team_ids = array();

foreach ( $wppa_rows as $wppa_row_item ) {
	if ( ! in_array( (int) $wppa_row_item['team'], $wppa_team_ids, true ) ) {
		$wppa_team_ids[] = (int) $wppa_row_item['team'];
	}
}

$wppa_single_team = WPPA_Teams::viewing_team_id( $wppa_viewer->ID );
$wppa_block_url   = WPPA_Plugin::tab_url( 'files' );

if ( $wppa_single_team ) {
	$wppa_block_url = add_query_arg( 'wppa_team', (int) $wppa_single_team, $wppa_block_url );
}
?>

<section class="wppa-card wppa-participants">
	<h2 class="wppa-title">
		<?php echo isset( $block_title ) && '' !== $block_title ? esc_html( $block_title ) : esc_html__( 'Участники и баллы', 'wp-personal-account' ); ?>
	</h2>
	<p class="wppa-hint">
		<?php esc_html_e( 'Участники команд без наставников: баллы и процент за тесты. Участники ниже порога помечены.', 'wp-personal-account' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( $wppa_block_url ); ?>" class="wppa-participants__threshold">
		<?php wp_nonce_field( 'wppa_participant_threshold', 'wppa_nonce' ); ?>
		<input type="hidden" name="wppa_action" value="participant_threshold" />
		<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) (int) $wppa_single_team ); ?>" />

		<label class="wppa-participants__label" for="wppa-participant-threshold">
			<?php esc_html_e( 'Минимальный процент правильных ответов', 'wp-personal-account' ); ?>
		</label>
		<input
			type="number"
			min="0"
			max="100"
			step="1"
			id="wppa-participant-threshold"
			name="min_percent"
			value="<?php echo esc_attr( (string) (int) $wppa_threshold ); ?>"
			class="wppa-participants__percent"
		/>
		<span aria-hidden="true">%</span>
		<button type="submit" class="wppa-button wppa-button--primary wppa-button--sm">
			<?php esc_html_e( 'Сохранить', 'wp-personal-account' ); ?>
		</button>

		<?php if ( $wppa_scores ) : ?>
			<span class="wppa-hint">
				<?php
				printf(
					/* translators: 1: порог в процентах, 2: сколько участников ниже порога, 3: всего участников */
					esc_html__( 'Порог %1$d%%: ниже него — %2$d из %3$d участников.', 'wp-personal-account' ),
					(int) $wppa_threshold,
					(int) $wppa_below,
					(int) count( $wppa_rows )
				);
				?>
			</span>
		<?php endif; ?>
	</form>

	<?php if ( ! $wppa_rows ) : ?>

		<p class="wppa-notice wppa-notice--info"><?php esc_html_e( 'Участников пока нет: команды ещё не собраны.', 'wp-personal-account' ); ?></p>

	<?php else : ?>

		<?php if ( ! $wppa_scores && ! WPPA_Olympiad::is_active() ) : ?>
			<p class="wppa-hint"><?php esc_html_e( 'Интеграция с тестами не подключена — баллы появятся, когда на сайте будет активен плагин олимпиад.', 'wp-personal-account' ); ?></p>
		<?php endif; ?>

		<div class="wppa-table-wrap">
			<table class="wppa-table wppa-participants__table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Участник', 'wp-personal-account' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Тесты', 'wp-personal-account' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Баллы', 'wp-personal-account' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Процент', 'wp-personal-account' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Сертификат', 'wp-personal-account' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wppa_rows as $wppa_participant_id => $wppa_row ) : ?>
						<?php
						$wppa_score = isset( $wppa_scores[ $wppa_participant_id ] ) ? $wppa_scores[ $wppa_participant_id ] : null;
						$wppa_team  = get_post( (int) $wppa_row['team'] );
						?>
						<tr>
							<td data-label="<?php esc_attr_e( 'Участник', 'wp-personal-account' ); ?>">
								<strong><?php echo esc_html( WPPA_Teams::full_name( $wppa_row['user'] ) ); ?></strong>
								<?php if ( '' !== (string) $wppa_row['user']->user_email ) : ?>
									<br /><span class="wppa-hint"><?php echo esc_html( $wppa_row['user']->user_email ); ?></span>
								<?php endif; ?>
								<br />
								<span class="wppa-badge <?php echo 'owner' === $wppa_row['role'] ? 'wppa-badge--owner' : ''; ?>">
									<?php echo esc_html( WPPA_Teams::role_label( $wppa_row['role'] ) ); ?>
								</span>
								<?php if ( $wppa_team ) : ?>
									<span class="wppa-hint"><?php echo esc_html( $wppa_team->post_title ); ?></span>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Тесты', 'wp-personal-account' ); ?>">
								<?php if ( $wppa_score ) : ?>
									<?php echo esc_html( (string) (int) $wppa_score['tests'] ); ?>
									<?php if ( ! empty( $wppa_score['pending'] ) ) : ?>
										<br /><span class="wppa-hint"><?php esc_html_e( 'на проверке', 'wp-personal-account' ); ?>: <?php echo esc_html( (string) (int) $wppa_score['pending'] ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									<span class="wppa-hint"><?php echo esc_html( WPPA_Olympiad::is_active() ? '0' : '—' ); ?></span>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Баллы', 'wp-personal-account' ); ?>">
								<?php if ( $wppa_score ) : ?>
									<?php echo esc_html( (int) $wppa_score['score'] . ' / ' . (int) $wppa_score['max_score'] ); ?>
								<?php else : ?>
									<span class="wppa-hint">—</span>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Процент', 'wp-personal-account' ); ?>">
								<?php if ( $wppa_score ) : ?>
									<?php $wppa_below_row = WPPA_Participants::below_threshold( (float) $wppa_score['percentage'] ); ?>
									<span class="wppa-badge <?php echo $wppa_below_row ? 'wppa-badge--warn' : 'wppa-badge--ok'; ?>">
										<?php echo esc_html( WPPA_Participants::percent_label( (float) $wppa_score['percentage'] ) ); ?>
									</span>
									<?php if ( $wppa_below_row ) : ?>
										<br /><span class="wppa-participants__below">
											<?php
											printf(
												/* translators: %d: минимальный процент */
												esc_html__( 'ниже порога %d%%', 'wp-personal-account' ),
												(int) $wppa_threshold
											);
											?>
										</span>
									<?php endif; ?>
								<?php else : ?>
									<span class="wppa-hint">—</span>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Сертификат', 'wp-personal-account' ); ?>" class="wppa-participants__certs">
								<?php if ( ! $wppa_certs_ready ) : ?>
									<span class="wppa-hint"><?php esc_html_e( 'нужен WP Diplom', 'wp-personal-account' ); ?></span>
								<?php else : ?>
									<?php $wppa_user_certs = WPPA_Certificates::for_user( $wppa_participant_id ); ?>
									<?php foreach ( $wppa_user_certs as $wppa_cert ) : ?>
										<div class="wppa-participants__cert">
											<a class="wppa-link" href="<?php echo esc_url( $wppa_cert['url'] ); ?>">
												<?php echo esc_html( '' !== $wppa_cert['number'] ? $wppa_cert['number'] : __( 'PDF', 'wp-personal-account' ) ); ?>
											</a>
										<?php if ( $wppa_cert['revoked'] ) : ?>
											<span class="wppa-badge wppa-badge--warn"><?php esc_html_e( 'отозван', 'wp-personal-account' ); ?></span>
										<?php endif; ?>
											<form method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'participants' ) ); ?>" class="wppa-participants__cert-remove">
												<?php wp_nonce_field( 'wppa_certificate', 'wppa_nonce' ); ?>
												<input type="hidden" name="wppa_action" value="certificate" />
												<input type="hidden" name="certificate_action" value="remove" />
												<input type="hidden" name="participant_id" value="<?php echo esc_attr( (string) $wppa_participant_id ); ?>" />
												<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_row['team'] ); ?>" />
												<input type="hidden" name="certificate_id" value="<?php echo esc_attr( (string) $wppa_cert['id'] ); ?>" />
												<button type="submit" class="wppa-button wppa-button--ghost wppa-button--sm"><?php esc_html_e( 'Убрать', 'wp-personal-account' ); ?></button>
											</form>
										</div>
									<?php endforeach; ?>
									<form method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'participants' ) ); ?>" class="wppa-participants__cert-issue">
										<?php wp_nonce_field( 'wppa_certificate', 'wppa_nonce' ); ?>
										<input type="hidden" name="wppa_action" value="certificate" />
										<input type="hidden" name="certificate_action" value="issue" />
										<input type="hidden" name="participant_id" value="<?php echo esc_attr( (string) $wppa_participant_id ); ?>" />
										<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_row['team'] ); ?>" />
										<button type="submit" class="wppa-button wppa-button--primary wppa-button--sm">
											<?php echo $wppa_user_certs ? esc_html__( 'Ещё', 'wp-personal-account' ) : esc_html__( 'Выдать', 'wp-personal-account' ); ?>
										</button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<section class="wppa-participants__certificates">
			<h3 class="wppa-subtitle"><?php esc_html_e( 'Сертификаты участников', 'wp-personal-account' ); ?></h3>

			<?php if ( ! $wppa_certs_ready ) : ?>
				<p class="wppa-hint">
					<?php esc_html_e( 'Черновики и бланки сертификатов делает плагин WP Diplom. Установите и активируйте его — здесь появятся кнопки «Выдать сертификат», а участник увидит вкладку «Сертификаты».', 'wp-personal-account' ); ?>
				</p>
			<?php else : ?>
				<p class="wppa-hint">
					<?php
					printf(
						/* translators: %d: минимальный процент правильных ответов */
						esc_html__( 'Сертификат получает участник с процентом не ниже порога (%d%%). Номер и PDF присваивает WP Diplom; участнику уходит уведомление письмом, если оно включено в настройках, а наставник видит сертификаты участников в своей вкладке «Сертификаты».', 'wp-personal-account' ),
						(int) $wppa_threshold
					);
					?>
				</p>

				<form method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'participants' ) ); ?>" class="wppa-participants__issue-all">
					<?php wp_nonce_field( 'wppa_certificate', 'wppa_nonce' ); ?>
					<input type="hidden" name="wppa_action" value="certificate" />
					<input type="hidden" name="certificate_action" value="issue_threshold" />

					<?php if ( $wppa_single_team ) : ?>
						<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) (int) $wppa_single_team ); ?>" />
					<?php else : ?>
						<label class="wppa-participants__label" for="wppa-cert-team"><?php esc_html_e( 'Команда', 'wp-personal-account' ); ?></label>
						<select id="wppa-cert-team" name="team_id">
							<?php foreach ( $wppa_team_ids as $wppa_team_option ) : ?>
								<option value="<?php echo esc_attr( (string) (int) $wppa_team_option ); ?>">
									<?php echo esc_html( (string) get_the_title( (int) $wppa_team_option ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>

					<button type="submit" class="wppa-button wppa-button--primary wppa-button--sm">
						<?php esc_html_e( 'Выдать всем, кто выше порога', 'wp-personal-account' ); ?>
					</button>
				</form>
			<?php endif; ?>
		</section>

	<?php endif; ?>
</section>
