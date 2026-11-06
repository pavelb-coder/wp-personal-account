<?php
/**
 * Блок «Участники и баллы» — только для куратора.
 *
 * Список участников команд (без наставников), их баллы и процент за тесты,
 * и адресные документы: куратор прикрепляет файл конкретному участнику.
 *
 * Доступные переменные: $user (WP_User).
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_viewer = isset( $user ) && $user instanceof WP_User ? $user : wp_get_current_user();

// Блок кураторский: участникам, капитанам и наставникам он не показывается.
if ( ! WPPA_Teams::is_curator( $wppa_viewer->ID ) ) {
	return;
}

$wppa_single_team = WPPA_Teams::viewing_team_id( $wppa_viewer->ID );
$wppa_all_teams   = $wppa_single_team ? array() : WPPA_Teams::all_teams();
$wppa_team_ids    = $wppa_single_team ? array( (int) $wppa_single_team ) : array_map( 'intval', array_column( $wppa_all_teams, 'id' ) );

$wppa_participants = array();

foreach ( $wppa_team_ids as $wppa_team_id ) {
	foreach ( WPPA_Teams::participants( $wppa_team_id ) as $wppa_member ) {
		$wppa_participants[ (int) $wppa_member->ID ] = array(
			'user'  => $wppa_member,
			'team'  => (int) $wppa_team_id,
			'role'  => WPPA_Teams::role( $wppa_member->ID ),
			'files' => WPPA_Participant_Files::files( $wppa_member->ID ),
		);
	}
}

$wppa_scores = WPPA_Olympiad::is_active()
	? WPPA_Olympiad::summary_map( array_keys( $wppa_participants ) )
	: array();
?>

<section class="wppa-card wppa-participants">
	<h2 class="wppa-title">
		<?php esc_html_e( 'Участники и баллы', 'wp-personal-account' ); ?>
	</h2>
	<p class="wppa-hint">
		<?php esc_html_e( 'Участники команд без наставников: баллы и процент за тесты, документы конкретному участнику. Участник увидит свой файл в разделе «Файлы».', 'wp-personal-account' ); ?>
	</p>

	<?php if ( ! $wppa_participants ) : ?>
		<p class="wppa-notice wppa-notice--info"><?php esc_html_e( 'Участников пока нет: команды ещё не собраны.', 'wp-personal-account' ); ?></p>
	<?php else : ?>

		<?php if ( ! $wppa_scores ) : ?>
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
						<th scope="col"><?php esc_html_e( 'Документы', 'wp-personal-account' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wppa_participants as $wppa_participant_id => $wppa_row ) : ?>
						<?php
						$wppa_score = isset( $wppa_scores[ $wppa_participant_id ] ) ? $wppa_scores[ $wppa_participant_id ] : null;
						$wppa_pct   = $wppa_score ? (float) $wppa_score['percentage'] : 0.0;
						$wppa_decimals = ( abs( $wppa_pct - round( $wppa_pct ) ) < 0.05 ) ? 0 : 1;
						$wppa_team  = get_post( $wppa_row['team'] );
						?>
						<tr>
							<td>
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
							<td>
								<?php if ( $wppa_score ) : ?>
									<?php echo esc_html( (string) (int) $wppa_score['tests'] ); ?>
									<?php if ( ! empty( $wppa_score['pending'] ) ) : ?>
										<br /><span class="wppa-hint"><?php esc_html_e( 'на проверке', 'wp-personal-account' ); ?>: <?php echo esc_html( (string) (int) $wppa_score['pending'] ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									<span class="wppa-hint"><?php echo esc_html( WPPA_Olympiad::is_active() ? '0' : '—' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $wppa_score ) : ?>
									<?php echo esc_html( (int) $wppa_score['score'] . ' / ' . (int) $wppa_score['max_score'] ); ?>
								<?php else : ?>
									<span class="wppa-hint">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $wppa_score ) : ?>
									<span class="wppa-badge <?php echo $wppa_pct >= 60 ? 'wppa-badge--ok' : ( $wppa_pct > 0 ? 'wppa-badge--warn' : '' ); ?>">
										<?php echo esc_html( number_format_i18n( $wppa_pct, $wppa_decimals ) . ' %' ); ?>
									</span>
								<?php else : ?>
									<span class="wppa-hint">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $wppa_row['files'] ) : ?>
									<ul class="wppa-participants__files">
										<?php foreach ( $wppa_row['files'] as $wppa_file_key => $wppa_file ) : ?>
											<li>
												<a class="wppa-link" href="<?php echo esc_url( WPPA_Participant_Files::download_url( $wppa_participant_id, $wppa_file_key ) ); ?>">
													<?php echo esc_html( $wppa_file['name'] ); ?>
												</a>
												<span class="wppa-hint"><?php echo esc_html( WPPA_Participant_Files::size_label( $wppa_file['size'] ) ); ?></span>
												<form method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'files' ) ); ?>" class="wppa-participants__remove">
													<?php wp_nonce_field( 'wppa_participant_file', 'wppa_nonce' ); ?>
													<input type="hidden" name="wppa_action" value="participant_file" />
													<input type="hidden" name="participant_file_action" value="remove" />
													<input type="hidden" name="participant_id" value="<?php echo esc_attr( (string) $wppa_participant_id ); ?>" />
													<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_row['team'] ); ?>" />
													<input type="hidden" name="file_key" value="<?php echo esc_attr( $wppa_file_key ); ?>" />
													<button type="submit" class="wppa-button wppa-button--ghost wppa-button--sm"><?php esc_html_e( 'Удалить', 'wp-personal-account' ); ?></button>
												</form>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>

								<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'files' ) ); ?>" class="wppa-participants__upload">
									<?php wp_nonce_field( 'wppa_participant_file', 'wppa_nonce' ); ?>
									<input type="hidden" name="wppa_action" value="participant_file" />
									<input type="hidden" name="participant_file_action" value="upload" />
									<input type="hidden" name="participant_id" value="<?php echo esc_attr( (string) $wppa_participant_id ); ?>" />
									<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_row['team'] ); ?>" />
									<label class="wppa-participants__label" for="wppa-participant-file-<?php echo esc_attr( (string) $wppa_participant_id ); ?>">
										<?php esc_html_e( 'Прикрепить документы', 'wp-personal-account' ); ?>
									</label>
									<input
										type="file"
										id="wppa-participant-file-<?php echo esc_attr( (string) $wppa_participant_id ); ?>"
										name="participant_files[]"
										multiple
									/>
									<button type="submit" class="wppa-button wppa-button--primary wppa-button--sm">
										<?php esc_html_e( 'Прикрепить', 'wp-personal-account' ); ?>
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
