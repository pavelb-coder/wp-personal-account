<?php
/**
 * Блок «Файлы от куратора».
 *
 * Отдельный раздел кабинета: наставник команды видит здесь документы, которые
 * прикрепил куратор, и скачивает их. Куратор видит файлы выбранной команды и
 * ссылку на управление ими.
 *
 * Доступные переменные: $user, $tabs, $block_title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_is_curator = WPPA_Teams::is_curator( $user->ID );
$wppa_is_mentor  = WPPA_Teams::is_mentor( $user->ID );
$wppa_my_team_id = (int) WPPA_Teams::team_id( $user->ID );

// Куратор открывает файлы любой команды: ?wppa_tab=files&wppa_team=ID
$wppa_curator_team_id = $wppa_is_curator ? WPPA_Teams::viewing_team_id( $user->ID ) : 0;
$wppa_team_id         = $wppa_curator_team_id ? $wppa_curator_team_id : $wppa_my_team_id;
$wppa_files           = ( $wppa_team_id && WPPA_Curator_Files::can_view( $wppa_team_id, $user->ID ) )
	? WPPA_Curator_Files::files( $wppa_team_id )
	: array();
$wppa_team_name       = $wppa_team_id ? (string) get_the_title( $wppa_team_id ) : '';
?>

<h2 class="wppa-title">
	<?php echo isset( $block_title ) && '' !== $block_title ? esc_html( $block_title ) : esc_html__( 'Файлы', 'wp-personal-account' ); ?>
</h2>

<?php if ( ! $wppa_team_id ) : ?>

	<?php if ( $wppa_is_curator ) : ?>
		<section class="wppa-card wppa-curator-files">
			<h3 class="wppa-subtitle"><?php esc_html_e( 'Файлы команд', 'wp-personal-account' ); ?></h3>
			<p class="wppa-hint"><?php esc_html_e( 'Выберите команду — и увидите документы, которые для неё прикреплены. Прикреплять и удалять файлы можно на вкладке «Команда», в карточке «Файлы для наставника».', 'wp-personal-account' ); ?></p>

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
		<div class="wppa-notice wppa-notice--info"><?php esc_html_e( 'Раздел с файлами доступен наставнику команды: документы прикрепляет куратор.', 'wp-personal-account' ); ?></div>
	<?php endif; ?>

<?php else : ?>

	<section class="wppa-card wppa-curator-files">
		<h3 class="wppa-subtitle"><?php esc_html_e( 'Файлы от куратора', 'wp-personal-account' ); ?></h3>

		<?php if ( '' !== $wppa_team_name ) : ?>
			<p class="wppa-hint">
				<?php
				printf(
					/* translators: %s: название команды */
					esc_html__( 'Документы для команды «%s».', 'wp-personal-account' ),
					esc_html( $wppa_team_name )
				);
				?>
			</p>
		<?php endif; ?>

		<?php if ( $wppa_files ) : ?>
			<div class="wppa-table-wrap">
				<table class="wppa-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Файл', 'wp-personal-account' ); ?></th>
							<th><?php esc_html_e( 'Размер', 'wp-personal-account' ); ?></th>
							<th><?php esc_html_e( 'Прикреплён', 'wp-personal-account' ); ?></th>
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
								<td data-label="<?php esc_attr_e( 'Размер', 'wp-personal-account' ); ?>"><?php echo esc_html( WPPA_Curator_Files::size_label( $wppa_file['size'] ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Прикреплён', 'wp-personal-account' ); ?>">
									<?php echo esc_html( $wppa_file['time'] ? date_i18n( get_option( 'date_format' ), $wppa_file['time'] ) : '—' ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $wppa_is_curator ) : ?>
				<p class="wppa-hint">
					<a href="<?php echo esc_url( add_query_arg( 'wppa_team', (int) $wppa_team_id, WPPA_Plugin::tab_url( 'team' ) ) ); ?>"><?php esc_html_e( 'Прикрепить или удалить файлы', 'wp-personal-account' ); ?></a>
				</p>
			<?php else : ?>
				<p class="wppa-hint"><?php esc_html_e( 'Файлы прикрепил куратор. Их видят только наставники команды — участникам они недоступны.', 'wp-personal-account' ); ?></p>
			<?php endif; ?>
		<?php else : ?>
			<div class="wppa-notice wppa-notice--info"><?php esc_html_e( 'Куратор пока не прикреплял файлов для вашей команды.', 'wp-personal-account' ); ?></div>

			<?php if ( $wppa_is_curator ) : ?>
				<p class="wppa-hint">
					<a href="<?php echo esc_url( add_query_arg( 'wppa_team', (int) $wppa_team_id, WPPA_Plugin::tab_url( 'team' ) ) ); ?>"><?php esc_html_e( 'Прикрепить файл', 'wp-personal-account' ); ?></a>
				</p>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $wppa_is_curator && $wppa_curator_team_id ) : ?>
			<p class="wppa-hint">
				<a href="<?php echo esc_url( WPPA_Plugin::tab_url( 'files' ) ); ?>"><?php esc_html_e( 'Выбрать другую команду', 'wp-personal-account' ); ?></a>
			</p>
		<?php endif; ?>
	</section>

<?php endif; ?>
