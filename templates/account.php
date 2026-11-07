<?php
/**
 * Каркас личного кабинета: навигация + активная вкладка.
 *
 * Доступные переменные: $user, $tabs, $current_tab.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wppa">

	<?php
	// Заголовок кабинета: текст, тег и оформление задаются в «Настройки → Оформление».
	$wppa_heading = WPPA_Theme::heading();

	if ( ! empty( $wppa_heading['show'] ) ) :
		?>
		<<?php echo esc_attr( $wppa_heading['tag'] ); ?> class="wppa-title wppa-page-title">
			<?php echo esc_html( $wppa_heading['text'] ); ?>
		</<?php echo esc_attr( $wppa_heading['tag'] ); ?>>
		<?php
	endif;
	?>

	<header class="wppa-header">
		<div class="wppa-header__user">
			<div class="wppa-avatar" aria-hidden="true">
				<?php echo get_avatar( $user->ID, 56, '', '', array( 'class' => 'wppa-avatar__img' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- разметка от WP. ?>
			</div>
			<div>
				<p class="wppa-header__name"><?php echo esc_html( $user->display_name ); ?></p>
				<?php if ( ! isset( $current_tab ) || 'dashboard' !== $current_tab ) : ?>
					<p class="wppa-header__email"><?php echo esc_html( $user->user_email ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<div class="wppa-header__actions">
			<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php esc_html_e( 'Вернуться на сайт', 'wp-personal-account' ); ?>
			</a>
			<a class="wppa-button wppa-button--ghost" href="<?php echo esc_url( wp_logout_url( WPPA_Plugin::account_url() ) ); ?>">
				<?php esc_html_e( 'Выйти', 'wp-personal-account' ); ?>
			</a>
		</div>
	</header>

	<?php WPPA_Notices::render(); ?>

	<nav class="wppa-nav" aria-label="<?php esc_attr_e( 'Разделы личного кабинета', 'wp-personal-account' ); ?>">
		<?php
		foreach ( $tabs as $slug => $label ) :
			// У куратора «Сертификаты» ведут в плагин WP Diplom — там выдача и правка.
			$wppa_link = WPPA_Template::tab_link( $slug );
			?>
			<a
				class="wppa-nav__link<?php echo $slug === $current_tab ? ' is-active' : ''; ?><?php echo ! empty( $wppa_link['external'] ) ? ' wppa-nav__link--external' : ''; ?>"
				href="<?php echo esc_url( $wppa_link['url'] ); ?>"
				<?php echo ! empty( $wppa_link['external'] ) ? 'target="' . esc_attr( $wppa_link['target'] ) . '" rel="noopener' . ( '_blank' === $wppa_link['target'] ? ' noreferrer' : '' ) . '"' : ''; ?>
				<?php echo $slug === $current_tab ? 'aria-current="page"' : ''; ?>
			>
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="wppa-body">
		<?php
		$tab_file = 'tab-' . $current_tab;

		if ( ! file_exists( WPPA_Template::locate( $tab_file ) ) ) {
			$tab_file = 'tab-profile';
		}

		WPPA_Template::render(
			$tab_file,
			array(
				'user' => $user,
				'tabs' => $tabs,
			)
		);
		?>
	</div>
</div>
