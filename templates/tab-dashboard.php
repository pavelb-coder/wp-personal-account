<?php
/**
 * Вкладка «Обзор».
 *
 * Доступные переменные: $user, $tabs.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_registered = $user->user_registered ? mysql2date( get_option( 'date_format' ), $user->user_registered ) : '—';
$wppa_pending    = WPPA_Profile::pending_email( $user->ID );
?>
<h2 class="wppa-title">
	<?php
	printf(
		/* translators: %s: имя пользователя */
		esc_html__( 'Здравствуйте, %s!', 'wp-personal-account' ),
		esc_html( $user->display_name )
	);
	?>
</h2>

<div class="wppa-grid">
	<div class="wppa-card wppa-card--stat">
		<span class="wppa-card__label"><?php esc_html_e( 'Дата регистрации', 'wp-personal-account' ); ?></span>
		<span class="wppa-card__value wppa-card__value--small"><?php echo esc_html( $wppa_registered ); ?></span>
	</div>

	<div class="wppa-card wppa-card--stat">
		<span class="wppa-card__label"><?php esc_html_e( 'Email', 'wp-personal-account' ); ?></span>
		<span class="wppa-card__value wppa-card__value--small"><?php echo esc_html( $user->user_email ); ?></span>
	</div>

	<div class="wppa-card wppa-card--stat">
		<span class="wppa-card__label"><?php esc_html_e( 'Роль', 'wp-personal-account' ); ?></span>
		<span class="wppa-card__value wppa-card__value--small">
			<?php
			$wppa_roles = array_intersect_key( wp_roles()->get_names(), array_flip( (array) $user->roles ) );
			echo esc_html( $wppa_roles ? implode( ', ', array_map( 'translate_user_role', $wppa_roles ) ) : '—' );
			?>
		</span>
	</div>
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

<h3 class="wppa-subtitle"><?php esc_html_e( 'Быстрые действия', 'wp-personal-account' ); ?></h3>
<div class="wppa-actions">
	<?php if ( isset( $tabs['profile'] ) ) : ?>
		<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( WPPA_Plugin::tab_url( 'profile' ) ); ?>"><?php esc_html_e( 'Редактировать профиль', 'wp-personal-account' ); ?></a>
	<?php endif; ?>
	<?php if ( isset( $tabs['security'] ) ) : ?>
		<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( WPPA_Plugin::tab_url( 'security' ) ); ?>"><?php esc_html_e( 'Сменить пароль', 'wp-personal-account' ); ?></a>
	<?php endif; ?>
	<a class="wppa-button wppa-button--ghost" data-wppa-confirm-logout href="<?php echo esc_url( wp_logout_url( WPPA_Plugin::account_url() ) ); ?>">
		<?php esc_html_e( 'Выйти', 'wp-personal-account' ); ?>
	</a>
</div>
