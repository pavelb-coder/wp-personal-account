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
$wppa_dash_team_id = WPPA_Teams::team_id( $user->ID );
$wppa_dash_stage2  = $wppa_dash_team_id ? WPPA_Teams::stage2( $wppa_dash_team_id ) : array();
$wppa_dash_team    = $wppa_dash_stage2 ? WPPA_Teams::get( $wppa_dash_team_id ) : null;
?>
<?php if ( array() !== $wppa_dash_stage2 ) : ?>
	<div class="wppa-card wppa-stage2 wppa-stage2--notice" role="status">
		<div class="wppa-stage2__head">
			<span class="wppa-badge wppa-badge--stage2"><?php esc_html_e( 'Прошла во второй этап', 'wp-personal-account' ); ?></span>
			<?php if ( $wppa_dash_team ) : ?>
				<strong class="wppa-stage2__team"><?php echo esc_html( $wppa_dash_team['name'] ); ?></strong>
			<?php endif; ?>
		</div>
		<p class="wppa-hint">
			<?php
			if ( ! empty( $wppa_dash_stage2['time'] ) ) {
				printf(
					/* translators: %s: дата отметки */
					esc_html__( 'Ваша команда прошла во второй этап — отмечено %s.', 'wp-personal-account' ),
					esc_html( date_i18n( get_option( 'date_format' ), (int) $wppa_dash_stage2['time'] ) )
				);
			} else {
				esc_html_e( 'Ваша команда прошла во второй этап.', 'wp-personal-account' );
			}
			?>
		</p>
		<?php if ( isset( $tabs['team'] ) ) : ?>
			<a class="wppa-link" href="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>"><?php esc_html_e( 'Перейти к команде', 'wp-personal-account' ); ?></a>
		<?php endif; ?>
	</div>
<?php endif; ?>
<?php

// Куратору — состояние рассылки участникам второго этапа и кнопка отмены.
$wppa_mail_stats   = WPPA_Teams::is_curator( $user->ID ) ? WPPA_Mailing::stats() : array();
$wppa_mail_next    = ! empty( $wppa_mail_stats['active'] ) ? WPPA_Mailing::next_run() : 0;
$wppa_mail_admin   = current_user_can( 'manage_options' );
?>
<?php if ( ! empty( $wppa_mail_stats['active'] ) ) : ?>
	<div class="wppa-card wppa-card--mailing">
		<h3 class="wppa-subtitle"><?php esc_html_e( 'Рассылка участникам второго этапа', 'wp-personal-account' ); ?></h3>
		<ul class="wppa-list wppa-mailing__stats">
			<li><?php printf( esc_html__( 'Всего писем: %s', 'wp-personal-account' ), esc_html( (string) $wppa_mail_stats['total'] ) ); ?></li>
			<li><?php printf( esc_html__( 'Отправлено: %s', 'wp-personal-account' ), esc_html( (string) $wppa_mail_stats['sent'] ) ); ?></li>
			<li><?php printf( esc_html__( 'Осталось: %s', 'wp-personal-account' ), esc_html( (string) $wppa_mail_stats['pending'] ) ); ?></li>
			<?php if ( $wppa_mail_stats['failed'] > 0 ) : ?>
				<li><?php printf( esc_html__( 'Ошибок: %s', 'wp-personal-account' ), esc_html( (string) $wppa_mail_stats['failed'] ) ); ?></li>
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

		<div class="wppa-actions">
			<?php if ( $wppa_mail_stats['pending'] > 0 || ! empty( $wppa_mail_stats['paused'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'dashboard' ) ); ?>" class="wppa-inline-form">
					<?php wp_nonce_field( 'wppa_mailing', 'wppa_nonce' ); ?>
					<input type="hidden" name="wppa_action" value="mailing" />
					<input type="hidden" name="wppa_mailing_action" value="cancel" />
					<button type="submit" class="wppa-button wppa-button--ghost"><?php esc_html_e( 'Отменить рассылку', 'wp-personal-account' ); ?></button>
				</form>
			<?php endif; ?>
			<?php if ( $wppa_mail_admin ) : ?>
				<a class="wppa-link" href="<?php echo esc_url( admin_url( 'admin.php?page=wppa-mailing' ) ); ?>"><?php esc_html_e( 'Шаблон и очередь в админке', 'wp-personal-account' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>
<?php

WPPA_Builder::render_tab(
	'dashboard',
	array(
		'user' => $user,
		'tabs' => $tabs,
	)
);
