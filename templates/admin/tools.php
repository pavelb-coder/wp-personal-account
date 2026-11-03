<?php
/**
 * Админ-панель: страница «Инструменты».
 *
 * Доступные переменные: $notice, $status.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wppa-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Инструменты', 'wp-personal-account' ); ?></h1>
	<hr class="wp-header-end" />

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'success' === $notice[0] ? 'success' : 'warning' ); ?> is-dismissible">
			<p><?php echo esc_html( $notice[1] ); ?></p>
		</div>
	<?php endif; ?>

	<div class="wppa-admin__grid">
		<section class="wppa-admin__panel">
			<h2><?php esc_html_e( 'Обслуживание', 'wp-personal-account' ); ?></h2>

			<div class="wppa-tool">
				<div>
					<strong><?php esc_html_e( 'Страница кабинета', 'wp-personal-account' ); ?></strong>
					<p class="wppa-admin__muted"><?php esc_html_e( 'Создаст страницу с шорткодом [wppa_account] и назначит её в настройках, если её нет.', 'wp-personal-account' ); ?></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'wppa_tool' ); ?>
					<input type="hidden" name="action" value="wppa_tool" />
					<input type="hidden" name="wppa_tool" value="create_page" />
					<button type="submit" class="button"><?php esc_html_e( 'Создать / восстановить', 'wp-personal-account' ); ?></button>
				</form>
			</div>

			<div class="wppa-tool">
				<div>
					<strong><?php esc_html_e( 'Лимит попыток входа', 'wp-personal-account' ); ?></strong>
					<p class="wppa-admin__muted"><?php esc_html_e( 'Сбросит блокировку входа (5 неудачных попыток за 15 минут) для всех IP.', 'wp-personal-account' ); ?></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'wppa_tool' ); ?>
					<input type="hidden" name="action" value="wppa_tool" />
					<input type="hidden" name="wppa_tool" value="clear_limits" />
					<button type="submit" class="button"><?php esc_html_e( 'Сбросить блокировки', 'wp-personal-account' ); ?></button>
				</form>
			</div>

			<div class="wppa-tool">
				<div>
					<strong><?php esc_html_e( 'Ожидающие подтверждения email', 'wp-personal-account' ); ?></strong>
					<p class="wppa-admin__muted"><?php esc_html_e( 'Удалит незавершённые заявки на смену email вместе с токенами.', 'wp-personal-account' ); ?></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'wppa_tool' ); ?>
					<input type="hidden" name="action" value="wppa_tool" />
					<input type="hidden" name="wppa_tool" value="clear_pending_emails" />
					<button type="submit" class="button"><?php esc_html_e( 'Очистить заявки', 'wp-personal-account' ); ?></button>
				</form>
			</div>

			<div class="wppa-tool">
				<div>
					<strong><?php esc_html_e( 'Экспорт аккаунтов', 'wp-personal-account' ); ?></strong>
					<p class="wppa-admin__muted"><?php esc_html_e( 'CSV с логинами, email, ролями и датой регистрации (до 5000 записей).', 'wp-personal-account' ); ?></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'wppa_tool' ); ?>
					<input type="hidden" name="action" value="wppa_tool" />
					<input type="hidden" name="wppa_tool" value="export_csv" />
					<button type="submit" class="button"><?php esc_html_e( 'Скачать CSV', 'wp-personal-account' ); ?></button>
				</form>
			</div>
		</section>

		<section class="wppa-admin__panel">
			<h2><?php esc_html_e( 'Шорткоды', 'wp-personal-account' ); ?></h2>

			<table class="widefat striped wppa-admin__table">
				<tbody>
					<tr>
						<td><code>[wppa_account]</code></td>
						<td><?php esc_html_e( 'Личный кабинет с вкладками', 'wp-personal-account' ); ?></td>
					</tr>
					<tr>
						<td><code>[wppa_login]</code></td>
						<td><?php esc_html_e( 'Форма входа (атрибут redirect)', 'wp-personal-account' ); ?></td>
					</tr>
					<tr>
						<td><code>[wppa_register]</code></td>
						<td><?php esc_html_e( 'Форма регистрации', 'wp-personal-account' ); ?></td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Среда', 'wp-personal-account' ); ?></h2>
			<ul class="wppa-admin__status">
				<li>
					<span class="wppa-dot <?php echo $status['account_page']['ok'] ? 'is-ok' : 'is-bad'; ?>"></span>
					<?php esc_html_e( 'Страница кабинета', 'wp-personal-account' ); ?>:
					<?php if ( $status['account_page']['edit'] ) : ?>
						<a href="<?php echo esc_url( (string) $status['account_page']['edit'] ); ?>"><?php echo esc_html( $status['account_page']['label'] ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $status['account_page']['label'] ); ?>
					<?php endif; ?>
				</li>
				<li>
					<span class="wppa-dot is-ok"></span>
					<?php esc_html_e( 'Версия WordPress', 'wp-personal-account' ); ?>: <?php echo esc_html( $status['environment']['wp'] ); ?>
				</li>
				<li>
					<span class="wppa-dot is-ok"></span>
					<?php esc_html_e( 'Версия PHP', 'wp-personal-account' ); ?>: <?php echo esc_html( $status['environment']['php'] ); ?>
				</li>
			</ul>

			<p class="wppa-admin__actions">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wppa-settings' ) ); ?>"><?php esc_html_e( 'Настройки кабинета', 'wp-personal-account' ); ?></a>
			</p>
		</section>
	</div>

	<section class="wppa-admin__panel">
		<h2><?php esc_html_e( 'Документация', 'wp-personal-account' ); ?></h2>
		<p class="wppa-admin__muted">
			<?php esc_html_e( 'Шаблоны переопределяются в теме: скопируйте файл из templates/ в yourtheme/wp-personal-account/. Доступны фильтры wppa_tabs, wppa_orders_query_args и wppa_notices.', 'wp-personal-account' ); ?>
		</p>
		<p>
			<a class="button" href="https://github.com/pavelb-coder/wp-personal-account" target="_blank" rel="noopener">GitHub</a>
			<a class="button" href="https://github.com/pavelb-coder/wp-personal-account/blob/main/README.md" target="_blank" rel="noopener">README</a>
		</p>
	</section>
</div>
