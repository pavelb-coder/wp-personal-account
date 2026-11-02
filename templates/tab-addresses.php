<?php
/**
 * Вкладка «Адреса» (WooCommerce).
 *
 * Доступные переменные: $user, $tabs.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

if ( ! WPPA_Addresses::is_available() ) {
	WPPA_Template::render(
		'notice',
		array(
			'type' => 'info',
			'text' => __( 'Для работы раздела адресов требуется активный WooCommerce.', 'wp-personal-account' ),
		)
	);
	return;
}

$wppa_fields    = WPPA_Addresses::fields();
$wppa_countries = WPPA_Addresses::countries();
$wppa_billing   = WPPA_Addresses::get_values( 'billing' );
$wppa_shipping  = WPPA_Addresses::get_values( 'shipping' );
?>
<h2 class="wppa-title"><?php esc_html_e( 'Адреса', 'wp-personal-account' ); ?></h2>

<form class="wppa-form" method="post" action="<?php echo esc_url( WPPA_Plugin::tab_url( 'addresses' ) ); ?>">
	<?php wp_nonce_field( 'wppa_addresses', 'wppa_nonce' ); ?>
	<input type="hidden" name="wppa_action" value="addresses" />

	<div class="wppa-address-grid">
		<?php
		$wppa_groups = array(
			'billing'  => array( __( 'Адрес плательщика', 'wp-personal-account' ), $wppa_billing ),
			'shipping' => array( __( 'Адрес доставки', 'wp-personal-account' ), $wppa_shipping ),
		);

		foreach ( $wppa_groups as $wppa_type => $wppa_group ) :
			$wppa_title   = $wppa_group[0];
			$wppa_values  = $wppa_group[1];
			?>
			<fieldset class="wppa-card wppa-fieldset">
				<legend class="wppa-subtitle"><?php echo esc_html( $wppa_title ); ?></legend>

				<?php foreach ( $wppa_fields as $wppa_key => $wppa_field ) : ?>
					<?php $wppa_id = 'wppa-' . $wppa_type . '-' . $wppa_key; ?>
					<p class="wppa-field">
						<label for="<?php echo esc_attr( $wppa_id ); ?>"><?php echo esc_html( $wppa_field['label'] ); ?></label>

						<?php if ( 'country' === $wppa_field['type'] && $wppa_countries ) : ?>
							<select id="<?php echo esc_attr( $wppa_id ); ?>" name="wppa_address[<?php echo esc_attr( $wppa_type ); ?>][<?php echo esc_attr( $wppa_key ); ?>]">
								<option value=""><?php esc_html_e( '— выберите —', 'wp-personal-account' ); ?></option>
								<?php foreach ( $wppa_countries as $wppa_code => $wppa_country ) : ?>
									<option value="<?php echo esc_attr( $wppa_code ); ?>" <?php selected( strtoupper( (string) $wppa_values[ $wppa_key ] ), strtoupper( $wppa_code ) ); ?>>
										<?php echo esc_html( $wppa_country ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php else : ?>
							<input
								type="<?php echo esc_attr( 'tel' === $wppa_field['type'] ? 'tel' : 'text' ); ?>"
								id="<?php echo esc_attr( $wppa_id ); ?>"
								name="wppa_address[<?php echo esc_attr( $wppa_type ); ?>][<?php echo esc_attr( $wppa_key ); ?>]"
								value="<?php echo esc_attr( $wppa_values[ $wppa_key ] ); ?>"
							/>
						<?php endif; ?>
					</p>
				<?php endforeach; ?>
			</fieldset>
		<?php endforeach; ?>
	</div>

	<button type="submit" class="wppa-button wppa-button--primary"><?php esc_html_e( 'Сохранить адреса', 'wp-personal-account' ); ?></button>
</form>
