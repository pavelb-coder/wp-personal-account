<?php
/**
 * Блок «Сводка профиля».
 *
 * Доступные переменные: $user, $tabs, $block_title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_registered = $user->user_registered ? mysql2date( get_option( 'date_format' ), $user->user_registered ) : '—';
$wppa_pending    = WPPA_Profile::pending_email( $user->ID );
$wppa_team_id    = WPPA_Teams::team_id( $user->ID );
$wppa_team       = $wppa_team_id ? WPPA_Teams::get( $wppa_team_id ) : null;
?>
<?php if ( isset( $block_title ) && '' !== $block_title ) : ?>
	<h3 class="wppa-subtitle"><?php echo esc_html( $block_title ); ?></h3>
<?php endif; ?>

<div class="wppa-grid">
	<div class="wppa-card wppa-card--stat">
		<span class="wppa-card__label"><?php esc_html_e( 'Дата регистрации', 'wp-personal-account' ); ?></span>
		<span class="wppa-card__value wppa-card__value--small"><?php echo esc_html( $wppa_registered ); ?></span>
	</div>

	<div class="wppa-card wppa-card--stat">
		<span class="wppa-card__label"><?php esc_html_e( 'Email', 'wp-personal-account' ); ?></span>
		<span class="wppa-card__value wppa-card__value--small"><?php echo esc_html( $user->user_email ); ?></span>
	</div>

	<?php if ( $wppa_team ) : ?>
		<div class="wppa-card wppa-card--stat">
			<span class="wppa-card__label"><?php esc_html_e( 'Команда', 'wp-personal-account' ); ?></span>
			<span class="wppa-card__value wppa-card__value--small"><?php echo esc_html( $wppa_team['name'] ); ?></span>
			<?php if ( ! WPPA_Teams::is_complete( $wppa_team_id ) ) : ?>
				<span class="wppa-badge wppa-badge--warn"><?php esc_html_e( 'Команда не сформирована', 'wp-personal-account' ); ?></span>
			<?php else : ?>
				<span class="wppa-badge wppa-badge--ok"><?php esc_html_e( 'Команда сформирована', 'wp-personal-account' ); ?></span>
			<?php endif; ?>
			<?php if ( isset( $tabs['team'] ) ) : ?>
				<a class="wppa-link" href="<?php echo esc_url( WPPA_Plugin::tab_url( 'team' ) ); ?>"><?php esc_html_e( 'Состав команды', 'wp-personal-account' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

<?php
$wppa_personals = array();

foreach ( WPPA_Fields::meta_fields() as $wppa_field_id => $wppa_field ) {
	$wppa_value = WPPA_Fields::get( $user->ID, $wppa_field_id );

	if ( '' === $wppa_value ) {
		continue;
	}

	$wppa_personals[ $wppa_field['label'] ] = $wppa_value;
}
?>

<?php if ( $wppa_personals ) : ?>
	<div class="wppa-card wppa-card--personals">
		<span class="wppa-card__label"><?php esc_html_e( 'Личные данные', 'wp-personal-account' ); ?></span>
		<dl class="wppa-dl">
			<?php foreach ( $wppa_personals as $wppa_label => $wppa_value ) : ?>
				<div class="wppa-dl__row">
					<dt><?php echo esc_html( $wppa_label ); ?></dt>
					<dd><?php echo esc_html( $wppa_value ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
		<?php if ( isset( $tabs['profile'] ) ) : ?>
			<a class="wppa-link" href="<?php echo esc_url( WPPA_Plugin::tab_url( 'profile' ) ); ?>"><?php esc_html_e( 'Редактировать данные', 'wp-personal-account' ); ?></a>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( $wppa_pending ) : ?>
	<div class="wppa-notice wppa-notice--info">
		<?php
		printf(
			/* translators: %s: новый email, ожидающий подтверждения */
			esc_html__( 'Ожидает подтверждения адрес %s. Проверьте почту.', 'wp-personal-account' ),
			esc_html( $wppa_pending )
		);
		?>
	</div>
<?php endif; ?>
