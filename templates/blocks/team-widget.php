<?php
/**
 * Блок «Команда».
 *
 * Доступные переменные: $user, $tabs, $block_title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_team_id  = WPPA_Teams::team_id( $user->ID );
$wppa_team     = $wppa_team_id ? WPPA_Teams::get( $wppa_team_id ) : null;
$wppa_is_owner   = $wppa_team_id && WPPA_Teams::is_owner( $user->ID );
$wppa_is_mentor  = $wppa_team_id && WPPA_Teams::is_mentor( $user->ID );
$wppa_is_manager = $wppa_team_id && WPPA_Teams::is_manager( $user->ID );
$wppa_my_role    = $wppa_team_id ? WPPA_Teams::role( $user->ID ) : '';
$wppa_max      = WPPA_Teams::team_limit( $wppa_team_id );
$wppa_min      = WPPA_Teams::team_min( $wppa_team_id );
$wppa_missing  = WPPA_Teams::missing_members( $wppa_team_id );
?>

<h2 class="wppa-title">
	<?php echo isset( $block_title ) && '' !== $block_title ? esc_html( $block_title ) : esc_html__( 'Команда', 'wp-personal-account' ); ?>
</h2>

<?php if ( ! $wppa_team ) : ?>

	<div class="wppa-notice wppa-notice--info">
		<?php esc_html_e( 'Вы пока не состоите в команде. Создайте свою или присоединитесь по ссылке-приглашению.', 'wp-personal-account' ); ?>
	</div>

	<div class="wppa-team-grid">
		<section class="wppa-card">
			<h3 class="wppa-subtitle"><?php esc_html_e( 'Создать команду', 'wp-personal-account' ); ?></h3>
			<p class="wppa-hint"><?php esc_html_e( 'Вы станете капитаном: сможете приглашать участников и управлять составом.', 'wp-personal-account' ); ?></p>

			<form class="wppa-form" method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>">
				<?php wp_nonce_field( 'wppa_team', 'wppa_nonce' ); ?>
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

<?php else : ?>

	<div class="wppa-card wppa-team-head">
		<div>
			<h3 class="wppa-team-name"><?php echo esc_html( $wppa_team['name'] ); ?></h3>
			<p class="wppa-hint">
				<?php
				printf(
					/* translators: 1: количество участников, 2: лимит, 3: минимум */
					esc_html__( 'Участников: %1$d из %2$d (минимум %3$d)', 'wp-personal-account' ),
					count( $wppa_team['members'] ),
					$wppa_max,
					$wppa_min
				);
				if ( $wppa_team['created'] ) {
					echo ' · ';
					printf(
						/* translators: %s: дата создания */
						esc_html__( 'создана %s', 'wp-personal-account' ),
						esc_html( date_i18n( get_option( 'date_format' ), $wppa_team['created'] ) )
					);
				}
				?>
			</p>
		</div>
		<span class="wppa-badge <?php echo esc_attr( 'owner' === $wppa_my_role ? 'wppa-badge--owner' : ( 'mentor' === $wppa_my_role ? 'wppa-badge--mentor' : '' ) ); ?>">
			<?php echo esc_html( WPPA_Teams::role_label( $wppa_my_role ) ); ?>
		</span>
	</div>

	<?php if ( $wppa_missing > 0 ) : ?>
		<div class="wppa-notice wppa-notice--info">
			<?php
			printf(
				/* translators: 1: сколько не хватает, 2: минимум */
				esc_html__( 'Команда ещё не набрана: не хватает %1$d участник(ов) до минимума %2$d. Пригласите коллег по ссылке ниже.', 'wp-personal-account' ),
				(int) $wppa_missing,
				(int) $wppa_min
			);
			?>
		</div>
	<?php endif; ?>

	<?php if ( $wppa_is_mentor ) : ?>
		<div class="wppa-notice wppa-notice--info">
			<?php esc_html_e( 'Вы наставник команды: можете приглашать и исключать участников. Переименовать или распустить команду может только капитан.', 'wp-personal-account' ); ?>
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
				<?php foreach ( $wppa_team['members'] as $wppa_member ) : ?>
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
								&& ( $wppa_is_owner || 'member' === $wppa_member_role );
							?>
							<td data-label="<?php esc_attr_e( 'Действия', 'wp-personal-account' ); ?>">
								<?php if ( $wppa_can_act ) : ?>
									<div class="wppa-actions">
										<?php if ( $wppa_is_owner ) : ?>
											<form method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>" class="wppa-inline-form">
												<?php wp_nonce_field( 'wppa_team', 'wppa_nonce' ); ?>
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

	<?php if ( $wppa_is_manager ) : ?>

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
				<input type="hidden" name="wppa_action" value="team" />
				<input type="hidden" name="wppa_team_action" value="refresh_token" />
				<button type="submit" class="wppa-button wppa-button--ghost"><?php esc_html_e( 'Обновить ссылку', 'wp-personal-account' ); ?></button>
			</form>

			<hr class="wppa-divider" />

			<form class="wppa-form" method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>">
				<?php wp_nonce_field( 'wppa_team', 'wppa_nonce' ); ?>
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

	<?php if ( $wppa_is_owner ) : ?>

		<section class="wppa-card">
			<h3 class="wppa-subtitle"><?php esc_html_e( 'Название команды', 'wp-personal-account' ); ?></h3>

			<form class="wppa-form" method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>">
				<?php wp_nonce_field( 'wppa_team', 'wppa_nonce' ); ?>
				<input type="hidden" name="wppa_action" value="team" />
				<input type="hidden" name="wppa_team_action" value="rename" />

				<p class="wppa-field">
					<label for="wppa-team-rename"><?php esc_html_e( 'Название', 'wp-personal-account' ); ?></label>
					<input type="text" id="wppa-team-rename" name="team_name" value="<?php echo esc_attr( $wppa_team['name'] ); ?>" maxlength="100" required />
				</p>

				<button type="submit" class="wppa-button wppa-button--primary"><?php esc_html_e( 'Сохранить', 'wp-personal-account' ); ?></button>
			</form>
		</section>

	<?php endif; ?>

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
			<input type="hidden" name="wppa_action" value="team" />
			<input type="hidden" name="wppa_team_action" value="leave" />
			<button type="submit" class="wppa-button wppa-button--danger" data-wppa-confirm="<?php esc_attr_e( 'Точно? Действие необратимо.', 'wp-personal-account' ); ?>">
				<?php echo $wppa_is_owner ? esc_html__( 'Распустить команду', 'wp-personal-account' ) : esc_html__( 'Выйти из команды', 'wp-personal-account' ); ?>
			</button>
		</form>
	</section>

<?php endif; ?>
