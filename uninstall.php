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
	'_wppa_consent_time',
	'_wppa_patronymic',
	'_wppa_phone',
	'_wppa_birthdate',
	'_wppa_city',
	'_wppa_company',
	'_wppa_position',
	'_wppa_website',
	'_wppa_telegram',
);

foreach ( $wppa_meta_keys as $wppa_meta_key ) {
	delete_metadata( 'user', 0, $wppa_meta_key, '', true );
}

// Содержимое чата удаляется только при явно включённой настройке полной очистки.
register_post_type( 'wppa_chat_message', array( 'public' => false, 'show_ui' => false ) );
do {
	$wppa_chat_ids = get_posts( array(
		'post_type' => 'wppa_chat_message',
		'post_status' => array( 'private', 'trash', 'draft', 'pending', 'publish', 'future' ),
		'numberposts' => 500, 'fields' => 'ids', 'suppress_filters' => true,
	) );
	$wppa_deleted = 0;
	foreach ( $wppa_chat_ids as $wppa_chat_id ) {
		if ( wp_delete_post( $wppa_chat_id, true ) ) { ++$wppa_deleted; }
	}
} while ( count( $wppa_chat_ids ) === 500 && $wppa_deleted === 500 );

// Удаляем персональные отметки прочтения только при включённой полной очистке.
global $wpdb;
$wppa_chat_meta_pattern = $wpdb->esc_like( '_wppa_chat_read_' . get_current_blog_id() . '_' ) . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- деинсталляция, метаключи содержат ID наставника.
$wppa_chat_curators = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wppa_chat_meta_pattern ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- пакетная очистка.
$wpdb->query( $wpdb->prepare( "DELETE /* WPPA_CHAT_CLEANUP */ FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wppa_chat_meta_pattern ) );
foreach ( (array) $wppa_chat_curators as $wppa_chat_curator ) { wp_cache_delete( (int) $wppa_chat_curator, 'user_meta' ); }
