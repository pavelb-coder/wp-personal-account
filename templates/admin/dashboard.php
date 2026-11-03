<?php
/**
 * Админ-панель: страница «Обзор».
 *
 * Доступные переменные: $stats, $chart, $recent, $status, $notice.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap wppa-admin">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Личный кабинет', 'wp-personal-account' ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=wppa-settings' ) ); ?>"><?php esc_html_e( 'Настройки', 'wp-personal-account' ); ?></a>
	<hr class="wp-header-end" />

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'success' === $notice[0] ? 'success' : 'warning' ); ?> is-dismissible">
			<p><?php echo esc_html( $notice[1] ); ?></p>
		</div>
	<?php endif; ?>

	<div class="wppa-admin__cards">
		<div class="wppa-admin__card">
			<span class="wppa-admin__card-label"><?php esc_html_e( 'Пользователей всего', 'wp-personal-account' ); ?></span>
			<span class="wppa-admin__card-value"><?php echo esc_html( number_format_i18n( $stats['total_users'] ) ); ?></span>
		</div>

		<div class="wppa-admin__card">
			<span class="wppa-admin__card-label"><?php esc_html_e( 'Новых за 30 дней', 'wp-personal-account' ); ?></span>
			<span class="wppa-admin__card-value"><?php echo esc_html( number_format_i18n( $stats['new_30'] ) ); ?></span>
		</div>

		<div class="wppa-admin__card">
			<span class="wppa-admin__card-label"><?php esc_html_e( 'Подписчиков', 'wp-personal-account' ); ?></span>
			<span class="wppa-admin__card-value"><?php echo esc_html( number_format_i18n( $stats['subscribers'] ) ); ?></span>
		</div>

		<div class="wppa-admin__card">
			<span class="wppa-admin__card-label"><?php esc_html_e( 'Ждут подтверждения email', 'wp-personal-account' ); ?></span>
			<span class="wppa-admin__card-value"><?php echo esc_html( number_format_i18n( $stats['pending_email'] ) ); ?></span>
		</div>

		<div class="wppa-admin__card">
			<span class="wppa-admin__card-label"><?php esc_html_e( 'Команд', 'wp-personal-account' ); ?></span>
			<span class="wppa-admin__card-value"><?php echo esc_html( number_format_i18n( $stats['teams'] ) ); ?></span>
			<span class="wppa-admin__card-note">
				<?php
				printf(
					/* translators: %d: количество пользователей в командах */
					esc_html__( 'участников: %d', 'wp-personal-account' ),
					(int) $stats['team_members']
				);
				?>
			</span>
		</div>
	</div>

	<div class="wppa-admin__grid">
		<section class="wppa-admin__panel">
			<h2><?php esc_html_e( 'Регистрации за 14 дней', 'wp-personal-account' ); ?></h2>

			<div class="wppa-chart" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: количество */ __( 'Всего регистраций: %d', 'wp-personal-account' ), $chart['total'] ) ); ?>">
				<?php foreach ( $chart['items'] as $wppa_point ) : ?>
					<?php
					$wppa_height = (int) round( ( $wppa_point['count'] / $chart['max'] ) * 100 );
					$wppa_height = max( 2, min( 100, $wppa_height ) );
					?>
					<div class="wppa-chart__col" title="<?php echo esc_attr( $wppa_point['date'] . ': ' . $wppa_point['count'] ); ?>">
						<span class="wppa-chart__bar" style="height:<?php echo esc_attr( (string) $wppa_height ); ?>%">
							<?php if ( $wppa_point['count'] ) : ?>
								<span class="wppa-chart__value"><?php echo esc_html( (string) $wppa_point['count'] ); ?></span>
							<?php endif; ?>
						</span>
						<span class="wppa-chart__label"><?php echo esc_html( $wppa_point['label'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<p class="wppa-admin__muted">
				<?php
				printf(
					/* translators: %s: количество регистраций */
					esc_html__( 'Всего за период: %s', 'wp-personal-account' ),
					esc_html( number_format_i18n( $chart['total'] ) )
				);
				?>
			</p>
		</section>

		<section class="wppa-admin__panel">
			<h2><?php esc_html_e( 'Состояние', 'wp-personal-account' ); ?></h2>

			<ul class="wppa-admin__status">
				<li>
					<span class="wppa-dot <?php echo $status['account_page']['ok'] ? 'is-ok' : 'is-bad'; ?>"></span>
					<?php esc_html_e( 'Страница кабинета', 'wp-personal-account' ); ?>:
					<?php if ( $status['account_page']['ok'] ) : ?>
						<a href="<?php echo esc_url( (string) $status['account_page']['link'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $status['account_page']['label'] ); ?></a>
					<?php else : ?>
						<strong><?php echo esc_html( $status['account_page']['label'] ); ?></strong>
					<?php endif; ?>
				</li>
				<li>
					<span class="wppa-dot <?php echo $status['registration']['ok'] ? 'is-ok' : 'is-warn'; ?>"></span>
					<?php esc_html_e( 'Регистрация', 'wp-personal-account' ); ?>: <?php echo esc_html( $status['registration']['label'] ); ?>
				</li>
				<li>
					<span class="wppa-dot <?php echo $status['tabs']['ok'] ? 'is-ok' : 'is-bad'; ?>"></span>
					<?php esc_html_e( 'Вкладки кабинета', 'wp-personal-account' ); ?>: <?php echo esc_html( $status['tabs']['label'] ); ?>
				</li>
			</ul>

			<p class="wppa-admin__muted">
				<?php
				printf(
					/* translators: 1: версия WordPress, 2: версия PHP, 3: версия плагина */
					esc_html__( 'WordPress %1$s · PHP %2$s · плагин %3$s', 'wp-personal-account' ),
					esc_html( $status['environment']['wp'] ),
					esc_html( $status['environment']['php'] ),
					esc_html( $status['environment']['plugin'] )
				);
				?>
			</p>

			<p class="wppa-admin__actions">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wppa-accounts' ) ); ?>"><?php esc_html_e( 'Все аккаунты', 'wp-personal-account' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wppa-teams' ) ); ?>"><?php esc_html_e( 'Команды', 'wp-personal-account' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wppa-tools' ) ); ?>"><?php esc_html_e( 'Инструменты', 'wp-personal-account' ); ?></a>
			</p>
		</section>
	</div>

	<section class="wppa-admin__panel">
		<h2><?php esc_html_e( 'Последние регистрации', 'wp-personal-account' ); ?></h2>

		<?php if ( empty( $recent ) ) : ?>
			<p class="wppa-admin__muted"><?php esc_html_e( 'Пользователей пока нет.', 'wp-personal-account' ); ?></p>
		<?php else : ?>
			<table class="widefat striped wppa-admin__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Пользователь', 'wp-personal-account' ); ?></th>
						<th><?php esc_html_e( 'Email', 'wp-personal-account' ); ?></th>
						<th><?php esc_html_e( 'Роль', 'wp-personal-account' ); ?></th>
						<th><?php esc_html_e( 'Регистрация', 'wp-personal-account' ); ?></th>
						<th><?php esc_html_e( 'Действия', 'wp-personal-account' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent as $wppa_user ) : ?>
						<tr>
							<td>
								<?php echo get_avatar( $wppa_user->ID, 24, '', '', array( 'class' => 'wppa-admin__avatar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<strong><?php echo esc_html( $wppa_user->display_name ); ?></strong>
								<span class="wppa-admin__muted">(<?php echo esc_html( $wppa_user->user_login ); ?>)</span>
							</td>
							<td><a href="mailto:<?php echo esc_attr( $wppa_user->user_email ); ?>"><?php echo esc_html( $wppa_user->user_email ); ?></a></td>
							<td><?php echo esc_html( implode( ', ', array_map( 'translate_user_role', array_intersect_key( wp_roles()->get_names(), array_flip( (array) $wppa_user->roles ) ) ) ) ); ?></td>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $wppa_user->user_registered ) ); ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( get_edit_user_link( $wppa_user->ID ) ); ?>"><?php esc_html_e( 'Профиль', 'wp-personal-account' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>
</div>
