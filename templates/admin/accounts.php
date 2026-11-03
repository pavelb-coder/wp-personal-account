<?php
/**
 * Админ-панель: страница «Аккаунты».
 *
 * Доступные переменные: $users, $total, $paged, $per_page, $search, $role, $roles, $notice.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_pages = (int) ceil( $total / max( 1, $per_page ) );
?>
<div class="wrap wppa-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Аккаунты', 'wp-personal-account' ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>"><?php esc_html_e( 'Добавить нового', 'wp-personal-account' ); ?></a>
	<hr class="wp-header-end" />

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'success' === $notice[0] ? 'success' : 'warning' ); ?> is-dismissible">
			<p><?php echo esc_html( $notice[1] ); ?></p>
		</div>
	<?php endif; ?>

	<form method="get" class="wppa-admin__filters">
		<input type="hidden" name="page" value="wppa-accounts" />
		<p class="search-box">
			<label class="screen-reader-text" for="wppa-search"><?php esc_html_e( 'Поиск аккаунтов', 'wp-personal-account' ); ?></label>
			<input type="search" id="wppa-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Логин, email или имя', 'wp-personal-account' ); ?>" />
			<?php submit_button( __( 'Поиск', 'wp-personal-account' ), '', '', false ); ?>
		</p>

		<label for="wppa-role" class="screen-reader-text"><?php esc_html_e( 'Роль', 'wp-personal-account' ); ?></label>
		<select name="role" id="wppa-role">
			<option value=""><?php esc_html_e( 'Все роли', 'wp-personal-account' ); ?></option>
			<?php foreach ( $roles as $wppa_slug => $wppa_name ) : ?>
				<option value="<?php echo esc_attr( $wppa_slug ); ?>" <?php selected( $role, $wppa_slug ); ?>>
					<?php echo esc_html( $wppa_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php submit_button( __( 'Фильтр', 'wp-personal-account' ), 'secondary', '', false ); ?>
	</form>

	<p class="wppa-admin__muted">
		<?php
		printf(
			/* translators: %s: количество найденных аккаунтов */
			esc_html__( 'Найдено аккаунтов: %s', 'wp-personal-account' ),
			esc_html( number_format_i18n( $total ) )
		);
		?>
	</p>

	<?php if ( empty( $users ) ) : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'Ничего не найдено.', 'wp-personal-account' ); ?></p></div>
	<?php else : ?>
		<table class="widefat striped wppa-admin__table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Пользователь', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Email', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Персональные данные', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Роль', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Регистрация', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Команда', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Подтверждение email', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Действия', 'wp-personal-account' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $users as $wppa_user ) : ?>
					<?php $wppa_pending = WPPA_Profile::pending_email( $wppa_user->ID ); ?>
					<tr>
						<td>
							<?php echo get_avatar( $wppa_user->ID, 24, '', '', array( 'class' => 'wppa-admin__avatar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<strong><?php echo esc_html( $wppa_user->display_name ); ?></strong>
							<span class="wppa-admin__muted">(<?php echo esc_html( $wppa_user->user_login ); ?>)</span>
						</td>
						<td><a href="mailto:<?php echo esc_attr( $wppa_user->user_email ); ?>"><?php echo esc_html( $wppa_user->user_email ); ?></a></td>
						<td>
							<?php
							$wppa_personals = array();

							foreach ( WPPA_Fields::meta_fields() as $wppa_field_id => $wppa_field ) {
								$wppa_value = WPPA_Fields::get( $wppa_user->ID, $wppa_field_id );

								if ( '' === $wppa_value ) {
									continue;
								}

								$wppa_personals[] = '<span class="wppa-admin__field"><span class="wppa-admin__muted">'
									. esc_html( $wppa_field['label'] ) . ':</span> ' . esc_html( $wppa_value ) . '</span>';
							}

							if ( ! $wppa_user->first_name && ! $wppa_user->last_name ) {
								$wppa_personals[] = '<span class="wppa-admin__muted">—</span>';
							}
							?>
							<?php if ( $wppa_user->last_name ) : ?>
								<span class="wppa-admin__field"><?php echo esc_html( $wppa_user->first_name . ' ' . $wppa_user->last_name ); ?></span>
							<?php endif; ?>
							<?php echo wp_kses_post( implode( '<br />', $wppa_personals ) ); ?>
						</td>
						<td><?php echo esc_html( implode( ', ', array_map( 'translate_user_role', array_intersect_key( wp_roles()->get_names(), array_flip( (array) $wppa_user->roles ) ) ) ) ); ?></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $wppa_user->user_registered ) ); ?></td>
						<td>
							<?php $wppa_team = WPPA_Teams::team_id( $wppa_user->ID ); ?>
							<?php if ( $wppa_team ) : ?>
								<?php echo esc_html( get_the_title( $wppa_team ) ); ?>
								<?php if ( WPPA_Teams::is_owner( $wppa_user->ID ) ) : ?>
									<span class="wppa-badge wppa-badge--owner"><?php esc_html_e( 'капитан', 'wp-personal-account' ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<span class="wppa-admin__muted">—</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $wppa_pending ) : ?>
								<span class="wppa-badge wppa-badge--warn"><?php echo esc_html( $wppa_pending ); ?></span>
							<?php else : ?>
								<span class="wppa-admin__muted">—</span>
							<?php endif; ?>
						</td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( get_edit_user_link( $wppa_user->ID ) ); ?>"><?php esc_html_e( 'Профиль', 'wp-personal-account' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $wppa_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					$wppa_links = paginate_links(
						array(
							'base'      => add_query_arg(
								array(
									'page' => 'wppa-accounts',
									's'    => rawurlencode( $search ),
									'role' => $role,
									'paged' => '%#%',
								),
								admin_url( 'admin.php' )
							),
							'format'    => '',
							'current'   => $paged,
							'total'     => $wppa_pages,
							'type'      => 'array',
							'prev_text' => '‹',
							'next_text' => '›',
						)
					);

					if ( $wppa_links ) {
						foreach ( $wppa_links as $wppa_link ) {
							echo '<span class="wppa-admin__page">' . wp_kses_post( $wppa_link ) . '</span>';
						}
					}
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
