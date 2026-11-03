<?php
/**
 * Заглушки WP API для админ-панели (поверх tools/wp-stubs.php).
 *
 * @package WP_Personal_Account
 */

require __DIR__ . '/wp-stubs.php';

/* --- дополнительные заглушки для админки --- */

class Demo_Roles {
	public $roles = array(
		'subscriber'    => array( 'name' => 'Подписчик' ),
		'administrator' => array( 'name' => 'Администратор' ),
	);

	public function get_names() {
		return array_map(
			static function ( $role ) {
				return $role['name'];
			},
			$this->roles
		);
	}
}

class WP_User_Query {
	private $total;
	private $results;

	/**
	 * Детерминированная последовательность «найденных» пользователей,
	 * чтобы графики и счётчики в превью выглядели реалистично.
	 *
	 * @var int[]
	 */
	private static $totals = array( 4, 2, 6, 3, 0, 5, 7, 1, 3, 4, 2, 5, 6, 3, 2, 1 );

	private static $calls = 0;

	public function __construct( $args = array() ) {
		$this->total   = self::$totals[ self::$calls % count( self::$totals ) ];
		self::$calls++;
		$this->results = array( new WP_User(), new WP_User() );
	}

	public function get_total() {
		return $this->total;
	}

	public function get_results() {
		return $this->results;
	}
}

if ( ! defined( 'WC_VERSION' ) ) {
	define( 'WC_VERSION', '9.4.0' );
}

class WooCommerce {}


function wp_roles() { return new Demo_Roles(); }
function translate_user_role( $name ) { return $name; }
function count_users() { return array( 'total_users' => 128, 'avail_roles' => array( 'subscriber' => 110, 'administrator' => 1 ) ); }
function wp_count_posts( $type = '' ) { return array( 'pending' => 4, 'processing' => 7, 'completed' => 21, 'cancelled' => 1, 'auto-draft' => 0, 'trash' => 0 ); }
function wp_list_pluck( $list, $field ) { return array_map( static function ( $item ) use ( $field ) { return is_array( $item ) ? ( $item[ $field ] ?? null ) : ( $item->$field ?? null ); }, (array) $list ); }
function wc_get_is_paid_statuses() { return array( 'completed', 'processing' ); }
function wc_get_orders( $args = array() ) {
	if ( ! empty( $args['paginate'] ) ) {
		return (object) array( 'orders' => array(), 'total' => 2, 'max_num_pages' => 1 );
	}
	return array();
}
function wc_get_order_status_name( $status ) { return ucfirst( $status ); }
function wc_price( $amount ) { return number_format( (float) $amount, 0, ',', ' ' ) . ' ₽'; }
function get_users( $args = array() ) { return array( new WP_User(), new WP_User() ); }
function current_user_can( $cap ) { return true; }
function get_current_screen() { return (object) array( 'id' => 'toplevel_page_wppa-dashboard' ); }
function get_the_title( $id = 0 ) { return 'Личный кабинет'; }
function get_edit_post_link( $id = 0, $context = 'display' ) { return 'https://example.com/wp-admin/post.php?post=' . $id . '&action=edit'; }
function get_edit_user_link( $id = 0 ) { return 'https://example.com/wp-admin/user-edit.php?user_id=' . $id; }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals, '.', ' ' ); }
function date_i18n( $format, $timestamp = null ) { return date( $format, $timestamp ? $timestamp : time() ); }
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
function submit_button( $text = '', $type = 'primary', $name = 'submit', $wrap = true, $other = null ) { echo '<button class="button">' . htmlspecialchars( $text ) . '</button>'; }
function checked( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' checked="checked"' : ''; if ( $echo ) { echo $r; } return $r; }
function selected( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' selected="selected"' : ''; if ( $echo ) { echo $r; } return $r; }
function add_menu_page() {}
function add_submenu_page() {}
function register_setting() {}
function settings_fields( $group ) { echo '<input type="hidden" name="option_page" value="' . htmlspecialchars( $group ) . '" />'; }
function wp_enqueue_style() {}
function wp_register_style() {}
function wp_register_script() {}
function wp_localize_script() {}
function wp_add_inline_style() {}
function wp_die( $message = '' ) { throw new RuntimeException( 'wp_die: ' . $message ); }
function wp_get_referer() { return ''; }
function delete_metadata() { return true; }
function nocache_headers() {}

