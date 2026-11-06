<?php
/**
 * Блок «Мои сертификаты» — сертификаты, выданные участнику куратором.
 *
 * Сертификаты создаёт плагин WP Diplom: здесь только список и скачивание PDF.
 *
 * Доступные переменные: $user, $block_title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_cert_user     = isset( $user ) && $user instanceof WP_User ? $user : wp_get_current_user();
$wppa_certificates  = WPPA_Certificates::for_user( $wppa_cert_user->ID );
?>

<section class="wppa-card wppa-certificates">
	<h2 class="wppa-title">
		<?php echo isset( $block_title ) && '' !== $block_title ? esc_html( $block_title ) : esc_html__( 'Мои сертификаты', 'wp-personal-account' ); ?>
	</h2>

	<?php if ( ! $wppa_certificates ) : ?>

		<p class="wppa-hint"><?php esc_html_e( 'Сертификатов пока нет: их выдаёт куратор после проверки результатов.', 'wp-personal-account' ); ?></p>

	<?php else : ?>

		<div class="wppa-table-wrap">
			<table class="wppa-table wppa-certificates__table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Номер', 'wp-personal-account' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Программа', 'wp-personal-account' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Выдан', 'wp-personal-account' ); ?></th>
						<th scope="col"><?php esc_html_e( 'PDF', 'wp-personal-account' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wppa_certificates as $wppa_cert ) : ?>
						<tr<?php echo $wppa_cert['revoked'] ? ' class="wppa-certificates__row--revoked"' : ''; ?>>
							<td data-label="<?php esc_attr_e( 'Номер', 'wp-personal-account' ); ?>">
								<strong><?php echo esc_html( '' !== $wppa_cert['number'] ? $wppa_cert['number'] : '—' ); ?></strong>
								<?php if ( $wppa_cert['revoked'] ) : ?>
									<br /><span class="wppa-badge wppa-badge--warn"><?php esc_html_e( 'отозван', 'wp-personal-account' ); ?></span>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Программа', 'wp-personal-account' ); ?>"><?php echo esc_html( '' !== $wppa_cert['course'] ? $wppa_cert['course'] : '—' ); ?></td>
							<td data-label="<?php esc_attr_e( 'Выдан', 'wp-personal-account' ); ?>"><?php echo esc_html( $wppa_cert['time'] ? date_i18n( get_option( 'date_format' ), $wppa_cert['time'] ) : '—' ); ?></td>
							<td data-label="<?php esc_attr_e( 'PDF', 'wp-personal-account' ); ?>">
								<?php if ( $wppa_cert['revoked'] ) : ?>
									<span class="wppa-hint"><?php esc_html_e( 'Сертификат отозван куратором — скачивание закрыто.', 'wp-personal-account' ); ?></span>
								<?php else : ?>
									<a class="wppa-button wppa-button--primary wppa-button--sm" href="<?php echo esc_url( $wppa_cert['url'] ); ?>">
										<?php esc_html_e( 'Скачать', 'wp-personal-account' ); ?>
									</a>
								<?php endif; ?>
								<?php if ( $wppa_cert['sent'] ) : ?>
									<br /><span class="wppa-hint"><?php esc_html_e( 'отправлен на e-mail', 'wp-personal-account' ); ?></span>
								<?php elseif ( $wppa_cert['notified'] ) : ?>
									<br /><span class="wppa-hint"><?php esc_html_e( 'уведомление отправлено', 'wp-personal-account' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

	<?php endif; ?>

	<?php
	// Наставник видит сертификаты участников своей команды; куратору хватает колонки в «Участниках».
	$wppa_team_certs = WPPA_Teams::is_mentor( $wppa_cert_user->ID ) && ! WPPA_Teams::is_curator( $wppa_cert_user->ID )
		? WPPA_Certificates::team_rows( $wppa_cert_user->ID )
		: array();
	?>

	<?php if ( $wppa_team_certs ) : ?>

		<section class="wppa-certificates__team">
			<h3 class="wppa-subtitle"><?php esc_html_e( 'Сертификаты участников', 'wp-personal-account' ); ?></h3>
			<p class="wppa-hint"><?php esc_html_e( 'Сертификаты, которые получили участники вашей команды.', 'wp-personal-account' ); ?></p>

			<div class="wppa-table-wrap">
				<table class="wppa-table wppa-certificates__table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Участник', 'wp-personal-account' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Номер', 'wp-personal-account' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Программа', 'wp-personal-account' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Выдан', 'wp-personal-account' ); ?></th>
							<th scope="col"><?php esc_html_e( 'PDF', 'wp-personal-account' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $wppa_team_certs as $wppa_team_cert ) : ?>
							<tr<?php echo $wppa_team_cert['cert']['revoked'] ? ' class="wppa-certificates__row--revoked"' : ''; ?>>
								<td data-label="<?php esc_attr_e( 'Участник', 'wp-personal-account' ); ?>">
									<?php echo esc_html( WPPA_Teams::full_name( $wppa_team_cert['user'] ) ); ?>
									<?php if ( $wppa_team_cert['cert']['revoked'] ) : ?>
										<span class="wppa-badge wppa-badge--warn"><?php esc_html_e( 'отозван', 'wp-personal-account' ); ?></span>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Номер', 'wp-personal-account' ); ?>">
									<?php echo esc_html( '' !== $wppa_team_cert['cert']['number'] ? $wppa_team_cert['cert']['number'] : '—' ); ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Программа', 'wp-personal-account' ); ?>">
									<?php echo esc_html( '' !== $wppa_team_cert['cert']['course'] ? $wppa_team_cert['cert']['course'] : '—' ); ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Выдан', 'wp-personal-account' ); ?>">
									<?php echo esc_html( $wppa_team_cert['cert']['time'] ? date_i18n( get_option( 'date_format' ), (int) $wppa_team_cert['cert']['time'] ) : '—' ); ?>
								</td>
								<td data-label="<?php esc_attr_e( 'PDF', 'wp-personal-account' ); ?>">
									<?php if ( $wppa_team_cert['cert']['revoked'] ) : ?>
										<span class="wppa-hint"><?php esc_html_e( 'отозван', 'wp-personal-account' ); ?></span>
									<?php else : ?>
										<a class="wppa-button wppa-button--ghost wppa-button--sm" href="<?php echo esc_url( $wppa_team_cert['cert']['url'] ); ?>">
											<?php esc_html_e( 'Скачать', 'wp-personal-account' ); ?>
										</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>

	<?php endif; ?>
</section>
