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
$wppa_team_ids = WPPA_Teams::is_mentor( $user->ID ) ? WPPA_Teams::mentor_team_ids( $user->ID ) : array( WPPA_Teams::team_id( $user->ID ) );
?>
<?php if ( isset( $block_title ) && '' !== $block_title ) : ?>
	<h3 class="wppa-subtitle"><?php echo esc_html( $block_title ); ?></h3>
<?php endif; ?>

<div class="wppa-grid">
	<div class="wppa-card wppa-card--stat">
		<span class="wppa-card__label"><?php esc_html_e( 'Дата регистрации', 'wp-personal-account' ); ?></span>
		<span class="wppa-card__value wppa-card__value--small"><?php echo esc_html( $wppa_registered ); ?></span>
	</div>

	<?php foreach ( $wppa_team_ids as $wppa_team_id ) : ?>
	<?php $wppa_team = $wppa_team_id ? WPPA_Teams::get( $wppa_team_id ) : null; ?>
	<?php if ( $wppa_team ) : ?>
		<div class="wppa-card wppa-card--stat">
			<span class="wppa-card__label"><?php esc_html_e( 'Команда', 'wp-personal-account' ); ?></span>
			<span class="wppa-card__value wppa-card__value--small"><?php echo esc_html( $wppa_team['name'] ); ?></span>
			<?php if ( ! WPPA_Teams::is_complete( $wppa_team_id ) ) : ?>
				<span class="wppa-badge wppa-badge--warn"><?php esc_html_e( 'Команда не сформирована', 'wp-personal-account' ); ?></span>
			<?php else : ?>
				<span class="wppa-badge wppa-badge--ok"><?php esc_html_e( 'Команда сформирована', 'wp-personal-account' ); ?></span>
			<?php endif; ?>
			<?php if ( WPPA_Teams::is_stage2( $wppa_team_id ) ) : ?>
				<span class="wppa-badge wppa-badge--stage2"><?php esc_html_e( 'Прошла во второй этап', 'wp-personal-account' ); ?></span>
			<?php endif; ?>
			<?php if ( isset( $tabs['team'] ) ) : ?>
				<a class="wppa-link" href="<?php echo esc_url( WPPA_Teams::context_url( $wppa_team_id, $user->ID ) ); ?>"><?php esc_html_e( 'Состав команды', 'wp-personal-account' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<?php endforeach; ?>
</div>

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
