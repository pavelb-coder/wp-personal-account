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
	'_wppa_participant_files',
	'_wppa_curator_files',
);

foreach ( $wppa_meta_keys as $wppa_meta_key ) {
	delete_metadata( 'user', 0, $wppa_meta_key, '', true );
}

// Файлы команд и адресные документы участников лежат в uploads — убираем папки.
$wppa_uploads = wp_upload_dir();
$wppa_dirs    = array( 'wppa-curator', 'wppa-participants' );

foreach ( $wppa_dirs as $wppa_dir ) {
	$wppa_path = rtrim( (string) $wppa_uploads['basedir'], '/\\' ) . '/' . $wppa_dir;

	if ( ! is_dir( $wppa_path ) ) {
		continue;
	}

	foreach ( (array) glob( $wppa_path . '/*' ) as $wppa_file ) {
		if ( is_file( $wppa_file ) ) {
			@unlink( $wppa_file );
		}
	}

	@rmdir( $wppa_path );
}
