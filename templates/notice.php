<?php
/**
 * Универсальное уведомление.
 *
 * Доступные переменные: $type, $text.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_type = isset( $type ) && in_array( $type, array( 'success', 'error', 'info' ), true ) ? $type : 'info';
?>
<div class="wppa-notice wppa-notice--<?php echo esc_attr( $wppa_type ); ?>" role="status">
	<?php echo esc_html( isset( $text ) ? $text : '' ); ?>
</div>
