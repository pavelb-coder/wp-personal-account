<?php
/**
 * Блок «Мои сертификаты» — сертификаты, выданные участнику куратором.
 *
 * Сертификаты создаёт плагин WP Diplom: здесь список и скачивание PDF,
 * наставнику — сертификаты участников команды, куратору — панель
 * редактирования выданных сертификатов.
 *
 * Доступные переменные: $user, $block_title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_cert_user      = isset( $user ) && $user instanceof WP_User ? $user : wp_get_current_user();
$wppa_certificates   = WPPA_Certificates::for_user( $wppa_cert_user->ID );
$wppa_cert_is_curator = WPPA_Teams::is_curator( $wppa_cert_user->ID );

// Наставник видит сертификаты участников своей команды.
$wppa_team_certs = WPPA_Teams::is_mentor( $wppa_cert_user->ID ) && ! $wppa_cert_is_curator
	? WPPA_Certificates::team_rows( $wppa_cert_user->ID )
	: array();

// Панель редактирования — только куратору и только при активном WP Diplom.
$wppa_cert_panel = $wppa_cert_is_curator && WPPA_Certificates::available()
	? WPPA_Certificates::team_rows( $wppa_cert_user->ID )
	: array();

$wppa_cert_panel_on = $wppa_cert_is_curator && WPPA_Certificates::available();

// Ни сертификатов, ни участников с сертификатами, ни панели куратора — блока нет вовсе.
if ( ! $wppa_certificates && ! $wppa_team_certs && ! $wppa_cert_panel_on ) {
	return;
}

// Поля панели редактирования: ключ => подпись и тип.
$wppa_cert_edit_fields = array(
	'recipient' => array( __( 'Получатель', 'wp-personal-account' ), 'text', true ),
	'email'     => array( __( 'E-mail', 'wp-personal-account' ), 'email', false ),
	'course'    => array( __( 'Программа', 'wp-personal-account' ), 'text', false ),
	'hours'     => array( __( 'Часы', 'wp-personal-account' ), 'text', false ),
	'teacher'   => array( __( 'Преподаватель', 'wp-personal-account' ), 'text', false ),
	'date'      => array( __( 'Дата выдачи', 'wp-personal-account' ), 'date', false ),
	'number'    => array( __( 'Номер', 'wp-personal-account' ), 'text', false ),
);
?>

<section class="wppa-card wppa-certificates">
	<h2 class="wppa-title">
		<?php echo isset( $block_title ) && '' !== $block_title ? esc_html( $block_title ) : esc_html__( 'Мои сертификаты', 'wp-personal-account' ); ?>
	</h2>

	<?php if ( ! $wppa_certificates && ! $wppa_cert_is_curator ) : ?>

		<p class="wppa-hint"><?php esc_html_e( 'Своих сертификатов пока нет: у вас появятся здесь после выдачи.', 'wp-personal-account' ); ?></p>

	<?php elseif ( $wppa_certificates ) : ?>

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
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

	<?php endif; ?>

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

	<?php if ( $wppa_cert_panel_on ) : ?>

		<section class="wppa-certificates__admin">
			<h3 class="wppa-subtitle"><?php esc_html_e( 'Редактирование сертификатов', 'wp-personal-account' ); ?></h3>
			<p class="wppa-hint">
				<?php esc_html_e( 'Правьте данные уже выданного сертификата: после сохранения PDF пересобирается, а файл в папке обновляется. Номер и программу участник увидит в своей вкладке сразу.', 'wp-personal-account' ); ?>
			</p>

			<?php if ( ! $wppa_cert_panel ) : ?>

				<p class="wppa-hint"><?php esc_html_e( 'Выданных сертификатов пока нет — их выдают во вкладке «Участники».', 'wp-personal-account' ); ?></p>

			<?php else : ?>

				<?php $wppa_cert_templates = WPPA_Certificates::templates(); ?>

				<?php foreach ( $wppa_cert_panel as $wppa_cert_row ) : ?>
					<?php
					$wppa_cert_pid  = (int) $wppa_cert_row['user']->ID;
					$wppa_cert_cid  = (int) $wppa_cert_row['cert']['id'];
					$wppa_cert_data = WPPA_Certificates::cert_data( $wppa_cert_cid );
					$wppa_cert_data = array_merge(
						array(
							'recipient' => '',
							'email'     => '',
							'course'    => '',
							'hours'     => '',
							'teacher'   => '',
							'date'      => '',
							'number'    => '',
							'template'  => 0,
							'revoked'   => false,
						),
						(array) $wppa_cert_data
					);
					$wppa_cert_action_url = WPPA_Plugin::tab_url( 'certificates' );
					?>
					<details class="wppa-certificates__editor">
						<summary class="wppa-certificates__summary">
							<strong><?php echo esc_html( WPPA_Teams::full_name( $wppa_cert_row['user'] ) ); ?></strong>
							<span class="wppa-hint">
								<?php echo esc_html( '' !== $wppa_cert_data['number'] ? $wppa_cert_data['number'] : '—' ); ?>
								<?php if ( '' !== $wppa_cert_data['course'] ) : ?>
									· <?php echo esc_html( $wppa_cert_data['course'] ); ?>
								<?php endif; ?>
							</span>
							<?php if ( $wppa_cert_data['revoked'] ) : ?>
								<span class="wppa-badge wppa-badge--warn"><?php esc_html_e( 'отозван', 'wp-personal-account' ); ?></span>
							<?php endif; ?>
							<span class="wppa-certificates__summary-edit"><?php esc_html_e( 'Изменить', 'wp-personal-account' ); ?></span>
						</summary>

						<form method="post" action="<?php echo esc_url( $wppa_cert_action_url ); ?>" class="wppa-certificates__edit">
							<?php wp_nonce_field( 'wppa_certificate', 'wppa_nonce' ); ?>
							<input type="hidden" name="wppa_action" value="certificate" />
							<input type="hidden" name="certificate_action" value="update" />
							<input type="hidden" name="certificate_back" value="certificates" />
							<input type="hidden" name="participant_id" value="<?php echo esc_attr( (string) $wppa_cert_pid ); ?>" />
							<input type="hidden" name="certificate_id" value="<?php echo esc_attr( (string) $wppa_cert_cid ); ?>" />

							<div class="wppa-certificates__fields">
								<?php foreach ( $wppa_cert_edit_fields as $wppa_cert_key => $wppa_cert_field ) : ?>
									<?php $wppa_cert_field_id = 'wppa-cert-' . $wppa_cert_cid . '-' . $wppa_cert_key; ?>
									<div class="wppa-field">
										<label for="<?php echo esc_attr( $wppa_cert_field_id ); ?>"><?php echo esc_html( $wppa_cert_field[0] ); ?></label>
										<input
											type="<?php echo esc_attr( $wppa_cert_field[1] ); ?>"
											id="<?php echo esc_attr( $wppa_cert_field_id ); ?>"
											name="certificate_fields[<?php echo esc_attr( $wppa_cert_key ); ?>]"
											value="<?php echo esc_attr( (string) $wppa_cert_data[ $wppa_cert_key ] ); ?>"
											<?php echo $wppa_cert_field[2] ? 'required' : ''; ?>
										/>
									</div>
								<?php endforeach; ?>

								<?php if ( $wppa_cert_templates ) : ?>
									<div class="wppa-field">
										<label for="wppa-cert-<?php echo esc_attr( (string) $wppa_cert_cid ); ?>-template"><?php esc_html_e( 'Бланк', 'wp-personal-account' ); ?></label>
										<select id="wppa-cert-<?php echo esc_attr( (string) $wppa_cert_cid ); ?>-template" name="certificate_fields[template]">
											<option value="0"><?php esc_html_e( '— по умолчанию —', 'wp-personal-account' ); ?></option>
											<?php foreach ( $wppa_cert_templates as $wppa_cert_template_id => $wppa_cert_template_title ) : ?>
												<option value="<?php echo esc_attr( (string) $wppa_cert_template_id ); ?>" <?php selected( (int) $wppa_cert_data['template'], (int) $wppa_cert_template_id ); ?>>
													<?php echo esc_html( $wppa_cert_template_title ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
								<?php endif; ?>

								<div class="wppa-field wppa-field--check">
									<input type="hidden" name="certificate_fields[revoked_set]" value="1" />
									<label>
										<input type="checkbox" name="certificate_fields[revoked]" value="1" <?php checked( $wppa_cert_data['revoked'] ); ?> />
										<?php esc_html_e( 'Сертификат отозван — скачивание закрыто', 'wp-personal-account' ); ?>
									</label>
								</div>
							</div>

							<button type="submit" class="wppa-button wppa-button--primary wppa-button--sm">
								<?php esc_html_e( 'Сохранить', 'wp-personal-account' ); ?>
							</button>
						</form>

						<div class="wppa-certificates__actions">
							<form method="post" action="<?php echo esc_url( $wppa_cert_action_url ); ?>">
								<?php wp_nonce_field( 'wppa_certificate', 'wppa_nonce' ); ?>
								<input type="hidden" name="wppa_action" value="certificate" />
								<input type="hidden" name="certificate_action" value="rebuild" />
								<input type="hidden" name="certificate_back" value="certificates" />
								<input type="hidden" name="participant_id" value="<?php echo esc_attr( (string) $wppa_cert_pid ); ?>" />
								<input type="hidden" name="certificate_id" value="<?php echo esc_attr( (string) $wppa_cert_cid ); ?>" />
								<button type="submit" class="wppa-button wppa-button--ghost wppa-button--sm">
									<?php esc_html_e( 'Пересобрать PDF', 'wp-personal-account' ); ?>
								</button>
							</form>

							<form method="post" action="<?php echo esc_url( $wppa_cert_action_url ); ?>">
								<?php wp_nonce_field( 'wppa_certificate', 'wppa_nonce' ); ?>
								<input type="hidden" name="wppa_action" value="certificate" />
								<input type="hidden" name="certificate_action" value="remove" />
								<input type="hidden" name="certificate_back" value="certificates" />
								<input type="hidden" name="participant_id" value="<?php echo esc_attr( (string) $wppa_cert_pid ); ?>" />
								<input type="hidden" name="certificate_id" value="<?php echo esc_attr( (string) $wppa_cert_cid ); ?>" />
								<button type="submit" class="wppa-button wppa-button--danger wppa-button--sm">
									<?php esc_html_e( 'Убрать из кабинета', 'wp-personal-account' ); ?>
								</button>
							</form>

							<a class="wppa-button wppa-button--ghost wppa-button--sm" href="<?php echo esc_url( $wppa_cert_row['cert']['url'] ); ?>">
								<?php esc_html_e( 'Скачать PDF', 'wp-personal-account' ); ?>
							</a>
						</div>
					</details>
				<?php endforeach; ?>

			<?php endif; ?>
		</section>

	<?php endif; ?>
</section>
