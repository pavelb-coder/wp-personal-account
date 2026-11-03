<?php
/**
 * Админ-панель: страница «Команды».
 *
 * Доступные переменные: $rows, $max_members, $total_users, $notice.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_in_teams = 0;
foreach ( $rows as $wppa_row ) {
	$wppa_in_teams += (int) $wppa_row['members'];
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
					<th><?php esc_html_e( 'Участников', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Лимит', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Создана', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Действия', 'wp-personal-account' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $wppa_row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $wppa_row['name'] ); ?></strong></td>
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
								/* translators: 1: участников, 2: лимит */
								esc_html__( '%1$d / %2$d', 'wp-personal-account' ),
								(int) $wppa_row['members'],
								(int) $wppa_row['limit']
							);
							?>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wppa-inline-form">
								<?php wp_nonce_field( 'wppa_tool' ); ?>
								<input type="hidden" name="action" value="wppa_tool" />
								<input type="hidden" name="wppa_tool" value="update_team_limit" />
								<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_row['id'] ); ?>" />
								<input
									type="number"
									name="team_limit"
									min="2"
									max="100"
									class="small-text"
									value="<?php echo esc_attr( $wppa_row['own_limit'] ? (string) $wppa_row['own_limit'] : '' ); ?>"
									placeholder="<?php echo esc_attr( (string) $max_members ); ?>"
									title="<?php esc_attr_e( 'Пусто — использовать общий лимит из настроек', 'wp-personal-account' ); ?>"
								/>
								<button type="submit" class="button button-small"><?php esc_html_e( 'Сохранить', 'wp-personal-account' ); ?></button>
							</form>
							<?php if ( $wppa_row['own_limit'] ) : ?>
								<span class="wppa-badge"><?php esc_html_e( 'свой лимит', 'wp-personal-account' ); ?></span>
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
			<?php esc_html_e( 'Лимит участников можно задать для каждой команды отдельно — поле «Лимит». Пустое поле означает общий лимит из настроек; чтобы вернуть общий, очистите поле и сохраните.', 'wp-personal-account' ); ?>
		</p>
	</section>
</div>
