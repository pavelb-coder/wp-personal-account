<?php
/**
 * Админ-панель: страница «Команды».
 *
 * Доступные переменные: $rows, $max_members, $total_users, $notice.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_in_teams    = 0;
$wppa_below_min   = 0;

foreach ( $rows as $wppa_row ) {
	$wppa_in_teams += (int) $wppa_row['members'];

	if ( ! empty( $wppa_row['missing'] ) ) {
		$wppa_below_min++;
	}
}
$wppa_available = isset( $total_users['total_users'] ) ? (int) $total_users['total_users'] : 0;
?>
<div class="wrap wppa-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Команды', 'wp-personal-account' ); ?></h1>
	<hr class="wp-header-end" />

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'success' === $notice[0] ? 'success' : 'warning' ); ?> is-dismissible">
			<p><?php echo esc_html( $notice[1] ); ?></p>
		</div>
	<?php endif; ?>

	<div class="wppa-admin__cards">
		<div class="wppa-admin__card">
			<span class="wppa-admin__card-label"><?php esc_html_e( 'Команд', 'wp-personal-account' ); ?></span>
			<span class="wppa-admin__card-value"><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?></span>
		</div>
		<div class="wppa-admin__card">
			<span class="wppa-admin__card-label"><?php esc_html_e( 'Пользователей в командах', 'wp-personal-account' ); ?></span>
			<span class="wppa-admin__card-value"><?php echo esc_html( number_format_i18n( $wppa_in_teams ) ); ?></span>
		</div>
		<div class="wppa-admin__card">
			<span class="wppa-admin__card-label"><?php esc_html_e( 'Ниже минимума', 'wp-personal-account' ); ?></span>
			<span class="wppa-admin__card-value"><?php echo esc_html( number_format_i18n( $wppa_below_min ) ); ?></span>
			<span class="wppa-admin__card-note">
				<?php
				printf(
					/* translators: 1: минимум, 2: максимум */
					esc_html__( 'минимум %1$d · лимит %2$d', 'wp-personal-account' ),
					(int) $min_members,
					(int) $max_members
				);
				?>
			</span>
		</div>

		<div class="wppa-admin__card">
			<span class="wppa-admin__card-label"><?php esc_html_e( 'Лимит участников', 'wp-personal-account' ); ?></span>
			<span class="wppa-admin__card-value"><?php echo esc_html( number_format_i18n( $max_members ) ); ?></span>
			<span class="wppa-admin__card-note">
				<?php
				printf(
					/* translators: 1: количество участников, 2: всего пользователей */
					esc_html__( 'охват: %1$d из %2$d', 'wp-personal-account' ),
					(int) $wppa_in_teams,
					(int) $wppa_available
				);
				?>
			</span>
		</div>
	</div>

	<?php if ( empty( $rows ) ) : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'Команд пока нет — они появятся, когда пользователи создадут их во вкладке «Команда».', 'wp-personal-account' ); ?></p></div>
	<?php else : ?>
		<table class="widefat striped wppa-admin__table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Команда', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Капитан', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Участников (без наставников)', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Наставники', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Мин. / лимит', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Создана', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Действия', 'wp-personal-account' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $wppa_row ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $wppa_row['name'] ); ?></strong>
							<?php if ( ! empty( $wppa_row['stage2'] ) ) : ?>
								<span class="wppa-badge wppa-badge--stage2"><?php esc_html_e( 'второй этап', 'wp-personal-account' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $wppa_row['owner_id'] ) : ?>
								<a href="<?php echo esc_url( get_edit_user_link( $wppa_row['owner_id'] ) ); ?>"><?php echo esc_html( $wppa_row['owner'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $wppa_row['owner'] ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php
							printf(
								/* translators: 1: участников (без наставников), 2: лимит */
								esc_html__( '%1$d / %2$d', 'wp-personal-account' ),
								(int) $wppa_row['members'],
								(int) $wppa_row['limit']
							);
							?>
							<?php if ( $wppa_row['people'] > $wppa_row['members'] ) : ?>
								<br />
								<span class="wppa-admin__muted">
									<?php
									printf(
										/* translators: 1: всего человек в команде, 2: наставников */
										esc_html__( 'всего человек: %1$d (наставников: %2$d)', 'wp-personal-account' ),
										(int) $wppa_row['people'],
										(int) $wppa_row['mentors_count']
									);
									?>
								</span>
							<?php endif; ?>
							<?php if ( $wppa_row['missing'] > 0 ) : ?>
								<br />
								<span class="wppa-badge wppa-badge--warn">
									<?php
									printf(
										/* translators: 1: сколько не хватает, 2: минимум */
										esc_html__( 'недобор: %1$d до %2$d', 'wp-personal-account' ),
										(int) $wppa_row['missing'],
										(int) $wppa_row['min']
									);
									?>
								</span>
							<?php endif; ?>
							<?php if ( ! empty( $wppa_row['gaps']['mentor'] ) ) : ?>
								<br />
								<span class="wppa-badge wppa-badge--warn"><?php esc_html_e( 'нет наставника', 'wp-personal-account' ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $wppa_row['complete'] ) ) : ?>
								<br />
								<span class="wppa-badge wppa-badge--ok"><?php esc_html_e( 'сформирована', 'wp-personal-account' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! empty( $wppa_row['mentors'] ) ) : ?>
								<div class="wppa-mentors">
									<?php foreach ( $wppa_row['mentors'] as $wppa_mentor ) : ?>
										<span class="wppa-badge wppa-badge--mentor">
											<?php echo esc_html( $wppa_mentor->display_name ); ?>
										</span>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wppa-inline-form">
											<?php wp_nonce_field( 'wppa_tool' ); ?>
											<input type="hidden" name="action" value="wppa_tool" />
											<input type="hidden" name="wppa_tool" value="remove_team_mentor" />
											<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_row['id'] ); ?>" />
											<input type="hidden" name="member_id" value="<?php echo esc_attr( (string) $wppa_mentor->ID ); ?>" />
											<button type="submit" class="button button-small"><?php esc_html_e( 'снять', 'wp-personal-account' ); ?></button>
										</form>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<?php
							// Кандидаты: участники команды, кроме капитана и действующих наставников.
							$wppa_candidates = array();

							foreach ( $wppa_row['members_list'] as $wppa_member ) {
								if ( 'owner' === WPPA_Teams::role( $wppa_member->ID ) || 'mentor' === WPPA_Teams::role( $wppa_member->ID ) ) {
									continue;
								}
								$wppa_candidates[] = $wppa_member;
							}
							?>

							<?php if ( $wppa_candidates ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wppa-inline-form">
									<?php wp_nonce_field( 'wppa_tool' ); ?>
									<input type="hidden" name="action" value="wppa_tool" />
									<input type="hidden" name="wppa_tool" value="set_team_mentor" />
									<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_row['id'] ); ?>" />
									<label>
										<span class="screen-reader-text"><?php esc_html_e( 'Кого сделать наставником', 'wp-personal-account' ); ?></span>
										<select name="member_id">
											<?php foreach ( $wppa_candidates as $wppa_candidate ) : ?>
												<option value="<?php echo esc_attr( (string) $wppa_candidate->ID ); ?>"><?php echo esc_html( $wppa_candidate->display_name ); ?></option>
											<?php endforeach; ?>
										</select>
									</label>
									<button type="submit" class="button button-small"><?php esc_html_e( 'Наставник', 'wp-personal-account' ); ?></button>
								</form>
							<?php elseif ( empty( $wppa_row['mentors'] ) ) : ?>
								<span class="wppa-admin__muted"><?php esc_html_e( 'нет участников', 'wp-personal-account' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wppa-inline-form wppa-team-limits">
								<?php wp_nonce_field( 'wppa_tool' ); ?>
								<input type="hidden" name="action" value="wppa_tool" />
								<input type="hidden" name="wppa_tool" value="update_team_settings" />
								<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_row['id'] ); ?>" />

								<label>
									<span class="screen-reader-text"><?php esc_html_e( 'Минимум участников', 'wp-personal-account' ); ?></span>
									<input
										type="number"
										name="team_min"
										min="1"
										max="100"
										class="small-text"
										value="<?php echo esc_attr( $wppa_row['own_min'] ? (string) $wppa_row['own_min'] : '' ); ?>"
										placeholder="<?php echo esc_attr( (string) $min_members ); ?>"
										title="<?php esc_attr_e( 'Минимум участников. Пусто — общий из настроек', 'wp-personal-account' ); ?>"
									/>
								</label>

								<label>
									<span class="screen-reader-text"><?php esc_html_e( 'Лимит участников', 'wp-personal-account' ); ?></span>
									<input
										type="number"
										name="team_limit"
										min="2"
										max="100"
										class="small-text"
										value="<?php echo esc_attr( $wppa_row['own_limit'] ? (string) $wppa_row['own_limit'] : '' ); ?>"
										placeholder="<?php echo esc_attr( (string) $max_members ); ?>"
										title="<?php esc_attr_e( 'Лимит участников. Пусто — общий из настроек', 'wp-personal-account' ); ?>"
									/>
								</label>

								<button type="submit" class="button button-small"><?php esc_html_e( 'Сохранить', 'wp-personal-account' ); ?></button>
							</form>
							<?php if ( $wppa_row['own_limit'] || $wppa_row['own_min'] ) : ?>
								<span class="wppa-badge"><?php esc_html_e( 'свои значения', 'wp-personal-account' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo $wppa_row['created'] ? esc_html( date_i18n( get_option( 'date_format' ), $wppa_row['created'] ) ) : '—'; ?>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wppa-inline-form">
								<?php wp_nonce_field( 'wppa_tool' ); ?>
								<input type="hidden" name="action" value="wppa_tool" />
								<input type="hidden" name="wppa_tool" value="dissolve_team" />
								<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_row['id'] ); ?>" />
								<button type="submit" class="button button-small" data-wppa-confirm="<?php esc_attr_e( 'Распустить команду? Участники выйдут из неё.', 'wp-personal-account' ); ?>">
									<?php esc_html_e( 'Распустить', 'wp-personal-account' ); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<section class="wppa-admin__panel" style="margin-top:20px;">
		<h2><?php esc_html_e( 'Как это работает', 'wp-personal-account' ); ?></h2>
		<p class="wppa-admin__muted">
			<?php esc_html_e( 'Пользователь создаёт команду и становится капитаном: он приглашает участников ссылкой (действует 7 дней) или письмом, исключает их и распускает команду. Участник может выйти самостоятельно.', 'wp-personal-account' ); ?>
		</p>
		<p class="wppa-admin__muted">
			<?php esc_html_e( 'Наставник — участник команды с расширенными правами: приглашает и исключает участников (кроме капитана и других наставников), но не может переименовать или распустить команду. В кабинете наставника назначает капитан, здесь — администратор. Наставник не считается участником: он не занимает место в лимите и не идёт в зачёт минимума.', 'wp-personal-account' ); ?>
		</p>
		<p class="wppa-admin__muted">
			<?php esc_html_e( 'Минимум и лимит можно задать для каждой команды отдельно — два поля в колонке «Мин. / лимит». Пустое поле означает общее значение из настроек; чтобы вернуть общее, очистите поле и сохраните. Минимум не может превышать лимит.', 'wp-personal-account' ); ?>
		</p>
	</section>
</div>
