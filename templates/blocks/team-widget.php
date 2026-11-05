<?php
/**
 * Блок «Команда».
 *
 * Доступные переменные: $user, $tabs, $block_title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_my_team_id = (int) WPPA_Teams::team_id( $user->ID );
$wppa_is_curator = WPPA_Teams::is_curator( $user->ID );
$wppa_team_id    = $wppa_my_team_id;

// Куратор открывает чужую команду через «Управлять» (?wppa_team=ID).
$wppa_curator_team_id = $wppa_is_curator ? WPPA_Teams::viewing_team_id( $user->ID ) : 0;
$wppa_curator_view    = $wppa_curator_team_id && $wppa_curator_team_id !== $wppa_my_team_id;

if ( $wppa_curator_view ) {
	$wppa_team_id = $wppa_curator_team_id;
}

$wppa_team      = $wppa_team_id ? WPPA_Teams::get( $wppa_team_id ) : null;
$wppa_has_team  = $wppa_team_id > 0;
$wppa_is_owner  = ! $wppa_curator_view && $wppa_team_id && WPPA_Teams::is_owner( $user->ID );
$wppa_is_mentor = ! $wppa_curator_view && $wppa_team_id && WPPA_Teams::is_mentor( $user->ID );

// Права на управление командой: капитан и наставник — на свою, куратор — на любую.
// Считаем их по номеру команды, а не по загруженным данным: карточки не должны
// пропадать, если объект команды почему-то не подгрузился.
$wppa_can_manage = $wppa_team_id && WPPA_Teams::can_manage_team( $wppa_team_id, $user->ID );
$wppa_is_manager = $wppa_can_manage;

// Приглашать участников и переименовывать команду может только капитан или наставник
// этой команды. Куратору эти действия недоступны — он работает с составом.
$wppa_can_invite = $wppa_team_id && WPPA_Teams::is_manager_of( $wppa_team_id, $user->ID );
$wppa_can_rename = $wppa_team_id && WPPA_Teams::is_captain_of( $wppa_team_id, $user->ID );
$wppa_team_name  = $wppa_team ? (string) $wppa_team['name'] : ( $wppa_team_id ? (string) get_the_title( $wppa_team_id ) : '' );
$wppa_created    = $wppa_team && ! empty( $wppa_team['created'] ) ? (int) $wppa_team['created'] : 0;
$wppa_team_rows  = $wppa_team && isset( $wppa_team['members'] ) ? (array) $wppa_team['members'] : array();
$wppa_my_role    = $wppa_curator_view ? 'curator' : ( $wppa_team_id ? WPPA_Teams::role( $user->ID ) : '' );
$wppa_stage2     = $wppa_team_id ? WPPA_Teams::stage2( $wppa_team_id ) : array();
$wppa_stage2_on  = array() !== $wppa_stage2;

// Скрытое поле team_id: без него куратор не поймёт, к какой команде относится действие.
$wppa_team_field = ( $wppa_is_curator && $wppa_team_id )
	? '<input type="hidden" name="team_id" value="' . (int) $wppa_team_id . '" />'
	: '';
$wppa_max      = WPPA_Teams::team_limit( $wppa_team_id );
$wppa_min      = WPPA_Teams::team_min( $wppa_team_id );
$wppa_missing  = WPPA_Teams::missing_members( $wppa_team_id );
$wppa_members  = $wppa_team_id ? WPPA_Teams::members_count( $wppa_team_id ) : 0;
$wppa_mentors  = $wppa_team_id ? WPPA_Teams::mentors_count( $wppa_team_id ) : 0;
$wppa_gaps     = $wppa_team_id ? WPPA_Teams::gaps( $wppa_team_id ) : array( 'members' => 0, 'mentor' => false );
$wppa_complete = $wppa_team_id && WPPA_Teams::is_complete( $wppa_team_id );
?>

<h2 class="wppa-title">
	<?php echo isset( $block_title ) && '' !== $block_title ? esc_html( $block_title ) : esc_html__( 'Команда', 'wp-personal-account' ); ?>
</h2>

<?php if ( $wppa_is_curator ) : ?>
	<?php
	$wppa_all_teams = WPPA_Teams::all_teams();
	$wppa_team_list  = WPPA_Plugin::tab_url( 'team' );
	?>
	<section class="wppa-card wppa-curator">
		<h3 class="wppa-subtitle"><?php esc_html_e( 'Куратор: все команды', 'wp-personal-account' ); ?></h3>
		<p class="wppa-hint">
			<?php esc_html_e( 'Вы видите все команды сайта и состояние каждой. Состав ведут капитан и наставник: приглашают участников, исключают их, назначают наставников и переименовывают команду. Ваша задача — прикреплять для наставников документы.', 'wp-personal-account' ); ?>
		</p>

		<?php if ( empty( $wppa_all_teams ) ) : ?>
			<div class="wppa-notice wppa-notice--info"><?php esc_html_e( 'Команд пока нет.', 'wp-personal-account' ); ?></div>
		<?php else : ?>
			<div class="wppa-table-wrap">
				<table class="wppa-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Команда', 'wp-personal-account' ); ?></th>
							<th><?php esc_html_e( 'Наставник', 'wp-personal-account' ); ?></th>
							<th><?php esc_html_e( 'Состояние', 'wp-personal-account' ); ?></th>
							<th><?php esc_html_e( 'Второй этап', 'wp-personal-account' ); ?></th>
							<th><?php esc_html_e( 'Действие', 'wp-personal-account' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $wppa_all_teams as $wppa_row ) : ?>
							<tr<?php echo (int) $wppa_row['id'] === (int) $wppa_team_id ? ' class="wppa-curator__current"' : ''; ?>>
								<td data-label="<?php esc_attr_e( 'Команда', 'wp-personal-account' ); ?>">
									<strong><?php echo esc_html( $wppa_row['name'] ); ?></strong>
									<?php if ( (int) $wppa_row['id'] === $wppa_my_team_id ) : ?>
										<span class="wppa-hint">(<?php esc_html_e( 'ваша команда', 'wp-personal-account' ); ?>)</span>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Наставник', 'wp-personal-account' ); ?>">
									<?php if ( ! empty( $wppa_row['mentor'] ) ) : ?>
										<strong><?php echo esc_html( $wppa_row['mentor'] ); ?></strong>
										<?php if ( ! empty( $wppa_row['mentor_email'] ) ) : ?>
											<br />
											<span class="wppa-hint">
												<a href="mailto:<?php echo esc_attr( $wppa_row['mentor_email'] ); ?>"><?php echo esc_html( $wppa_row['mentor_email'] ); ?></a>
											</span>
										<?php endif; ?>
									<?php else : ?>
										<span class="wppa-hint"><?php esc_html_e( 'не назначен', 'wp-personal-account' ); ?></span>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Состояние', 'wp-personal-account' ); ?>">
									<?php if ( $wppa_row['complete'] ) : ?>
										<span class="wppa-badge wppa-badge--ok"><?php esc_html_e( 'сформирована', 'wp-personal-account' ); ?></span>
									<?php elseif ( $wppa_row['missing'] > 0 ) : ?>
										<span class="wppa-badge wppa-badge--warn">
											<?php
											printf(
												/* translators: %d: сколько не хватает */
												esc_html__( 'не хватает %d', 'wp-personal-account' ),
												(int) $wppa_row['missing']
											);
											?>
										</span>
									<?php else : ?>
										<span class="wppa-badge"><?php esc_html_e( 'нет наставника', 'wp-personal-account' ); ?></span>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Второй этап', 'wp-personal-account' ); ?>">
									<?php if ( ! empty( $wppa_row['stage2'] ) ) : ?>
										<form method="post" action="<?php echo esc_url( $wppa_team_list ); ?>" class="wppa-inline-form">
											<?php wp_nonce_field( 'wppa_team', 'wppa_nonce' ); ?>
											<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_row['id'] ); ?>" />
											<input type="hidden" name="wppa_action" value="team" />
											<input type="hidden" name="wppa_team_action" value="stage2_remove" />
											<input type="hidden" name="wppa_stay" value="list" />
											<span class="wppa-badge wppa-badge--stage2"><?php esc_html_e( 'прошла', 'wp-personal-account' ); ?></span>
											<button type="submit" class="wppa-link-button"><?php esc_html_e( 'Снять', 'wp-personal-account' ); ?></button>
										</form>
									<?php else : ?>
										<form method="post" action="<?php echo esc_url( $wppa_team_list ); ?>" class="wppa-inline-form">
											<?php wp_nonce_field( 'wppa_team', 'wppa_nonce' ); ?>
											<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_row['id'] ); ?>" />
											<input type="hidden" name="wppa_action" value="team" />
											<input type="hidden" name="wppa_team_action" value="stage2_add" />
											<input type="hidden" name="wppa_stay" value="list" />
											<button type="submit" class="wppa-button wppa-button--ghost"><?php esc_html_e( 'Отметить', 'wp-personal-account' ); ?></button>
										</form>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Действие', 'wp-personal-account' ); ?>">
									<?php if ( (int) $wppa_row['id'] === (int) $wppa_team_id && $wppa_curator_view ) : ?>
										<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( $wppa_team_list ); ?>"><?php esc_html_e( 'Закрыть', 'wp-personal-account' ); ?></a>
									<?php else : ?>
										<a class="wppa-button" href="<?php echo esc_url( add_query_arg( array( 'wppa_tab' => 'files', 'wppa_team' => (int) $wppa_row['id'] ), WPPA_Plugin::tab_url( 'files' ) ) ); ?>"><?php esc_html_e( 'Добавить файлы', 'wp-personal-account' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( $wppa_curator_view ) : ?>
	<div class="wppa-notice wppa-notice--info">
		<?php
		printf(
			/* translators: 1: название команды, 2: ссылка на список команд */
			esc_html__( 'Вы управляете командой «%1$s» как куратор: состав и файлы. Приглашения и переименование — у капитана и наставника. %2$s', 'wp-personal-account' ),
			esc_html( $wppa_team_name ),
			'<a href="' . esc_url( WPPA_Plugin::tab_url( 'team' ) ) . '">' . esc_html__( 'Вернуться к списку команд', 'wp-personal-account' ) . '</a>'
		);
		?>
	</div>
<?php endif; ?>

<?php if ( ! $wppa_has_team ) : ?>


	<?php if ( $wppa_is_curator ) : ?>

		<div class="wppa-notice wppa-notice--info">
			<?php esc_html_e( 'Вы куратор и не состоите в команде — это нормально: куратор не занимает место в составе. Выберите команду в списке выше, чтобы посмотреть и поправить её.', 'wp-personal-account' ); ?>
		</div>

	<?php else : ?>

	<div class="wppa-notice wppa-notice--info">
		<?php esc_html_e( 'Вы пока не состоите в команде. Создайте свою или присоединитесь по ссылке-приглашению.', 'wp-personal-account' ); ?>
	</div>

	<div class="wppa-team-grid">
		<section class="wppa-card">
			<h3 class="wppa-subtitle"><?php esc_html_e( 'Создать команду', 'wp-personal-account' ); ?></h3>
			<p class="wppa-hint"><?php esc_html_e( 'Вы станете капитаном: сможете приглашать участников и управлять составом.', 'wp-personal-account' ); ?></p>

			<form class="wppa-form" method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>">
				<?php wp_nonce_field( 'wppa_team', 'wppa_nonce' ); ?>
				<?php echo $wppa_team_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- собранный статический input. ?>
				<input type="hidden" name="wppa_action" value="team" />
				<input type="hidden" name="wppa_team_action" value="create" />

				<p class="wppa-field">
					<label for="wppa-team-name"><?php esc_html_e( 'Название команды', 'wp-personal-account' ); ?></label>
					<input type="text" id="wppa-team-name" name="team_name" maxlength="100" required />
				</p>

				<button type="submit" class="wppa-button wppa-button--primary"><?php esc_html_e( 'Создать команду', 'wp-personal-account' ); ?></button>
			</form>
		</section>

		<section class="wppa-card">
			<h3 class="wppa-subtitle"><?php esc_html_e( 'Присоединиться', 'wp-personal-account' ); ?></h3>
			<p class="wppa-hint"><?php esc_html_e( 'Откройте ссылку-приглашение от капитана команды — вы присоединитесь автоматически.', 'wp-personal-account' ); ?></p>

			<form class="wppa-form" method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>">
				<?php wp_nonce_field( 'wppa_team', 'wppa_nonce' ); ?>
				<?php echo $wppa_team_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- собранный статический input. ?>
				<input type="hidden" name="wppa_action" value="team" />
				<input type="hidden" name="wppa_team_action" value="join_code" />

				<p class="wppa-field">
					<label for="wppa-team-code"><?php esc_html_e( 'Код из приглашения', 'wp-personal-account' ); ?></label>
					<input type="text" id="wppa-team-code" name="team_code" autocomplete="off" placeholder="a1b2c3…" />
					<span class="wppa-hint"><?php esc_html_e( 'Можно вставить ссылку целиком — код найдётся автоматически.', 'wp-personal-account' ); ?></span>
				</p>

				<button type="submit" class="wppa-button wppa-button--ghost"><?php esc_html_e( 'Присоединиться', 'wp-personal-account' ); ?></button>
			</form>
		</section>
	</div>

	<?php endif; ?>

<?php else : ?>

	<?php if ( ! $wppa_team ) : ?>
		<div class="wppa-notice wppa-notice--info">
			<?php esc_html_e( 'Не удалось загрузить данные команды. Обновите страницу или вернитесь к списку команд.', 'wp-personal-account' ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $wppa_curator_view ) : ?>
		<div class="wppa-notice wppa-notice--info">
			<?php esc_html_e( 'Список участников ведут капитан и наставник — куратор в состав не вмешивается. Ниже можно прикрепить документы для наставника.', 'wp-personal-account' ); ?>
		</div>
	<?php endif; ?>

	<div class="wppa-card wppa-team-head">
		<div>
			<h3 class="wppa-team-name"><?php echo esc_html( $wppa_team_name ); ?></h3>
			<p class="wppa-hint">
				<?php
				printf(
					/* translators: 1: количество участников (без наставников), 2: лимит, 3: минимум */
					esc_html__( 'Участников: %1$d из %2$d (минимум %3$d)', 'wp-personal-account' ),
					(int) $wppa_members,
					$wppa_max,
					$wppa_min
				);
				if ( $wppa_mentors > 0 ) {
					echo ' · ';
					printf(
						/* translators: %d: количество наставников */
						esc_html__( 'наставников: %d (в лимит не входят)', 'wp-personal-account' ),
						(int) $wppa_mentors
					);
				}
				if ( $wppa_created ) {
					echo ' · ';
					printf(
						/* translators: %s: дата создания */
						esc_html__( 'создана %s', 'wp-personal-account' ),
						esc_html( date_i18n( get_option( 'date_format' ), $wppa_created ) )
					);
				}
				?>
			</p>
		</div>
		<span class="wppa-badge <?php echo esc_attr( 'owner' === $wppa_my_role ? 'wppa-badge--owner' : ( 'mentor' === $wppa_my_role ? 'wppa-badge--mentor' : '' ) ); ?>">
			<?php echo esc_html( WPPA_Teams::role_label( $wppa_my_role ) ); ?>
		</span>
	</div>

	<?php if ( $wppa_stage2_on ) : ?>
		<div class="wppa-card wppa-stage2">
			<p class="wppa-hint">
				<span class="wppa-badge wppa-badge--stage2"><?php esc_html_e( 'Прошла во второй этап', 'wp-personal-account' ); ?></span>
				<?php
				if ( ! empty( $wppa_stage2['time'] ) ) {
					printf(
						/* translators: %s: дата */
						esc_html__( 'Отмечено %s.', 'wp-personal-account' ),
						esc_html( date_i18n( get_option( 'date_format' ), (int) $wppa_stage2['time'] ) )
					);
				}
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $wppa_is_curator && $wppa_team_id ) : ?>
		<div class="wppa-hint wppa-stage2-toggle">
			<form method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>" class="wppa-inline-form">
				<?php wp_nonce_field( 'wppa_team', 'wppa_nonce' ); ?>
				<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_team_id ); ?>" />
				<input type="hidden" name="wppa_action" value="team" />
				<input type="hidden" name="wppa_team_action" value="<?php echo $wppa_stage2_on ? 'stage2_remove' : 'stage2_add'; ?>" />
				<button type="submit" class="wppa-link-button">
					<?php echo $wppa_stage2_on ? esc_html__( 'Снять отметку «второй этап»', 'wp-personal-account' ) : esc_html__( 'Отметить: команда прошла во второй этап', 'wp-personal-account' ); ?>
				</button>
			</form>
		</div>
	<?php endif; ?>

	<?php if ( ! $wppa_curator_view ) : ?>

	<?php if ( $wppa_missing > 0 ) : ?>
		<div class="wppa-notice wppa-notice--info">
			<?php
			printf(
				/* translators: 1: сколько не хватает, 2: минимум */
				esc_html__( 'Команда ещё не набрана: не хватает %1$d участник(ов) до минимума %2$d (наставники в зачёт не идут). Пригласите коллег по ссылке ниже.', 'wp-personal-account' ),
				(int) $wppa_missing,
				(int) $wppa_min
			);
			?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $wppa_gaps['mentor'] ) ) : ?>
		<div class="wppa-notice wppa-notice--info">
			<?php esc_html_e( 'В команде нет наставника. Без наставника команда считается несформированной — назначьте наставника из числа участников.', 'wp-personal-account' ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $wppa_complete ) : ?>
		<p class="wppa-hint">
			<span class="wppa-badge wppa-badge--ok"><?php esc_html_e( 'Команда сформирована', 'wp-personal-account' ); ?></span>
		</p>
	<?php endif; ?>

	<?php if ( $wppa_mentors > 0 ) : ?>
		<p class="wppa-hint">
			<?php esc_html_e( 'Наставники входят в состав, но не считаются участниками: не занимают место в лимите и не идут в зачёт минимума.', 'wp-personal-account' ); ?>
		</p>
	<?php endif; ?>

	<?php if ( $wppa_is_mentor ) : ?>
		<div class="wppa-notice wppa-notice--info">
			<?php esc_html_e( 'Вы наставник команды: можете приглашать и исключать участников. Переименовать или распустить команду может только капитан.', 'wp-personal-account' ); ?>
			<a href="<?php echo esc_url( WPPA_Plugin::tab_url( 'files' ) ); ?>"><?php esc_html_e( 'Документы от куратора — на вкладке «Файлы».', 'wp-personal-account' ); ?></a>
		</div>
	<?php endif; ?>

	<h3 class="wppa-subtitle"><?php esc_html_e( 'Состав команды', 'wp-personal-account' ); ?></h3>

	<div class="wppa-table-wrap">
		<table class="wppa-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Участник', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Email', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Роль', 'wp-personal-account' ); ?></th>
					<?php if ( $wppa_is_manager ) : ?>
						<th><?php esc_html_e( 'Действия', 'wp-personal-account' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $wppa_team_rows as $wppa_member ) : ?>
					<?php $wppa_member_role = WPPA_Teams::role( $wppa_member->ID ); ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Участник', 'wp-personal-account' ); ?>">
							<?php echo get_avatar( $wppa_member->ID, 28, '', '', array( 'class' => 'wppa-avatar__img wppa-avatar--inline' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<strong><?php echo esc_html( $wppa_member->display_name ); ?></strong>
							<?php if ( (int) $wppa_member->ID === (int) $user->ID ) : ?>
								<span class="wppa-hint">(<?php esc_html_e( 'это вы', 'wp-personal-account' ); ?>)</span>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Email', 'wp-personal-account' ); ?>"><?php echo esc_html( $wppa_member->user_email ); ?></td>
						<td data-label="<?php esc_attr_e( 'Роль', 'wp-personal-account' ); ?>">
							<span class="wppa-badge <?php echo esc_attr( 'owner' === $wppa_member_role ? 'wppa-badge--owner' : ( 'mentor' === $wppa_member_role ? 'wppa-badge--mentor' : '' ) ); ?>">
								<?php echo esc_html( WPPA_Teams::role_label( $wppa_member_role ) ); ?>
							</span>
						</td>
						<?php if ( $wppa_is_manager ) : ?>
							<?php
							$wppa_can_act = 'owner' !== $wppa_member_role
								&& ( $wppa_is_owner || $wppa_curator_view || 'member' === $wppa_member_role );
							?>
							<td data-label="<?php esc_attr_e( 'Действия', 'wp-personal-account' ); ?>">
								<?php if ( $wppa_can_act ) : ?>
									<div class="wppa-actions">
										<?php if ( $wppa_is_owner || $wppa_curator_view ) : ?>
											<form method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>" class="wppa-inline-form">
												<?php wp_nonce_field( 'wppa_team', 'wppa_nonce' ); ?>
												<?php echo $wppa_team_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- собранный статический input. ?>
												<input type="hidden" name="wppa_action" value="team" />
												<input type="hidden" name="wppa_team_action" value="<?php echo 'mentor' === $wppa_member_role ? 'dismiss_mentor' : 'make_mentor'; ?>" />
												<input type="hidden" name="member_id" value="<?php echo esc_attr( (string) $wppa_member->ID ); ?>" />
												<button type="submit" class="wppa-link-button">
													<?php echo 'mentor' === $wppa_member_role ? esc_html__( 'Снять наставника', 'wp-personal-account' ) : esc_html__( 'Сделать наставником', 'wp-personal-account' ); ?>
												</button>
											</form>
										<?php endif; ?>

										<form method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>" class="wppa-inline-form">
											<?php wp_nonce_field( 'wppa_team', 'wppa_nonce' ); ?>
											<?php echo $wppa_team_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- собранный статический input. ?>
											<input type="hidden" name="wppa_action" value="team" />
											<input type="hidden" name="wppa_team_action" value="remove_member" />
											<input type="hidden" name="member_id" value="<?php echo esc_attr( (string) $wppa_member->ID ); ?>" />
											<button type="submit" class="wppa-link-button"><?php esc_html_e( 'Исключить', 'wp-personal-account' ); ?></button>
										</form>
									</div>
								<?php else : ?>
									<span class="wppa-hint">—</span>
								<?php endif; ?>
							</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php endif; ?>

	<?php
	// Файлы куратора: куратор прикрепляет, наставник команды скачивает.
	$wppa_files = class_exists( 'WPPA_Curator_Files' ) ? WPPA_Curator_Files::files( $wppa_team_id ) : array();
	?>

	<?php if ( $wppa_is_curator && $wppa_team_id ) : ?>

		<section class="wppa-card wppa-curator-files">
			<h3 class="wppa-subtitle"><?php esc_html_e( 'Файлы для наставника', 'wp-personal-account' ); ?></h3>
			<p class="wppa-hint">
				<?php
				printf(
					/* translators: %d: максимальное число файлов */
					esc_html__( 'Документы видит только наставник этой команды и вы. Наставник может прикреплять свои файлы — они появятся в этом списке с пометкой «наставник». Разрешены документы, таблицы, презентации, архивы и изображения, до 10 МБ, не больше %d файлов.', 'wp-personal-account' ),
					(int) WPPA_Curator_Files::MAX_FILES
				);
				?>
			</p>

			<?php if ( $wppa_files ) : ?>
				<div class="wppa-table-wrap">
					<table class="wppa-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Файл', 'wp-personal-account' ); ?></th>
								<th><?php esc_html_e( 'От кого', 'wp-personal-account' ); ?></th>
								<th><?php esc_html_e( 'Размер', 'wp-personal-account' ); ?></th>
								<th><?php esc_html_e( 'Прикреплён', 'wp-personal-account' ); ?></th>
								<th><?php esc_html_e( 'Действия', 'wp-personal-account' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $wppa_files as $wppa_file ) : ?>
								<tr>
									<td data-label="<?php esc_attr_e( 'Файл', 'wp-personal-account' ); ?>">
										<a href="<?php echo esc_url( WPPA_Curator_Files::download_url( $wppa_team_id, $wppa_file['key'] ) ); ?>">
											<?php echo esc_html( $wppa_file['name'] ); ?>
										</a>
									</td>
									<td data-label="<?php esc_attr_e( 'От кого', 'wp-personal-account' ); ?>">
										<?php echo 'mentor' === $wppa_file['from'] ? esc_html__( 'наставник', 'wp-personal-account' ) : esc_html__( 'куратор', 'wp-personal-account' ); ?>
									</td>
									<td data-label="<?php esc_attr_e( 'Размер', 'wp-personal-account' ); ?>"><?php echo esc_html( WPPA_Curator_Files::size_label( $wppa_file['size'] ) ); ?></td>
									<td data-label="<?php esc_attr_e( 'Прикреплён', 'wp-personal-account' ); ?>">
										<?php echo esc_html( $wppa_file['time'] ? date_i18n( get_option( 'date_format' ), $wppa_file['time'] ) : '—' ); ?>
									</td>
									<td data-label="<?php esc_attr_e( 'Действия', 'wp-personal-account' ); ?>">
										<form method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>" class="wppa-inline-form">
											<?php wp_nonce_field( 'wppa_curator_file', 'wppa_nonce' ); ?>
											<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_team_id ); ?>" />
											<input type="hidden" name="wppa_action" value="curator_file" />
											<input type="hidden" name="curator_file_action" value="remove" />
											<input type="hidden" name="file_key" value="<?php echo esc_attr( $wppa_file['key'] ); ?>" />
											<button type="submit" class="wppa-link-button" data-wppa-confirm="<?php esc_attr_e( 'Удалить файл?', 'wp-personal-account' ); ?>"><?php esc_html_e( 'Удалить', 'wp-personal-account' ); ?></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<div class="wppa-notice wppa-notice--info"><?php esc_html_e( 'Файлов пока нет. Прикрепите первый — наставник увидит его сразу после загрузки.', 'wp-personal-account' ); ?></div>
			<?php endif; ?>

			<form class="wppa-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>">
				<?php wp_nonce_field( 'wppa_curator_file', 'wppa_nonce' ); ?>
				<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_team_id ); ?>" />
				<input type="hidden" name="wppa_action" value="curator_file" />
				<input type="hidden" name="curator_file_action" value="upload" />

				<p class="wppa-field">
					<label for="wppa-curator-file"><?php esc_html_e( 'Прикрепить файл', 'wp-personal-account' ); ?></label>
					<input type="file" id="wppa-curator-file" name="curator_file" required />
				</p>

				<button type="submit" class="wppa-button wppa-button--primary"><?php esc_html_e( 'Прикрепить', 'wp-personal-account' ); ?></button>
			</form>
		</section>

	<?php endif; ?>

	<?php
	// Участникам команды: что прислал куратор. Видно всем участникам, без отметки второго этапа.
	$wppa_team_curator_files = array();

	// Наставнику полка не нужна: у него свой раздел «Файлы» с двумя полками.
	if ( $wppa_team_id && ! $wppa_is_curator && ! $wppa_is_mentor ) {
		foreach ( WPPA_Curator_Files::files( $wppa_team_id ) as $wppa_entry ) {
			if ( 'mentor' !== $wppa_entry['from'] ) {
				$wppa_team_curator_files[ $wppa_entry['key'] ] = $wppa_entry;
			}
		}
	}
	?>

	<?php if ( $wppa_team_curator_files ) : ?>

		<section class="wppa-card wppa-team-files">
			<h3 class="wppa-subtitle"><?php esc_html_e( 'Файлы от куратора', 'wp-personal-account' ); ?></h3>
			<p class="wppa-hint"><?php esc_html_e( 'Документы, которые куратор прислал вашей команде. Их видят все участники команды, скачать может каждый.', 'wp-personal-account' ); ?></p>

			<ul class="wppa-list wppa-team-files__list">
				<?php foreach ( $wppa_team_curator_files as $wppa_file ) : ?>
					<li>
						<a class="wppa-link" href="<?php echo esc_url( WPPA_Curator_Files::download_url( $wppa_team_id, $wppa_file['key'] ) ); ?>"><?php echo esc_html( $wppa_file['name'] ); ?></a>
						<span class="wppa-hint">
							<?php echo esc_html( WPPA_Curator_Files::size_label( $wppa_file['size'] ) ); ?>
							<?php if ( ! empty( $wppa_file['time'] ) ) : ?>
								· <?php echo esc_html( date_i18n( get_option( 'date_format' ), (int) $wppa_file['time'] ) ); ?>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

	<?php endif; ?>

	<?php if ( $wppa_can_invite ) : ?>

		<section class="wppa-card">
			<h3 class="wppa-subtitle"><?php esc_html_e( 'Пригласить участников', 'wp-personal-account' ); ?></h3>

			<?php if ( ! WPPA_Teams::has_valid_token( $wppa_team_id ) ) : ?>
				<div class="wppa-notice wppa-notice--info"><?php esc_html_e( 'Срок действия текущей ссылки истёк — обновите её.', 'wp-personal-account' ); ?></div>
			<?php endif; ?>

			<p class="wppa-field">
				<label for="wppa-invite-link"><?php esc_html_e( 'Ссылка-приглашение', 'wp-personal-account' ); ?></label>
				<span class="wppa-copy">
					<input type="text" id="wppa-invite-link" value="<?php echo esc_attr( WPPA_Teams::invite_link( $wppa_team_id ) ); ?>" readonly />
					<button type="button" class="wppa-button wppa-button--ghost" data-wppa-copy="wppa-invite-link"><?php esc_html_e( 'Копировать', 'wp-personal-account' ); ?></button>
				</span>
				<span class="wppa-hint"><?php esc_html_e( 'Действует 7 дней. Перегенерируйте, если ссылка попала не туда.', 'wp-personal-account' ); ?></span>
			</p>

			<form method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>" class="wppa-inline-form">
				<?php wp_nonce_field( 'wppa_team', 'wppa_nonce' ); ?>
				<?php echo $wppa_team_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- собранный статический input. ?>
				<input type="hidden" name="wppa_action" value="team" />
				<input type="hidden" name="wppa_team_action" value="refresh_token" />
				<button type="submit" class="wppa-button wppa-button--ghost"><?php esc_html_e( 'Обновить ссылку', 'wp-personal-account' ); ?></button>
			</form>

			<hr class="wppa-divider" />

			<form class="wppa-form" method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>">
				<?php wp_nonce_field( 'wppa_team', 'wppa_nonce' ); ?>
				<?php echo $wppa_team_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- собранный статический input. ?>
				<input type="hidden" name="wppa_action" value="team" />
				<input type="hidden" name="wppa_team_action" value="invite_email" />

				<p class="wppa-field">
					<label for="wppa-invite-email"><?php esc_html_e( 'Пригласить по email', 'wp-personal-account' ); ?></label>
					<input type="email" id="wppa-invite-email" name="invite_email" autocomplete="email" required />
				</p>

				<button type="submit" class="wppa-button wppa-button--primary"><?php esc_html_e( 'Отправить приглашение', 'wp-personal-account' ); ?></button>
			</form>
		</section>

	<?php endif; ?>

	<?php if ( $wppa_can_rename ) : ?>

		<section class="wppa-card">
			<h3 class="wppa-subtitle"><?php esc_html_e( 'Название команды', 'wp-personal-account' ); ?></h3>

			<form class="wppa-form" method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>">
				<?php wp_nonce_field( 'wppa_team', 'wppa_nonce' ); ?>
				<?php echo $wppa_team_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- собранный статический input. ?>
				<input type="hidden" name="wppa_action" value="team" />
				<input type="hidden" name="wppa_team_action" value="rename" />

				<p class="wppa-field">
					<label for="wppa-team-rename"><?php esc_html_e( 'Название', 'wp-personal-account' ); ?></label>
					<input type="text" id="wppa-team-rename" name="team_name" value="<?php echo esc_attr( $wppa_team_name ); ?>" maxlength="100" required />
				</p>

				<button type="submit" class="wppa-button wppa-button--primary"><?php esc_html_e( 'Сохранить', 'wp-personal-account' ); ?></button>
			</form>
		</section>

	<?php endif; ?>

	<?php if ( ! $wppa_curator_view ) : ?>
	<section class="wppa-card wppa-card--danger">
		<h3 class="wppa-subtitle">
			<?php echo $wppa_is_owner ? esc_html__( 'Распустить команду', 'wp-personal-account' ) : esc_html__( 'Выйти из команды', 'wp-personal-account' ); ?>
		</h3>
		<p class="wppa-hint">
			<?php
			echo $wppa_is_owner
				? esc_html__( 'Команда будет удалена, все участники выйдут из неё. Действие необратимо.', 'wp-personal-account' )
				: esc_html__( 'Вы сможете вернуться, если у вас будет новая ссылка-приглашение.', 'wp-personal-account' );
			?>
		</p>

		<form method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>" class="wppa-inline-form">
			<?php wp_nonce_field( 'wppa_team', 'wppa_nonce' ); ?>
			<?php echo $wppa_team_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- собранный статический input. ?>
			<input type="hidden" name="wppa_action" value="team" />
			<input type="hidden" name="wppa_team_action" value="leave" />
			<button type="submit" class="wppa-button wppa-button--danger" data-wppa-confirm="<?php esc_attr_e( 'Точно? Действие необратимо.', 'wp-personal-account' ); ?>">
				<?php echo $wppa_is_owner ? esc_html__( 'Распустить команду', 'wp-personal-account' ) : esc_html__( 'Выйти из команды', 'wp-personal-account' ); ?>
			</button>
		</form>
	</section>
	<?php endif; ?>

<?php endif; ?>
