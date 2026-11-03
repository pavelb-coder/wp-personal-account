<?php
/**
 * Блок «Баллы за тесты» — результаты олимпиад в личном кабинете.
 *
 * Доступные переменные: $user, $tabs, $block_title, $block_id.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $user ) || ! $user instanceof WP_User ) {
	return;
}

if ( ! WPPA_Olympiad::is_active() ) {
	return;
}

$wppa_totals  = WPPA_Olympiad::totals( $user->ID );
$wppa_results = WPPA_Olympiad::results( $user->ID );
$wppa_title   = isset( $block_title ) && '' !== $block_title ? $block_title : __( 'Баллы за тесты', 'wp-personal-account' );
?>
<section class="wppa-card wppa-scores">
	<h3 class="wppa-subtitle"><?php echo esc_html( $wppa_title ); ?></h3>

	<?php if ( empty( $wppa_results ) ) : ?>
		<p class="wppa-muted"><?php esc_html_e( 'Пока нет ни одной завершённой работы.', 'wp-personal-account' ); ?></p>
	<?php else : ?>
		<div class="wppa-scores__totals">
			<div class="wppa-scores__metric">
				<span class="wppa-scores__value"><?php echo esc_html( $wppa_totals['score'] ); ?></span>
				<span class="wppa-scores__label"><?php esc_html_e( 'баллов всего', 'wp-personal-account' ); ?></span>
			</div>
			<div class="wppa-scores__metric">
				<span class="wppa-scores__value"><?php echo esc_html( $wppa_totals['tests'] ); ?></span>
				<span class="wppa-scores__label"><?php esc_html_e( 'работ зачтено', 'wp-personal-account' ); ?></span>
			</div>
			<div class="wppa-scores__metric">
				<span class="wppa-scores__value"><?php echo esc_html( WPPA_Olympiad::format_percentage( $wppa_totals['percentage'] ) ); ?>%</span>
				<span class="wppa-scores__label"><?php esc_html_e( 'общий результат', 'wp-personal-account' ); ?></span>
			</div>
		</div>

		<?php if ( $wppa_totals['pending'] > 0 ) : ?>
			<p class="wppa-muted">
				<?php
				printf(
					/* translators: %d: число работ */
					esc_html( _n( 'На проверке: %d работа.', 'На проверке: %d работ.', $wppa_totals['pending'], 'wp-personal-account' ) ),
					(int) $wppa_totals['pending']
				);
				?>
			</p>
		<?php endif; ?>

		<table class="wppa-scores__table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Тест', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Баллы', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Результат', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Статус', 'wp-personal-account' ); ?></th>
					<th><?php esc_html_e( 'Дата', 'wp-personal-account' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $wppa_results as $wppa_result ) : ?>
					<tr>
						<td><?php echo esc_html( $wppa_result['title'] ); ?></td>
						<td>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: баллы, 2: максимум */
									__( '%1$d из %2$d', 'wp-personal-account' ),
									$wppa_result['score'],
									$wppa_result['max_score']
								)
							);
							?>
						</td>
						<td><?php echo esc_html( WPPA_Olympiad::format_percentage( $wppa_result['percentage'] ) ); ?>%</td>
						<td>
							<span class="wppa-scores__status wppa-scores__status--<?php echo esc_attr( $wppa_result['counted'] ? 'ok' : 'muted' ); ?>">
								<?php echo esc_html( $wppa_result['status_label'] ); ?>
							</span>
						</td>
						<td>
							<?php
							echo esc_html(
								'' !== $wppa_result['submitted_at'] && '0000-00-00 00:00:00' !== $wppa_result['submitted_at']
									? mysql2date( 'd.m.Y H:i', $wppa_result['submitted_at'] )
									: '—'
							);
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
