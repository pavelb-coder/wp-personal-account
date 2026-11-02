<?php
/**
 * Удаление данных плагина при деинсталляции.
 *
 * Выполняется, только если в настройках включён пункт
 * «Удалять настройки плагина при деинсталляции».
 *
 * @package WP_Personal_Account
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$wppa_settings = get_option( 'wppa_settings', array() );

if ( empty( $wppa_settings['delete_data_on_uninstall'] ) ) {
	return;
}

delete_option( 'wppa_settings' );

$wppa_meta_keys = array(
	'_wppa_pending_email',
	'_wppa_email_token',
	'_wppa_email_token_expires',
);

foreach ( $wppa_meta_keys as $wppa_meta_key ) {
	delete_metadata( 'user', 0, $wppa_meta_key, '', true );
}
