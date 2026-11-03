<?php
/**
 * Блок «Форма профиля».
 *
 * Доступные переменные: $user, $tabs, $block_title.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;
?>
<h2 class="wppa-title">
	<?php echo isset( $block_title ) && '' !== $block_title ? esc_html( $block_title ) : esc_html__( 'Профиль', 'wp-personal-account' ); ?>
</h2>

<form class="wppa-form" method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'profile' ) ); ?>">
	<?php wp_nonce_field( 'wppa_profile', 'wppa_nonce' ); ?>
	<input type="hidden" name="wppa_action" value="profile" />

	<div class="wppa-row">
		<p class="wppa-field">
			<label for="wppa-first-name"><?php esc_html_e( 'Имя', 'wp-personal-account' ); ?></label>
			<input type="text" id="wppa-first-name" name="first_name" value="<?php echo esc_attr( $user->first_name ); ?>" autocomplete="given-name" />
		</p>

		<p class="wppa-field">
			<label for="wppa-last-name"><?php esc_html_e( 'Фамилия', 'wp-personal-account' ); ?></label>
			<input type="text" id="wppa-last-name" name="last_name" value="<?php echo esc_attr( $user->last_name ); ?>" autocomplete="family-name" />
		</p>
	</div>

	<p class="wppa-field">
		<label for="wppa-display-name"><?php esc_html_e( 'Отображаемое имя', 'wp-personal-account' ); ?></label>
		<input type="text" id="wppa-display-name" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>" required />
	</p>

	<p class="wppa-field">
		<label for="wppa-description"><?php esc_html_e( 'О себе', 'wp-personal-account' ); ?></label>
		<textarea id="wppa-description" name="description" rows="4"><?php echo esc_textarea( $user->description ); ?></textarea>
	</p>

	<p class="wppa-field">
		<label><?php esc_html_e( 'Email', 'wp-personal-account' ); ?></label>
		<input type="email" value="<?php echo esc_attr( $user->user_email ); ?>" readonly />
		<span class="wppa-hint">
			<?php
			if ( isset( $tabs['security'] ) ) {
				printf(
					/* translators: %s: ссылка */
					esc_html__( 'Сменить email можно в разделе %s.', 'wp-personal-account' ),
					'<a href="' . esc_url( WPPA_Plugin::tab_url( 'security' ) ) . '">' . esc_html__( 'Безопасность', 'wp-personal-account' ) . '</a>'
				);
			} else {
				esc_html_e( 'Email меняется в разделе «Безопасность».', 'wp-personal-account' );
			}
			?>
		</span>
	</p>

	<button type="submit" class="wppa-button wppa-button--primary"><?php esc_html_e( 'Сохранить', 'wp-personal-account' ); ?></button>
</form>
