<?php
/**
 * Блок «Файлы» — документы команды между куратором и наставником.
 *
 * Наставник прикрепляет файлы для куратора и скачивает файлы от него.
 * Куратор — наоборот: прикрепляет документы для наставника и видит файлы,
 * которые прислал наставник. Участникам команды раздел недоступен.
 *
 * Доступные переменные: $user, $tabs, $block_title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_is_curator = WPPA_Teams::is_curator( $user->ID );
$wppa_my_team_id = (int) WPPA_Teams::team_id( $user->ID );

// Куратор открывает файлы любой команды: ?wppa_tab=files&wppa_team=ID
$wppa_curator_team_id = $wppa_is_curator ? WPPA_Teams::viewing_team_id( $user->ID ) : 0;
$wppa_team_id         = $wppa_curator_team_id ? $wppa_curator_team_id : $wppa_my_team_id;
$wppa_can_view        = $wppa_team_id && WPPA_Curator_Files::can_view( $wppa_team_id, $user->ID );
$wppa_can_upload      = $wppa_team_id && WPPA_Curator_Files::can_manage( $wppa_team_id, $user->ID );
$wppa_can_attach      = $wppa_team_id && WPPA_Curator_Files::can_attach( $wppa_team_id, $user->ID );
$wppa_direction       = $wppa_team_id ? WPPA_Curator_Files::direction( $wppa_team_id, $user->ID ) : '';
$wppa_files           = $wppa_can_view ? WPPA_Curator_Files::files( $wppa_team_id ) : array();

// Участнику команды показываем только документы куратора.
if ( '' === $wppa_direction ) {
	$wppa_files = array_filter(
		$wppa_files,
		static function ( $wppa_entry ) {
			return 'mentor' !== $wppa_entry['from'];
		}
	);
}
$wppa_team_name       = $wppa_team_id ? (string) get_the_title( $wppa_team_id ) : '';

// Две встречные полки: что прислал куратор и что прислал наставник.
$wppa_from_curator = array();
$wppa_from_mentor  = array();

foreach ( $wppa_files as $wppa_key => $wppa_entry ) {
	if ( 'mentor' === $wppa_entry['from'] ) {
		$wppa_from_mentor[ $wppa_key ] = $wppa_entry;
	} else {
		$wppa_from_curator[ $wppa_key ] = $wppa_entry;
	}
}

$wppa_upload_form = '<form class="wppa-form" method="post" enctype="multipart/form-data" action="' . esc_url( WPPA_Plugin::tab_url( 'files' ) ) . '">';
?>

<h2 class="wppa-title">
	<?php echo isset( $block_title ) && '' !== $block_title ? esc_html( $block_title ) : esc_html__( 'Файлы', 'wp-personal-account' ); ?>
</h2>

<?php if ( ! $wppa_team_id ) : ?>

	<?php if ( $wppa_is_curator ) : ?>
		<section class="wppa-card wppa-curator-files">
			<h3 class="wppa-subtitle"><?php esc_html_e( 'Файлы команд', 'wp-personal-account' ); ?></h3>
			<p class="wppa-hint"><?php esc_html_e( 'Выберите команду — и увидите документы, которыми обменялись куратор и наставник. Прикреплять файлы можно здесь же или на вкладке «Команда».', 'wp-personal-account' ); ?></p>

			<?php $wppa_all_teams = WPPA_Teams::all_teams(); ?>

			<?php if ( empty( $wppa_all_teams ) ) : ?>
				<div class="wppa-notice wppa-notice--info"><?php esc_html_e( 'Команд пока нет.', 'wp-personal-account' ); ?></div>
			<?php else : ?>
				<div class="wppa-table-wrap">
					<table class="wppa-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Команда', 'wp-personal-account' ); ?></th>
								<th><?php esc_html_e( 'Файлов', 'wp-personal-account' ); ?></th>
								<th><?php esc_html_e( 'Действие', 'wp-personal-account' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $wppa_all_teams as $wppa_row ) : ?>
								<tr>
									<td data-label="<?php esc_attr_e( 'Команда', 'wp-personal-account' ); ?>"><strong><?php echo esc_html( $wppa_row['name'] ); ?></strong></td>
									<td data-label="<?php esc_attr_e( 'Файлов', 'wp-personal-account' ); ?>"><?php echo esc_html( (string) WPPA_Curator_Files::count( (int) $wppa_row['id'] ) ); ?></td>
									<td data-label="<?php esc_attr_e( 'Действие', 'wp-personal-account' ); ?>">
										<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( add_query_arg( 'wppa_team', (int) $wppa_row['id'], WPPA_Plugin::tab_url( 'files' ) ) ); ?>"><?php esc_html_e( 'Открыть', 'wp-personal-account' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>
	<?php else : ?>
		<div class="wppa-notice wppa-notice--info"><?php esc_html_e( 'Раздел с файлами доступен наставнику команды и куратору.', 'wp-personal-account' ); ?></div>
	<?php endif; ?>

<?php elseif ( ! $wppa_can_view ) : ?>

	<div class="wppa-notice wppa-notice--info"><?php esc_html_e( 'Файлы этой команды вам недоступны.', 'wp-personal-account' ); ?></div>

<?php else : ?>

	<?php if ( '' !== $wppa_team_name ) : ?>
		<p class="wppa-hint">
			<?php
			printf(
				/* translators: %s: название команды */
				'' === $wppa_direction
					? esc_html__( 'Документы команды «%s», которые прислал куратор. Их можно скачать.', 'wp-personal-account' )
					: esc_html__( 'Документы команды «%s». Их видят только наставник этой команды и куратор.', 'wp-personal-account' ),
				esc_html( $wppa_team_name )
			);
			?>
		</p>
	<?php endif; ?>

	<section class="wppa-card wppa-curator-files">
		<h3 class="wppa-subtitle"><?php esc_html_e( 'Файлы от куратора', 'wp-personal-account' ); ?></h3>

		<?php if ( $wppa_from_curator ) : ?>
			<div class="wppa-table-wrap">
				<table class="wppa-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Файл', 'wp-personal-account' ); ?></th>
							<th><?php esc_html_e( 'Размер', 'wp-personal-account' ); ?></th>
							<th><?php esc_html_e( 'Прикреплён', 'wp-personal-account' ); ?></th>
							<?php if ( $wppa_can_upload ) : ?>
								<th><?php esc_html_e( 'Действия', 'wp-personal-account' ); ?></th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $wppa_from_curator as $wppa_file ) : ?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Файл', 'wp-personal-account' ); ?>">
									<a href="<?php echo esc_url( WPPA_Curator_Files::download_url( $wppa_team_id, $wppa_file['key'] ) ); ?>">
										<?php echo esc_html( $wppa_file['name'] ); ?>
									</a>
								</td>
								<td data-label="<?php esc_attr_e( 'Размер', 'wp-personal-account' ); ?>"><?php echo esc_html( WPPA_Curator_Files::size_label( $wppa_file['size'] ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Прикреплён', 'wp-personal-account' ); ?>">
									<?php echo esc_html( $wppa_file['time'] ? date_i18n( get_option( 'date_format' ), $wppa_file['time'] ) : '—' ); ?>
								</td>
								<?php if ( $wppa_can_upload ) : ?>
									<td data-label="<?php esc_attr_e( 'Действия', 'wp-personal-account' ); ?>">
										<?php if ( 'curator' === $wppa_direction ) : ?>
											<form method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'files' ) ); ?>" class="wppa-inline-form">
												<?php wp_nonce_field( 'wppa_curator_file', 'wppa_nonce' ); ?>
												<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_team_id ); ?>" />
												<input type="hidden" name="wppa_action" value="curator_file" />
												<input type="hidden" name="curator_file_action" value="remove" />
												<input type="hidden" name="file_key" value="<?php echo esc_attr( $wppa_file['key'] ); ?>" />
												<button type="submit" class="wppa-link-button" data-wppa-confirm="<?php esc_attr_e( 'Удалить файл?', 'wp-personal-account' ); ?>"><?php esc_html_e( 'Удалить', 'wp-personal-account' ); ?></button>
											</form>
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
		<?php else : ?>
			<div class="wppa-notice wppa-notice--info"><?php esc_html_e( 'Куратор пока не прикреплял файлов для вашей команды.', 'wp-personal-account' ); ?></div>
		<?php endif; ?>
	</section>

	<?php // Пока команда не отмечена, файлов наставника не бывает — блок не показываем.
	// Участнику команды полка наставника не нужна вовсе.
	if ( WPPA_Teams::is_stage2( $wppa_team_id ) && '' !== $wppa_direction ) : ?>
	<section class="wppa-card wppa-curator-files">
		<h3 class="wppa-subtitle"><?php esc_html_e( 'Файлы от наставника', 'wp-personal-account' ); ?></h3>

		<?php if ( $wppa_from_mentor ) : ?>
			<div class="wppa-table-wrap">
				<table class="wppa-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Файл', 'wp-personal-account' ); ?></th>
							<th><?php esc_html_e( 'Размер', 'wp-personal-account' ); ?></th>
							<th><?php esc_html_e( 'Прикреплён', 'wp-personal-account' ); ?></th>
							<th><?php esc_html_e( 'Действия', 'wp-personal-account' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $wppa_from_mentor as $wppa_file ) : ?>
							<?php $wppa_may_remove = 'curator' === $wppa_direction || WPPA_Curator_Files::can_remove( $wppa_team_id, $wppa_file['key'], $user->ID ); ?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Файл', 'wp-personal-account' ); ?>">
									<a href="<?php echo esc_url( WPPA_Curator_Files::download_url( $wppa_team_id, $wppa_file['key'] ) ); ?>">
										<?php echo esc_html( $wppa_file['name'] ); ?>
									</a>
								</td>
								<td data-label="<?php esc_attr_e( 'Размер', 'wp-personal-account' ); ?>"><?php echo esc_html( WPPA_Curator_Files::size_label( $wppa_file['size'] ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Прикреплён', 'wp-personal-account' ); ?>">
									<?php echo esc_html( $wppa_file['time'] ? date_i18n( get_option( 'date_format' ), $wppa_file['time'] ) : '—' ); ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Действия', 'wp-personal-account' ); ?>">
									<?php if ( $wppa_may_remove ) : ?>
										<form method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'files' ) ); ?>" class="wppa-inline-form">
											<?php wp_nonce_field( 'wppa_curator_file', 'wppa_nonce' ); ?>
											<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_team_id ); ?>" />
											<input type="hidden" name="wppa_action" value="curator_file" />
											<input type="hidden" name="curator_file_action" value="remove" />
											<input type="hidden" name="file_key" value="<?php echo esc_attr( $wppa_file['key'] ); ?>" />
											<button type="submit" class="wppa-link-button" data-wppa-confirm="<?php esc_attr_e( 'Удалить файл?', 'wp-personal-account' ); ?>"><?php esc_html_e( 'Удалить', 'wp-personal-account' ); ?></button>
										</form>
									<?php else : ?>
										<span class="wppa-hint">—</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<div class="wppa-notice wppa-notice--info"><?php esc_html_e( 'Наставник пока не прикреплял файлов.', 'wp-personal-account' ); ?></div>
		<?php endif; ?>

	<?php endif; ?>
	<?php // Прикрепление — отдельно от полок: куратору можно всегда, наставнику после второго этапа. ?>
	<?php if ( $wppa_can_attach ) : ?>
		<section class="wppa-card wppa-upload">
			<h3 class="wppa-subtitle"><?php esc_html_e( 'Прикрепить файл', 'wp-personal-account' ); ?></h3>
			<?php echo $wppa_upload_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- статическая разметка формы. ?>
				<?php wp_nonce_field( 'wppa_curator_file', 'wppa_nonce' ); ?>
				<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $wppa_team_id ); ?>" />
				<input type="hidden" name="wppa_action" value="curator_file" />
				<input type="hidden" name="curator_file_action" value="upload" />

				<p class="wppa-field">
					<label for="wppa-team-file"><?php echo 'curator' === $wppa_direction ? esc_html__( 'Прикрепить файл для наставника', 'wp-personal-account' ) : esc_html__( 'Прикрепить файл для куратора', 'wp-personal-account' ); ?></label>
					<input type="file" id="wppa-team-file" name="curator_file" required />
				</p>

				<p class="wppa-hint">
					<?php
					printf(
						/* translators: 1: максимальный размер, 2: максимальное число файлов */
						esc_html__( 'До %1$s на файл и не больше %2$d файлов на команду: документы, таблицы, презентации, архивы, изображения.', 'wp-personal-account' ),
						esc_html( WPPA_Curator_Files::size_label( WPPA_Curator_Files::max_size() ) ),
						(int) WPPA_Curator_Files::MAX_FILES
					);
					?>
				</p>

				<?php if ( 'mentor' === $wppa_direction && WPPA_Team_Files_Export::enabled() ) : ?>
					<p class="wppa-hint"><?php esc_html_e( 'Файл сразу скопируется в сетевую папку команды — наставнику не нужно ничего выгружать вручную.', 'wp-personal-account' ); ?></p>
				<?php endif; ?>

				<button type="submit" class="wppa-button wppa-button--primary"><?php esc_html_e( 'Прикрепить', 'wp-personal-account' ); ?></button>
			</form>
		</section>
	<?php endif; ?>

	<?php if ( $wppa_is_curator && $wppa_curator_team_id ) : ?>
		<p class="wppa-hint">
			<a href="<?php echo esc_url( WPPA_Plugin::tab_url( 'files' ) ); ?>"><?php esc_html_e( 'Выбрать другую команду', 'wp-personal-account' ); ?></a>
		</p>
	<?php endif; ?>

<?php endif; ?>
