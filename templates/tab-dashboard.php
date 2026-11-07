<?php
/**
 * Вкладка «Обзор» — вывод блоков конструктора.
 *
 * Доступные переменные: $user, $tabs.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

// Отметка «команда прошла во второй этап» — видна всем участникам команды и наставнику.
$wppa_dash_teams = WPPA_Teams::is_mentor( $user->ID ) ? WPPA_Teams::mentor_team_ids( $user->ID ) : array( WPPA_Teams::team_id( $user->ID ) );
?>
<?php foreach ( $wppa_dash_teams as $wppa_dash_team_id ) : ?>
<?php $wppa_dash_stage2 = $wppa_dash_team_id ? WPPA_Teams::stage2( $wppa_dash_team_id ) : array(); ?>
<?php $wppa_dash_team = $wppa_dash_stage2 ? WPPA_Teams::get( $wppa_dash_team_id ) : null; ?>
<?php if ( array() !== $wppa_dash_stage2 ) : ?>
	<div class="wppa-card wppa-stage2 wppa-stage2--notice" role="status">
		<div class="wppa-stage2__head">
			<span class="wppa-badge wppa-badge--stage2"><?php esc_html_e( 'Ваша команда прошла во второй этап', 'wp-personal-account' ); ?></span>
			<?php if ( $wppa_dash_team ) : ?>
				<strong class="wppa-stage2__team"><?php echo esc_html( $wppa_dash_team['name'] ); ?></strong>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>
<?php endforeach; ?>
<?php

// Куратору — состояние рассылок и кнопки отмены: второй этап, файлы и сертификаты.
$wppa_mail_admin = current_user_can( 'manage_options' );
$wppa_mailings   = array();

if ( WPPA_Teams::is_curator( $user->ID ) ) {
	foreach ( array( 'stage2', 'files', 'certificates' ) as $wppa_mail_kind ) {
		$wppa_mail_class = WPPA_Mailing::class_of( $wppa_mail_kind );
		$wppa_mail_state = $wppa_mail_class::stats();

		if ( empty( $wppa_mail_state['active'] ) ) {
			continue;
		}

		$wppa_mailings[ $wppa_mail_kind ] = array(
			'class' => $wppa_mail_class,
			'stats' => $wppa_mail_state,
			'next'  => $wppa_mail_class::next_run(),
		);
	}
}
?>
<?php foreach ( $wppa_mailings as $wppa_mail_kind => $wppa_mail ) : ?>
	<?php
	$wppa_mail_stats = $wppa_mail['stats'];
	$wppa_mail_next  = $wppa_mail['next'];
	$wppa_mail_titles = array(
		'stage2'       => __( 'Рассылка участникам второго этапа', 'wp-personal-account' ),
		'files'        => __( 'Рассылка о прикреплённых файлах', 'wp-personal-account' ),
		'certificates' => __( 'Рассылка о сертификатах', 'wp-personal-account' ),
	);
	$wppa_mail_title = isset( $wppa_mail_titles[ $wppa_mail_kind ] ) ? $wppa_mail_titles[ $wppa_mail_kind ] : $wppa_mail_titles['stage2'];
	?>
	<div class="wppa-card wppa-card--mailing">
		<h3 class="wppa-subtitle"><?php echo esc_html( $wppa_mail_title ); ?></h3>
		<ul class="wppa-list wppa-mailing__stats">
			<li><?php printf( esc_html__( 'Всего писем: %s', 'wp-personal-account' ), esc_html( (string) $wppa_mail_stats['total'] ) ); ?></li>
			<li><?php printf( esc_html__( 'Отправлено: %s', 'wp-personal-account' ), esc_html( (string) $wppa_mail_stats['sent'] ) ); ?></li>
			<li><?php printf( esc_html__( 'Осталось: %s', 'wp-personal-account' ), esc_html( (string) $wppa_mail_stats['pending'] ) ); ?></li>
			<?php if ( $wppa_mail_stats['failed'] > 0 ) : ?>
				<li><?php printf( esc_html__( 'Ошибок: %s', 'wp-personal-account' ), esc_html( (string) $wppa_mail_stats['failed'] ) ); ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $wppa_mail_stats['skipped'] ) ) : ?>
				<li>
					<?php
					printf(
						esc_html(
							'files' === $wppa_mail_kind
								? __( 'Пропущено: %s', 'wp-personal-account' )
								: __( 'Пропущено (отметка снята): %s', 'wp-personal-account' )
						),
						esc_html( (string) $wppa_mail_stats['skipped'] )
					);
					?>
				</li>
			<?php endif; ?>
			<li>
				<?php
				if ( ! empty( $wppa_mail_stats['done'] ) ) {
					esc_html_e( 'Состояние: рассылка завершена', 'wp-personal-account' );
				} elseif ( $wppa_mail_next > 0 ) {
					printf(
						/* translators: %s: дата и время */
						esc_html__( 'Следующее письмо: %s', 'wp-personal-account' ),
						esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $wppa_mail_next ) )
					);
				} else {
					esc_html_e( 'Состояние: расписание не задано', 'wp-personal-account' );
				}
				?>
			</li>
		</ul>

		<?php if ( ! empty( $wppa_mail_stats['paused'] ) ) : ?>
			<div class="wppa-notice wppa-notice--error"><?php echo esc_html( $wppa_mail_stats['paused'] ); ?></div>
		<?php endif; ?>

		<?php if ( isset( $tabs['mailing'] ) ) : ?>
			<p class="wppa-hint">
				<a class="wppa-link" href="<?php echo esc_url( WPPA_Plugin::tab_url( 'mailing' ) ); ?>"><?php esc_html_e( 'Открыть раздел «Рассылка»: шаблон письма, очередь и журнал', 'wp-personal-account' ); ?></a>
			</p>
		<?php endif; ?>

		<div class="wppa-actions">
			<?php if ( $wppa_mail_stats['pending'] > 0 || ! empty( $wppa_mail_stats['paused'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'dashboard' ) ); ?>" class="wppa-inline-form">
					<?php wp_nonce_field( 'wppa_mailing', 'wppa_nonce' ); ?>
					<input type="hidden" name="wppa_action" value="mailing" />
					<input type="hidden" name="wppa_mailing_action" value="cancel" />
					<input type="hidden" name="mailing_kind" value="<?php echo esc_attr( $wppa_mail_kind ); ?>" />
					<button type="submit" class="wppa-button wppa-button--ghost"><?php esc_html_e( 'Отменить рассылку', 'wp-personal-account' ); ?></button>
				</form>
			<?php endif; ?>
			<?php if ( $wppa_mail_admin ) : ?>
				<a class="wppa-link" href="<?php echo esc_url( add_query_arg( 'wppa_kind', $wppa_mail_kind, admin_url( 'admin.php?page=wppa-mailing' ) ) ); ?>"><?php esc_html_e( 'Шаблон и очередь в админке', 'wp-personal-account' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
<?php endforeach; ?>
<?php

WPPA_Builder::render_tab(
	'dashboard',
	array(
		'user' => $user,
		'tabs' => $tabs,
	)
);
