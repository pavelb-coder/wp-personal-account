<?php
/**
 * Мини-заглушки WordPress для локальной проверки шаблонов без установки CMS.
 * Используется файлами tools/smoke-test.php и tools/build-preview.php.
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'AUTH_SALT', 'salt' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ENT_QUOTES_WP', ENT_QUOTES );

$GLOBALS['wppa_test'] = array(
	'options'  => array(
		'date_format'  => 'd.m.Y',
		'users_can_register' => 1,
		'wppa_settings' => array(
			'account_page_id'      => 42,
			'enabled_tabs'         => array( 'dashboard', 'profile', 'team', 'security' ),
			'registration_enabled' => 1,
			'accent_color'         => '#2563eb',
		),
	),
	'logged_in' => true,
	'meta'      => array(),
	'posts'     => array(),
	'postmeta'  => array(),
	'cpt'       => array(),
	'next_id'   => 100,
);

class WP_Widget {
	public $id_base;
	public $name;
	public $widget_options;
	public $number = 2;

	public function __construct( $id_base = '', $name = '', $options = array() ) {
		$this->id_base        = $id_base;
		$this->name           = $name;
		$this->widget_options = $options;
	}

	public function get_field_id( $field ) { return $this->id_base . '-' . $field; }
	public function get_field_name( $field ) { return 'widget_' . $this->id_base . '[' . $field . ']'; }
	public function widget( $args, $instance ) {}
	public function form( $instance ) {}
	public function update( $new_instance, $old_instance ) { return $new_instance; }
}

function register_widget( $class ) {
	$GLOBALS['wppa_test']['widgets'][] = $class;
	return true;
}

function is_active_widget( $callback = false, $widget_id = false, $id_base = false, $skip_inactive = true ) {
	return ! empty( $GLOBALS['wppa_test']['widget_active'] );
}

class WP_Query {
	public $found_posts = 2;

	public function __construct( $args = array() ) {}
}

class WP_Post {
	public $ID = 42;
	public $post_content = '[wppa_account]';
	public $post_title = 'Личный кабинет';
	public $post_type = 'page';
	public $post_author = 7;
	public $post_status = 'publish';

	public function __construct( $args = array() ) {
		foreach ( (array) $args as $key => $value ) {
			$this->$key = $value;
		}
	}
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

	/**
	 * Конструктор: подставляет данные из демо-реестра, если он задан.
	 *
	 * @param int $id ID пользователя.
	 */
	public function __construct( $id = 0 ) {
		if ( $id ) {
			$this->ID = (int) $id;
		}

		$registry = isset( $GLOBALS['wppa_test']['users'] ) ? (array) $GLOBALS['wppa_test']['users'] : array();

		if ( isset( $registry[ $this->ID ] ) ) {
			$this->display_name = $registry[ $this->ID ][0];
			$this->user_email   = $registry[ $this->ID ]['email'] ?? $registry[ $this->ID ][1];
			$this->first_name   = $registry[ $this->ID ]['first_name'] ?? $this->first_name;
			$this->last_name    = $registry[ $this->ID ]['last_name'] ?? $this->last_name;

			if ( isset( $registry[ $this->ID ]['login'] ) ) {
				$this->user_login = (string) $registry[ $this->ID ]['login'];
			}

			if ( isset( $registry[ $this->ID ]['registered'] ) ) {
				$this->user_registered = (string) $registry[ $this->ID ]['registered'];
			}
		}
	}
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
function wp_kses_post( $t ) {
	$t = preg_replace( '#<script\b.*?</script>#is', '', (string) $t );
	return preg_replace( '#\son\w+\s*=\s*("[^"]*"|\'[^\']*\')#i', '', $t );
}
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_textarea_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function sanitize_hex_color( $c ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $c ) ? $c : null; }
function sanitize_file_name( $n ) {
	// Ядро сохраняет юникод в именах файлов: вырезаем только служебные символы и слэши.
	$n = (string) $n;
	$n = str_replace( array( '/', chr( 92 ), '<', '>', ':', '"', '|', '?', '*' ), '', $n );
	$n = preg_replace( '/[[:cntrl:]]/', '', $n );

	return trim( (string) $n );
}
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
function get_current_user_id() { return isset( $GLOBALS['wppa_test']['current_user'] ) ? (int) $GLOBALS['wppa_test']['current_user'] : 7; }
function wp_set_current_user( $id ) { return new WP_User(); }
function wp_set_auth_cookie( $id, $r = false, $s = false ) {}
function wp_set_password( $p, $id ) {}
function wp_check_password( $p, $h, $id = '' ) { return true; }
function wp_hash_password( $p ) { return 'hash'; }
function wp_generate_password( $l = 12, $s = true, $e = true ) {
	// Стаб отдаёт разные значения: от этого зависит уникальность ключей файлов.
	$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
	$out   = '';
	$len   = strlen( $chars ) - 1;

	for ( $i = 0; $i < (int) $l; $i++ ) {
		$out .= $chars[ mt_rand( 0, $len ) ];
	}

	return $out;
}
function wp_update_user( $a ) { return (int) $a['ID']; }
function wp_insert_user( $a ) { return 99; }
function wp_mail( $to, $s, $m ) { return true; }
function email_exists( $e ) { return false; }
function username_exists( $u ) { return false; }
function wp_create_nonce( $action = -1 ) { return 'nonce-' . substr( md5( (string) $action ), 0, 10 ); }
function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $name . '=' . wp_create_nonce( $action ); }
function auth_redirect() { throw new RuntimeException( 'auth_redirect' ); }
function check_admin_referer( $a, $n = '' ) { return true; }

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Проверка nonce: в заглушках пустой nonce считается просроченным.
	 *
	 * @param string $nonce  Значение.
	 * @param string $action Действие.
	 * @return int|false
	 */
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return '' === (string) $nonce ? false : 1;
	}
}
function wp_safe_redirect( $u ) {
	// В тестах обработчики форм завершаются exit-ом: перехватываем адрес исключением.
	if ( ! empty( $GLOBALS['wppa_test']['catch_redirect'] ) ) {
		throw new RuntimeException( 'redirect:' . $u );
	}

	return true;
}
function get_bloginfo( $k ) { return 'Демо-сайт'; }
function wp_specialchars_decode( $t, $q = null ) { return $t; }
function is_ssl() { return true; }
function wp_validate_redirect( $u, $d = '' ) { return $u ? $u : $d; }
function get_transient( $k ) { return 0; }
function set_transient( $k, $v, $e = 0 ) { return true; }
function delete_transient( $k ) { return true; }
function wppa_stub_post( $id ) {
	return isset( $GLOBALS['wppa_test']['posts'][ (int) $id ] ) ? $GLOBALS['wppa_test']['posts'][ (int) $id ] : null;
}

function get_post( $id = null ) {
	if ( null === $id ) {
		return new WP_Post();
	}
	return wppa_stub_post( $id );
}

function get_post_type( $id = null ) {
	$post = get_post( $id );
	return $post ? $post->post_type : false;
}

function get_post_status( $id = null ) {
	$post = get_post( $id );
	return $post ? $post->post_status : false;
}

function get_the_title( $id = 0 ) {
	$post = get_post( $id );
	return $post ? $post->post_title : '';
}

function register_post_type( $type, $args = array() ) {
	$GLOBALS['wppa_test']['cpt'][ $type ] = $args;
	return (object) $args;
}

function wp_insert_post( $args, $wp_error = false ) {
	$id   = ++$GLOBALS['wppa_test']['next_id'];
	$post = new WP_Post(
		array(
			'ID'          => $id,
			'post_type'   => isset( $args['post_type'] ) ? $args['post_type'] : 'post',
			'post_title'  => isset( $args['post_title'] ) ? $args['post_title'] : '',
			'post_author' => isset( $args['post_author'] ) ? (int) $args['post_author'] : 0,
			'post_status' => isset( $args['post_status'] ) ? $args['post_status'] : 'publish',
		)
	);
	$GLOBALS['wppa_test']['posts'][ $id ] = $post;

	if ( ! empty( $args['meta_input'] ) ) {
		foreach ( (array) $args['meta_input'] as $key => $value ) {
			$GLOBALS['wppa_test']['postmeta'][ $id ][ $key ] = $value;
		}
	}

	return $wp_error ? $id : $id;
}

function wp_update_post( $args, $wp_error = false ) {
	$id = isset( $args['ID'] ) ? (int) $args['ID'] : 0;

	if ( ! wppa_stub_post( $id ) ) {
		return $wp_error ? new WP_Error( 'not_found' ) : 0;
	}

	if ( isset( $args['post_title'] ) ) {
		$GLOBALS['wppa_test']['posts'][ $id ]->post_title = $args['post_title'];
	}

	return $id;
}

function wp_delete_post( $id, $force = false ) {
	unset( $GLOBALS['wppa_test']['posts'][ (int) $id ], $GLOBALS['wppa_test']['postmeta'][ (int) $id ] );
	return true;
}

function get_post_meta( $id, $key = '', $single = false ) {
	$meta = isset( $GLOBALS['wppa_test']['postmeta'][ (int) $id ] ) ? $GLOBALS['wppa_test']['postmeta'][ (int) $id ] : array();
	return isset( $meta[ $key ] ) ? $meta[ $key ] : '';
}

function update_post_meta( $id, $key, $value ) {
	$GLOBALS['wppa_test']['postmeta'][ (int) $id ][ $key ] = $value;
	return true;
}

function delete_post_meta( $id, $key ) {
	unset( $GLOBALS['wppa_test']['postmeta'][ (int) $id ][ $key ] );
	return true;
}

function get_posts( $args = array() ) {
	$out = array();

	foreach ( (array) $GLOBALS['wppa_test']['posts'] as $id => $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}

		if ( ! empty( $args['meta_key'] ) && ( ! isset( $GLOBALS['wppa_test']['postmeta'][ $id ][ $args['meta_key'] ] )
			|| (string) $GLOBALS['wppa_test']['postmeta'][ $id ][ $args['meta_key'] ] !== (string) $args['meta_value'] ) ) {
			continue;
		}

		$out[] = ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) ? $id : $post;
	}

	return $out;
}

function get_users( $args = array() ) {
	if ( ! empty( $args['meta_key'] ) ) {
		$out = array();

		foreach ( (array) $GLOBALS['wppa_test']['meta'] as $user_id => $meta ) {
			if ( ! isset( $meta[ $args['meta_key'] ] ) ) {
				continue;
			}
			if ( isset( $args['meta_value'] ) && (string) $meta[ $args['meta_key'] ] !== (string) $args['meta_value'] ) {
				continue;
			}
			$out[]      = new WP_User( (int) $user_id );
		}

		return $out;
	}

	return array( new WP_User(), new WP_User() );
}

function get_userdata( $id ) {
	$user             = new WP_User();
	$user->ID         = (int) $id;
	$user->user_login = 'user' . $id;

	// Имя берём из реестра демо-пользователей, если он задан.
	$registry = isset( $GLOBALS['wppa_test']['users'] ) ? (array) $GLOBALS['wppa_test']['users'] : array();

	if ( isset( $registry[ (int) $id ][0] ) ) {
		$user->display_name = $registry[ (int) $id ][0];
	}

	return $user;
}

function get_the_permalink( $id = 0 ) { return 'https://example.com/?p=' . (int) $id; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function parse_str_stub() {}
function is_page( $id = null ) { return true; }
function has_shortcode( $content, $tag ) { return false !== strpos( (string) $content, '[' . $tag ); }
function get_permalink( $id = null ) { return 'https://example.com/personal-account/'; }
function home_url( $path = '/' ) { return 'https://example.com' . $path; }
function admin_url( $p = '' ) { return 'https://example.com/wp-admin/' . $p; }
function add_query_arg( ...$args ) {
	// Две формы, как в ядре: add_query_arg( массив, $url ) и add_query_arg( ключ, значение, $url ).
	if ( is_array( $args[0] ) ) {
		$query = $args[0];
		$url   = isset( $args[1] ) ? (string) $args[1] : '';
	} else {
		$query = array( $args[0] => isset( $args[1] ) ? $args[1] : '' );
		$url   = isset( $args[2] ) ? (string) $args[2] : '';
	}

	if ( '' === $url ) {
		return '?' . http_build_query( $query );
	}

	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $query );
}
function wp_nonce_field( $action = -1, $name = '_wpnonce' ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce" />'; }
function wp_logout_url( $redirect = '' ) { return 'https://example.com/logout'; }
function wp_lostpassword_url( $redirect = '' ) { return 'https://example.com/lost'; }
function get_avatar( $id, $size = 56, $d = '', $a = '', $args = array() ) {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="56" height="56"><rect width="56" height="56" rx="28" fill="#dbeafe"/><circle cx="28" cy="22" r="9" fill="#2563eb"/><path d="M8 52c4-11 13-16 20-16s16 5 20 16z" fill="#2563eb"/></svg>';
	return '<img class="avatar" width="' . $size . '" height="' . $size . '" src="data:image/svg+xml;base64,' . base64_encode( $svg ) . '" alt="" />';
}
function date_i18n( $format, $timestamp = null ) { return date( $format, $timestamp ? $timestamp : time() ); }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals, '.', ' ' ); }
function mysql2date( $format, $date ) { return date( $format, strtotime( $date ) ); }
function locate_template( $files ) { return ''; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function shortcode_atts( $defaults, $atts, $shortcode = '' ) {
	$atts = (array) $atts;
	$out  = array();
	foreach ( $defaults as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}
function add_shortcode( $tag, $callback ) { $GLOBALS['wppa_test']['shortcodes'][ $tag ] = $callback; }

function sanitize_html_class( $class, $fallback = '' ) {
	$class = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
	return '' === $class ? (string) $fallback : $class;
}

function wp_unique_id( $prefix = '' ) {
	static $id = 0;
	++$id;
	return $prefix . $id;
}

function wp_json_encode( $data, $options = 0, $depth = 512 ) { return json_encode( $data, $options, $depth ); }

function get_block_wrapper_attributes( $extra = array() ) {
	$classes = isset( $extra['class'] ) ? $extra['class'] : '';
	$styles  = isset( $extra['style'] ) ? $extra['style'] : '';
	$out     = 'class="' . htmlspecialchars( $classes ) . '"';

	if ( '' !== $styles ) {
		$out .= ' style="' . htmlspecialchars( $styles ) . '"';
	}

	return $out;
}

function register_block_type( $name, $args = array() ) {
	$key = is_string( $name ) ? $name : ( isset( $args['render_callback'] ) ? $name : $name );
	$GLOBALS['wppa_test']['blocks'][] = is_string( $name ) ? $name : $name;
	return (object) array( 'name' => $key, 'args' => $args );
}

function has_block( $name, $post = null ) { return ! empty( $GLOBALS['wppa_test']['has_block'][ $name ] ); }
function wp_style_is( $handle, $status = 'enqueued' ) { return ! empty( $GLOBALS['wppa_test']['styles'][ $handle ] ); }
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) { $GLOBALS['wppa_test']['styles'][ $handle ] = true; }
function wp_add_inline_style( $handle, $css ) { $GLOBALS['wppa_test']['inline_css'][ $handle ] = $css; }
function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) {
	$GLOBALS['wppa_test']['scripts'][ $handle ] = array( 'src' => $src, 'deps' => $deps );
	return true;
}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) { return wp_register_script( $handle, $src, $deps, $ver, $footer ); }
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
function add_filter( $h, $cb, $p = 10, $a = 1 ) {
	$GLOBALS['wppa_test']['filters'][ $h ][ (int) $p ][] = array( $cb, (int) $a );
	return true;
}

function remove_filter( $h, $cb, $p = 10 ) {
	if ( empty( $GLOBALS['wppa_test']['filters'][ $h ][ (int) $p ] ) ) {
		return false;
	}

	foreach ( $GLOBALS['wppa_test']['filters'][ $h ][ (int) $p ] as $index => $entry ) {
		if ( $entry[0] === $cb ) {
			unset( $GLOBALS['wppa_test']['filters'][ $h ][ (int) $p ][ $index ] );
			return true;
		}
	}

	return false;
}

function apply_filters( $h, $v ) {
	if ( empty( $GLOBALS['wppa_test']['filters'][ $h ] ) ) {
		return $v;
	}

	$hooks = $GLOBALS['wppa_test']['filters'][ $h ];
	ksort( $hooks );

	$extra = array_slice( func_get_args(), 2 );

	foreach ( $hooks as $entries ) {
		foreach ( (array) $entries as $entry ) {
			$v = call_user_func_array( $entry[0], array_merge( array( $v ), $extra ) );
		}
	}

	return $v;
}

function do_action( $h ) {}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap = '' ) {
		if ( isset( $GLOBALS['wppa_test']['can_manage'] ) ) {
			return (bool) $GLOBALS['wppa_test']['can_manage'];
		}
		return true;
	}
}

function wp_timezone() { return new DateTimeZone( 'UTC' ); }

function wp_timezone_string() { return 'UTC'; }

function wp_date( $format, $timestamp = null ) { return gmdate( $format, $timestamp ? $timestamp : time() ); }

function show_admin_bar( $show = true ) {
	$GLOBALS['wppa_test']['admin_bar'] = (bool) $show;
	return true;
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() { return ! empty( $GLOBALS['wppa_test']['is_admin'] ); }
}

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $post_types = '' ) { return ! isset( $GLOBALS['wppa_test']['is_singular'] ) || (bool) $GLOBALS['wppa_test']['is_singular']; }
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() { return ! empty( $GLOBALS['wppa_test']['doing_ajax'] ); }
}

function get_user_by( $field, $value ) {
	$users = isset( $GLOBALS['wppa_test']['users_by'] ) ? (array) $GLOBALS['wppa_test']['users_by'] : array();

	if ( isset( $users[ $field ][ $value ] ) ) {
		return new WP_User( (int) $users[ $field ][ $value ] );
	}

	return false;
}

function get_password_reset_key( $user ) {
	$GLOBALS['wppa_test']['reset_key_user'] = $user->ID;
	return 'test-reset-key';
}

function check_password_reset_key( $key, $login ) {
	$GLOBALS['wppa_test']['checked_key'] = array( $key, $login );

	if ( 'test-reset-key' !== $key ) {
		return new WP_Error();
	}

	return new WP_User( 7 );
}

function reset_password( $user, $new_pass ) {
	$GLOBALS['wppa_test']['reset_done'] = array( $user->ID, $new_pass );
	return true;
}

function wp_next_scheduled( $hook, $args = array() ) {
	$events = isset( $GLOBALS['wppa_test']['cron'] ) ? (array) $GLOBALS['wppa_test']['cron'] : array();
	return isset( $events[ $hook ] ) ? (int) $events[ $hook ] : false;
}

function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
	$GLOBALS['wppa_test']['cron'][ $hook ]        = (int) $timestamp;
	$GLOBALS['wppa_test']['cron_recurrence'][ $hook ] = $recurrence;
	return true;
}

function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
	$GLOBALS['wppa_test']['cron'][ $hook ] = (int) $timestamp;
	return true;
}

function wp_get_schedule( $hook, $args = array() ) {
	return isset( $GLOBALS['wppa_test']['cron_recurrence'][ $hook ] ) ? $GLOBALS['wppa_test']['cron_recurrence'][ $hook ] : false;
}

function wp_clear_scheduled_hook( $hook, $args = array() ) {
	unset( $GLOBALS['wppa_test']['cron'][ $hook ], $GLOBALS['wppa_test']['cron_recurrence'][ $hook ] );
	return true;
}

function sanitize_title( $title, $fallback = '', $context = 'save' ) {
	$title = strtolower( preg_replace( '/[^A-Za-z0-9]+/', '-', (string) $title ) );
	$title = trim( $title, '-' );
	return '' === $title ? (string) $fallback : $title;
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' checked="checked"' : ''; if ( $echo ) { echo $r; } return $r; }
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' selected="selected"' : ''; if ( $echo ) { echo $r; } return $r; }
}

function wp_mkdir_p( $target ) {
	return is_dir( $target ) || mkdir( $target, 0777, true );
}

function wp_get_upload_dir() {
	return array(
		'basedir' => rtrim( sys_get_temp_dir(), '/' ) . '/wppa-test-uploads',
		'baseurl' => 'https://example.com/wp-content/uploads',
	);
}

function content_url( $path = '' ) { return 'https://example.com/wp-content' . $path; }

function wp_tempnam( $filename = '', $dir = '' ) {
	$dir = '' !== $dir ? $dir : sys_get_temp_dir();
	$tmp = rtrim( $dir, '/\\' ) . DIRECTORY_SEPARATOR . uniqid( 'wppa-', true ) . '-' . preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $filename );
	return touch( $tmp ) ? $tmp : false;
}

/**
 * Минимальная замена $wpdb для тестов: возвращает подготовленные строки.
 */
class WPPA_Test_WPDB {

	public $prefix = 'wp_';

	public $data = array();

	public $queries = array();

	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		foreach ( $args as $arg ) {
			$replacement = is_int( $arg ) || is_float( $arg ) ? (string) $arg : "'" . $arg . "'";
			$query       = preg_replace( '/%[sdf]/', str_replace( '$', '\$', $replacement ), $query, 1 );
		}

		return $query;
	}

	public function get_results( $query, $output = null ) {
		$this->queries[] = $query;

		if ( false !== strpos( $query, 'olympiad_attempts' ) ) {
			return isset( $this->data['attempts'] ) ? $this->data['attempts'] : array();
		}

		return array();
	}

	public function get_var( $query = null ) {
		$this->queries[] = $query;

		return isset( $this->data['var'] ) ? $this->data['var'] : null;
	}

	public function get_row( $query = null ) {
		$this->queries[] = $query;

		return isset( $this->data['row'] ) ? $this->data['row'] : null;
	}

	public function esc_like( $text ) {
		return $text;
	}
}

$GLOBALS['wpdb'] = new WPPA_Test_WPDB();

function esc_sql( $data ) {
	return str_replace( array( "'", '"' ), array( "\'", '\"' ), (string) $data );
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = null ) {
		return 1 === (int) $number ? $single : $plural;
	}
}

function size_format( $bytes, $decimals = 0 ) {
	$bytes = (int) $bytes;

	if ( $bytes >= 1048576 ) {
		return number_format( $bytes / 1048576, $decimals ) . ' MB';
	}

	if ( $bytes >= 1024 ) {
		return number_format( $bytes / 1024, $decimals ) . ' KB';
	}

	return $bytes . ' B';
}

function get_locale() {
	return isset( $GLOBALS['wppa_test']['locale'] ) ? $GLOBALS['wppa_test']['locale'] : 'ru_RU';
}
