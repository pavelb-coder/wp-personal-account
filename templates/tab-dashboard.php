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

WPPA_Builder::render_tab(
	'dashboard',
	array(
		'user' => $user,
		'tabs' => $tabs,
	)
);
