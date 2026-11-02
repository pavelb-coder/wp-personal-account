<?php
/**
 * Заказы пользователя (интеграция с WooCommerce).
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Orders
 */
class WPPA_Orders {

	/**
	 * Доступен ли раздел заказов.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' );
	}

	/**
	 * Список заказов текущего пользователя.
	 *
	 * @param int $paged Страница.
	 * @return object|null Объект с orders/total/max_num_pages.
	 */
	public static function get_orders( $paged = 1 ) {
		if ( ! self::is_available() || ! is_user_logged_in() ) {
			return null;
		}

		$per_page = (int) WPPA_Plugin::setting( 'orders_per_page', 10 );
		$per_page = min( 50, max( 1, $per_page ) );

		/**
		 * Фильтр аргументов запроса заказов.
		 *
		 * @param array $args Аргументы wc_get_orders().
		 */
		$args = apply_filters(
			'wppa_orders_query_args',
			array(
				'customer_id' => get_current_user_id(),
				'limit'       => $per_page,
				'paged'       => max( 1, (int) $paged ),
				'orderby'     => 'date',
				'order'       => 'DESC',
				'paginate'    => true,
			)
		);

		return wc_get_orders( $args );
	}

	/**
	 * Заказ по ID с проверкой принадлежности пользователю.
	 *
	 * @param int $order_id ID заказа.
	 * @return WC_Order|null
	 */
	public static function get_order( $order_id ) {
		if ( ! self::is_available() || ! is_user_logged_in() ) {
			return null;
		}

		$order = wc_get_order( absint( $order_id ) );

		if ( ! $order instanceof WC_Order ) {
			return null;
		}

		if ( (int) $order->get_customer_id() !== (int) get_current_user_id() ) {
			return null;
		}

		return $order;
	}

	/**
	 * Название статуса заказа.
	 *
	 * @param WC_Order $order Заказ.
	 * @return string
	 */
	public static function status_label( $order ) {
		$status = $order->get_status();
		$label  = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $status ) : $status;

		return $label ? $label : __( 'Неизвестно', 'wp-personal-account' );
	}

	/**
	 * Форматированная дата заказа.
	 *
	 * @param WC_Order $order Заказ.
	 * @return string
	 */
	public static function date( $order ) {
		$created = $order->get_date_created();

		if ( ! $created ) {
			return '—';
		}

		return function_exists( 'wc_format_datetime' ) ? wc_format_datetime( $created ) : $created->date_i18n( get_option( 'date_format' ) );
	}
}
