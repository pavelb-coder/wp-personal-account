<?php
/**
 * Мини-заглушки WordPress для локальной проверки шаблонов без установки CMS.
 * Используется файлами tools/smoke-test.php и tools/build-preview.php.
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'AUTH_SALT', 'salt' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ENT_QUOTES_WP', ENT_QUOTES );

$GLOBALS['wppa_test'] = array(
	'options'  => array(
		'date_format'  => 'd.m.Y',
		'users_can_register' => 1,
		'wppa_settings' => array(
			'account_page_id'      => 42,
			'enabled_tabs'         => array( 'dashboard', 'profile', 'orders', 'addresses', 'security' ),
			'registration_enabled' => 1,
			'accent_color'         => '#2563eb',
		),
	),
	'logged_in' => true,
	'meta'      => array(),
);

class WP_Post {
	public $ID = 42;
	public $post_content = '[wppa_account]';
}

class WP_User {
	public $ID = 7;
	public $display_name = 'Иван Петров';
	public $user_email = 'ivan@example.com';
	public $first_name = 'Иван';
	public $last_name = 'Петров';
	public $description = 'Люблю доставку.';
	public $user_login = 'ivan';
	public $user_pass = 'hash';
	public $user_registered = '2025-01-10 12:00:00';
	public $roles = array( 'subscriber' );
}

function __( $t, $d = null ) { return $t; }
function _e( $t, $d = null ) { echo $t; }
function esc_html__( $t, $d = null ) { return htmlspecialchars( $t, ENT_QUOTES ); }
function esc_attr__( $t, $d = null ) { return htmlspecialchars( $t, ENT_QUOTES ); }
function esc_html_e( $t, $d = null ) { echo htmlspecialchars( $t, ENT_QUOTES ); }
function esc_attr_e( $t, $d = null ) { echo htmlspecialchars( $t, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_textarea( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function esc_url_raw( $u ) { return (string) $u; }
function wp_kses_post( $t ) { return $t; }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_textarea_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function sanitize_hex_color( $c ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $c ) ? $c : null; }
function sanitize_file_name( $n ) { return preg_replace( '/[^A-Za-z0-9_\-.]/', '', (string) $n ); }
function sanitize_user( $u, $strict = false ) { return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $u ); }
function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }
function wp_unslash( $v ) { return $v; }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function absint( $v ) { return abs( (int) $v ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['wppa_test']['options'] ) ? $GLOBALS['wppa_test']['options'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['wppa_test']['options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['wppa_test']['options'][ $k ] ); return true; }
function get_user_meta( $uid, $key, $single = false ) { return isset( $GLOBALS['wppa_test']['meta'][ $uid ][ $key ] ) ? $GLOBALS['wppa_test']['meta'][ $uid ][ $key ] : ''; }
function update_user_meta( $uid, $key, $value ) { $GLOBALS['wppa_test']['meta'][ $uid ][ $key ] = $value; return true; }
function delete_user_meta( $uid, $key ) { unset( $GLOBALS['wppa_test']['meta'][ $uid ][ $key ] ); return true; }
function is_user_logged_in() { return (bool) $GLOBALS['wppa_test']['logged_in']; }
class WP_Roles_Stub {
	/**
	 * Роли демо-пользователя.
	 *
	 * @var array
	 */
	public $roles = array(
		'subscriber'    => array( 'name' => 'Подписчик' ),
		'administrator' => array( 'name' => 'Администратор' ),
	);

	/**
	 * Названия ролей.
	 *
	 * @return array
	 */
	public function get_names() {
		$names = array();
		foreach ( $this->roles as $slug => $role ) {
			$names[ $slug ] = $role['name'];
		}
		return $names;
	}
}

function wp_roles() {
	static $roles = null;
	if ( null === $roles ) {
		$roles = new WP_Roles_Stub();
	}
	return $roles;
}

function translate_user_role( $name ) { return $name; }
function wp_get_current_user() { return new WP_User(); }
function get_current_user_id() { return 7; }
function wp_set_current_user( $id ) { return new WP_User(); }
function wp_set_auth_cookie( $id, $r = false, $s = false ) {}
function wp_set_password( $p, $id ) {}
function wp_check_password( $p, $h, $id = '' ) { return true; }
function wp_hash_password( $p ) { return 'hash'; }
function wp_generate_password( $l = 12, $s = true, $e = true ) { return substr( str_repeat( 'abc123', 10 ), 0, $l ); }
function wp_update_user( $a ) { return (int) $a['ID']; }
function wp_insert_user( $a ) { return 99; }
function wp_mail( $to, $s, $m ) { return true; }
function email_exists( $e ) { return false; }
function username_exists( $u ) { return false; }
function check_admin_referer( $a, $n = '' ) { return true; }
function wp_safe_redirect( $u ) { return true; }
function get_bloginfo( $k ) { return 'Демо-сайт'; }
function wp_specialchars_decode( $t, $q = null ) { return $t; }
function is_ssl() { return true; }
function wp_validate_redirect( $u, $d = '' ) { return $u ? $u : $d; }
function get_transient( $k ) { return 0; }
function set_transient( $k, $v, $e = 0 ) { return true; }
function delete_transient( $k ) { return true; }
function get_post( $id = null ) { return new WP_Post(); }
function get_post_type( $id = null ) { return 'page'; }
function get_post_status( $id = null ) { return 'publish'; }
function is_page( $id = null ) { return true; }
function has_shortcode( $content, $tag ) { return false !== strpos( (string) $content, '[' . $tag ); }
function get_permalink( $id = null ) { return 'https://example.com/personal-account/'; }
function home_url( $path = '/' ) { return 'https://example.com' . $path; }
function admin_url( $p = '' ) { return 'https://example.com/wp-admin/' . $p; }
function add_query_arg( $args, $url = '' ) {
	if ( ! is_array( $args ) ) { return $url; }
	if ( func_num_args() === 1 ) { return '?' . http_build_query( $args ); }
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}
function wp_nonce_field( $action = -1, $name = '_wpnonce' ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce" />'; }
function wp_logout_url( $redirect = '' ) { return 'https://example.com/logout'; }
function wp_lostpassword_url( $redirect = '' ) { return 'https://example.com/lost'; }
function get_avatar( $id, $size = 56, $d = '', $a = '', $args = array() ) {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="56" height="56"><rect width="56" height="56" rx="28" fill="#dbeafe"/><circle cx="28" cy="22" r="9" fill="#2563eb"/><path d="M8 52c4-11 13-16 20-16s16 5 20 16z" fill="#2563eb"/></svg>';
	return '<img class="avatar" width="' . $size . '" height="' . $size . '" src="data:image/svg+xml;base64,' . base64_encode( $svg ) . '" alt="" />';
}
function mysql2date( $format, $date ) { return date( $format, strtotime( $date ) ); }
function locate_template( $files ) { return ''; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function get_page_by_path( $path ) { return null; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function paginate_links( $args = array() ) {
	$out = array();
	for ( $i = 1; $i <= $args['total']; $i++ ) {
		$out[] = '<span class="page-numbers current">' . $i . '</span>';
	}
	return $out;
}
class WP_Error {
	public $code;
	public function __construct( $code = '', $msg = '' ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function wp_styles() {}
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'https://example.com/wp-content/plugins/' . basename( dirname( $f ) ) . '/'; }
function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
function register_activation_hook( $f, $cb ) {}
function register_deactivation_hook( $f, $cb ) {}
function load_plugin_textdomain( $d, $dep = false, $path = '' ) {}
function add_action( $h, $cb, $p = 10, $a = 1 ) {}
function add_filter( $h, $cb, $p = 10, $a = 1 ) {}
function apply_filters( $h, $v ) { return $v; }
function do_action( $h ) {}
