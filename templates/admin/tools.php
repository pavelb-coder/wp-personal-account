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

			<div class="wppa-tool wppa-tool--export">
				<div>
					<strong><?php esc_html_e( 'Экспорт аккаунтов', 'wp-personal-account' ); ?></strong>
					<p class="wppa-admin__muted"><?php esc_html_e( 'CSV по группам колонок: учётная запись, профиль, личные данные, команда, статусы. Две строки заголовков — группа и поле.', 'wp-personal-account' ); ?></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wppa-export-form">
					<?php wp_nonce_field( 'wppa_tool' ); ?>
					<input type="hidden" name="action" value="wppa_tool" />
					<input type="hidden" name="wppa_tool" value="export_csv" />

					<fieldset class="wppa-export-form__groups">
						<legend><?php esc_html_e( 'Группы колонок', 'wp-personal-account' ); ?></legend>
						<?php foreach ( WPPA_Export::groups() as $wppa_group_key => $wppa_group_label ) : ?>
							<label>
								<input
									type="checkbox"
									name="export_groups[]"
									value="<?php echo esc_attr( $wppa_group_key ); ?>"
									<?php checked( in_array( $wppa_group_key, WPPA_Export::default_groups(), true ) ); ?>
									<?php echo 'account' === $wppa_group_key ? 'disabled' : ''; ?>
								/>
								<?php
								if ( 'account' === $wppa_group_key ) {
									echo '<input type="hidden" name="export_groups[]" value="account" />';
								}
								echo esc_html( $wppa_group_label );
								?>
							</label>
						<?php endforeach; ?>
					</fieldset>

					<p class="wppa-export-form__row">
						<label for="wppa-export-scope"><?php esc_html_e( 'Кого выгружать', 'wp-personal-account' ); ?></label>
						<select id="wppa-export-scope" name="export_scope">
							<?php foreach ( WPPA_Export::scopes() as $wppa_scope_key => $wppa_scope_label ) : ?>
								<option value="<?php echo esc_attr( $wppa_scope_key ); ?>"><?php echo esc_html( $wppa_scope_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<p class="wppa-export-form__row">
						<label for="wppa-export-delimiter"><?php esc_html_e( 'Разделитель', 'wp-personal-account' ); ?></label>
						<select id="wppa-export-delimiter" name="export_delimiter">
							<option value=";"><?php esc_html_e( 'точка с запятой (;), Excel', 'wp-personal-account' ); ?></option>
							<option value=","><?php esc_html_e( 'запятая (,), Google Sheets', 'wp-personal-account' ); ?></option>
						</select>
					</p>

					<p class="wppa-export-form__row">
						<label for="wppa-export-limit"><?php esc_html_e( 'Максимум строк', 'wp-personal-account' ); ?></label>
						<input type="number" id="wppa-export-limit" name="export_limit" value="5000" min="1" max="20000" step="100" class="small-text" />
					</p>

					<button type="submit" class="button button-primary"><?php esc_html_e( 'Скачать CSV', 'wp-personal-account' ); ?></button>
				</form>
			</div>
		</section>

		<section class="wppa-admin__panel">
			<h2><?php esc_html_e( 'Экспорт в сетевую папку (SMB)', 'wp-personal-account' ); ?></h2>

			<?php
			$wppa_folder = isset( $export_folder ) ? $export_folder : WPPA_Export_Folder::config();
			$wppa_next   = isset( $export_next ) ? (int) $export_next : 0;
			$wppa_log    = isset( $export_log ) ? (array) $export_log : array();
			?>

			<p class="wppa-admin__muted">
				<?php esc_html_e( 'Файл CSV по тем же группам колонок, что и ручная выгрузка, но записывается в папку на диске — в том числе в смонтированную сетевую шару SMB/CIFS. Вместе с CSV кнопка «Экспортировать» раскладывает файлы наставников по папкам команд — общая папка «Команды», внутри «Название команды (ID)». Настройки пути и расписания — в разделе «Настройки → Экспорт в папку».', 'wp-personal-account' ); ?>
			</p>

			<?php
			$wppa_smb_test = isset( $smb_test ) && is_array( $smb_test ) ? $smb_test : null;
			$wppa_smb_conf = isset( $smb_config ) && is_array( $smb_config ) ? $smb_config : WPPA_SMB::config();
			?>

			<?php if ( 'smb' === $wppa_folder['mode'] ) : ?>
				<p>
					<span class="wppa-badge <?php echo ( $wppa_smb_test && $wppa_smb_test['ok'] ) ? 'wppa-badge--ok' : 'wppa-badge--warn'; ?>">
						<?php echo ( $wppa_smb_test && $wppa_smb_test['ok'] ) ? esc_html__( 'Сетевая папка: доступ есть', 'wp-personal-account' ) : esc_html__( 'Сетевая папка: доступа нет', 'wp-personal-account' ); ?>
					</span>
					<span class="wppa-admin__muted"><?php echo $wppa_smb_test ? esc_html( $wppa_smb_test['message'] ) : ''; ?></span>
				</p>
				<p class="wppa-admin__muted">
					<?php
					printf(
						/* translators: 1: путь, 2: логин */
						esc_html__( 'Путь: %1$s, пользователь: %2$s.', 'wp-personal-account' ),
						'<code>' . esc_html( '' !== $wppa_smb_conf['path'] ? $wppa_smb_conf['path'] : '—' ) . '</code>',
						esc_html( '' !== $wppa_smb_conf['user'] ? $wppa_smb_conf['user'] : '—' )
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( isset( $export_check ) && is_array( $export_check ) ) : ?>
				<p>
					<span class="wppa-badge <?php echo $export_check['ok'] ? 'wppa-badge--ok' : 'wppa-badge--warn'; ?>">
						<?php echo $export_check['ok'] ? esc_html__( 'Папка доступна', 'wp-personal-account' ) : esc_html__( 'Папка недоступна', 'wp-personal-account' ); ?>
					</span>
					<span class="wppa-admin__muted"><?php echo esc_html( $export_check['message'] ); ?></span>
				</p>
			<?php endif; ?>

			<p>
				<?php if ( $wppa_next ) : ?>
					<?php
					printf(
						/* translators: 1: дата и время, 2: периодичность */
						esc_html__( 'Следующая выгрузка: %1$s (%2$s).', 'wp-personal-account' ),
						esc_html( WPPA_Time::format_date( $wppa_next ) ),
						esc_html( isset( WPPA_Export_Folder::schedules()[ $wppa_folder['schedule'] ] ) ? WPPA_Export_Folder::schedules()[ $wppa_folder['schedule'] ] : '' )
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Автоматическая выгрузка не запланирована — включите её в настройках.', 'wp-personal-account' ); ?>
				<?php endif; ?>
			</p>

			<?php if ( $wppa_folder['on_change'] ) : ?>
				<p class="wppa-admin__muted">
					<?php
					printf(
						/* translators: 1: задержка, 2: состояние */
						esc_html__( 'Выгрузка по изменениям: %1$s; %2$s', 'wp-personal-account' ),
						esc_html( WPPA_Export_Folder::delays()[ $wppa_folder['change_delay'] ] ),
						esc_html(
							! empty( $export_pending )
								? sprintf(
									/* translators: 1: число, 2: время */
									__( 'ожидает %1$d изменений, ближайшая выгрузка %2$s', 'wp-personal-account' ),
									count( $export_pending ),
									$export_change ? wp_date( 'd.m.Y H:i', $export_change ) : __( 'сразу', 'wp-personal-account' )
								)
								: __( 'изменений пока нет', 'wp-personal-account' )
						)
					);
					?>
				</p>
			<?php endif; ?>

			<?php
			$wppa_files_last = isset( $files_last ) && is_array( $files_last ) ? $files_last : WPPA_Team_Files_Export::last_result();
			?>

			<?php if ( ! empty( $wppa_files_last ) ) : ?>
				<div class="wppa-admin__panel" style="margin:12px 0;padding:12px 14px;border-left:4px solid <?php echo ! empty( $wppa_files_last['ok'] ) ? '#00a32a' : '#d63638'; ?>;background:#fff">
					<strong><?php esc_html_e( 'Последняя выгрузка файлов наставников', 'wp-personal-account' ); ?></strong>
					<p class="wppa-admin__muted" style="margin:4px 0 6px">
						<?php
						printf(
							/* translators: 1: дата, 2: выгружено, 3: всего, 4: ошибок */
							esc_html__( '%1$s · выгружено %2$d из %3$d, ошибок %4$d', 'wp-personal-account' ),
							esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $wppa_files_last['time'] ) ),
							(int) $wppa_files_last['exported'],
							(int) $wppa_files_last['files'],
							(int) $wppa_files_last['failed']
						);
						?>
					</p>
					<p style="margin:0 0 6px"><?php echo esc_html( $wppa_files_last['message'] ); ?></p>
					<?php if ( ! empty( $wppa_files_last['errors'] ) ) : ?>
						<ul class="wppa-admin__muted" style="margin:0;padding-left:18px">
							<?php foreach ( (array) $wppa_files_last['errors'] as $wppa_files_error ) : ?>
								<li><?php echo esc_html( (string) $wppa_files_error ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="wppa-admin__actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'wppa_tool' ); ?>
					<input type="hidden" name="action" value="wppa_tool" />
					<input type="hidden" name="wppa_tool" value="export_folder_now" />
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Экспортировать', 'wp-personal-account' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'wppa_tool' ); ?>
					<input type="hidden" name="action" value="wppa_tool" />
					<input type="hidden" name="wppa_tool" value="files_folder_check" />
					<button type="submit" class="button"><?php esc_html_e( 'Проверить папку команд', 'wp-personal-account' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'wppa_tool' ); ?>
					<input type="hidden" name="action" value="wppa_tool" />
					<input type="hidden" name="wppa_tool" value="folder_verify" />
					<button type="submit" class="button"><?php esc_html_e( 'Проверить подключение к сетевой папке', 'wp-personal-account' ); ?></button>
				</form>
				<?php if ( $wppa_log ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'wppa_tool' ); ?>
						<input type="hidden" name="action" value="wppa_tool" />
						<input type="hidden" name="wppa_tool" value="export_folder_clear_log" />
						<button type="submit" class="button"><?php esc_html_e( 'Очистить журнал', 'wp-personal-account' ); ?></button>
					</form>
				<?php endif; ?>
			</div>

			<?php if ( $wppa_log ) : ?>
				<table class="widefat striped wppa-admin__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Когда', 'wp-personal-account' ); ?></th>
							<th><?php esc_html_e( 'Результат', 'wp-personal-account' ); ?></th>
							<th><?php esc_html_e( 'Файл', 'wp-personal-account' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $wppa_log, 0, 5 ) as $wppa_entry ) : ?>
							<tr>
								<td><?php echo esc_html( WPPA_Time::format_date( (int) $wppa_entry['time'] ) ); ?></td>
								<td>
									<span class="wppa-badge <?php echo ! empty( $wppa_entry['ok'] ) ? 'wppa-badge--ok' : 'wppa-badge--warn'; ?>">
										<?php echo ! empty( $wppa_entry['ok'] ) ? esc_html__( 'записан', 'wp-personal-account' ) : esc_html__( 'ошибка', 'wp-personal-account' ); ?>
									</span>
									<span class="wppa-admin__muted"><?php echo esc_html( $wppa_entry['message'] ); ?></span>
								</td>
								<td><code><?php echo esc_html( $wppa_entry['file'] ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
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
					<tr>
						<td><code>[wppa_auth]</code></td>
						<td><?php esc_html_e( 'Вход и регистрация одним блоком (атрибуты mode, redirect)', 'wp-personal-account' ); ?></td>
					</tr>
					<tr>
						<td><code>wppa/auth</code></td>
						<td><?php esc_html_e( 'Блок Gutenberg «Вход и регистрация» — для блочных тем и Site Editor', 'wp-personal-account' ); ?></td>
					</tr>
					<tr>
						<td><code>wppa/login</code></td>
						<td><?php esc_html_e( 'Блок Gutenberg «Вход» — только форма входа или кнопка «Войти» (атрибут display)', 'wp-personal-account' ); ?></td>
					</tr>
					<tr>
						<td><code>wppa/register</code></td>
						<td><?php esc_html_e( 'Блок Gutenberg «Регистрация» — только форма регистрации или кнопка «Регистрация»', 'wp-personal-account' ); ?></td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Вход на главной странице', 'wp-personal-account' ); ?></h2>
			<ol class="wppa-admin__steps">
				<li>
					<strong><?php esc_html_e( 'Блочная тема (FSE):', 'wp-personal-account' ); ?></strong>
					<?php esc_html_e( 'вставьте блок «Вход и регистрация (личный кабинет)» в Site Editor или в редакторе страницы — раздел «Виджеты» в списке блоков.', 'wp-personal-account' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Отдельными блоками:', 'wp-personal-account' ); ?></strong>
					<?php esc_html_e( '«Вход (личный кабинет)» и «Регистрация (личный кабинет)» — независимые блоки. У каждого есть вид «форма» и вид «кнопка» (ссылка на форму): удобно ставить кнопки в шапку, а формы — на страницу.', 'wp-personal-account' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Классическая тема:', 'wp-personal-account' ); ?></strong>
					<?php esc_html_e( 'виджет «Личный кабинет: вход и регистрация» в «Внешний вид → Виджеты».', 'wp-personal-account' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Любой вариант:', 'wp-personal-account' ); ?></strong>
					<?php esc_html_e( 'шорткод [wppa_auth] в блоке «Шорткод».', 'wp-personal-account' ); ?>
				</li>
			</ol>
			<p class="wppa-admin__actions">
				<a class="button" href="<?php echo esc_url( admin_url( 'site-editor.php' ) ); ?>"><?php esc_html_e( 'Открыть Site Editor', 'wp-personal-account' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'widgets.php' ) ); ?>"><?php esc_html_e( 'Виджеты', 'wp-personal-account' ); ?></a>
			</p>

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
