<?php
/**
 * Заглушки WP API для админ-панели (поверх tools/wp-stubs.php).
 *
 * @package WP_Personal_Account
 */

require __DIR__ . '/wp-stubs.php';

/* --- дополнительные заглушки для админки --- */

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


function count_users() { return array( 'total_users' => 128, 'avail_roles' => array( 'subscriber' => 110, 'administrator' => 1 ) ); }
function wp_list_pluck( $list, $field ) { return array_map( static function ( $item ) use ( $field ) { return is_array( $item ) ? ( $item[ $field ] ?? null ) : ( $item->$field ?? null ); }, (array) $list ); }
function current_user_can( $cap ) { return true; }
function get_current_screen() { return (object) array( 'id' => 'toplevel_page_wppa-dashboard' ); }
function get_edit_post_link( $id = 0, $context = 'display' ) { return 'https://example.com/wp-admin/post.php?post=' . $id . '&action=edit'; }
function get_edit_user_link( $id = 0 ) { return 'https://example.com/wp-admin/user-edit.php?user_id=' . $id; }
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
function submit_button( $text = '', $type = 'primary', $name = 'submit', $wrap = true, $other = null ) { echo '<button class="button">' . htmlspecialchars( $text ) . '</button>'; }
function checked( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' checked="checked"' : ''; if ( $echo ) { echo $r; } return $r; }
function selected( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' selected="selected"' : ''; if ( $echo ) { echo $r; } return $r; }
function add_menu_page() {}
function add_submenu_page() {}
function register_setting() {}
function settings_fields( $group ) { echo '<input type="hidden" name="option_page" value="' . htmlspecialchars( $group ) . '" />'; }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_register_style() {}
function wp_register_script() {}
function wp_localize_script() {}
function wp_add_inline_style() {}
function wp_die( $message = '' ) { throw new RuntimeException( 'wp_die: ' . $message ); }
function wp_get_referer() { return ''; }
function delete_metadata() { return true; }
function nocache_headers() {}

