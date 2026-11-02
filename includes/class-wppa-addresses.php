<?php
/**
 * Адреса плательщика и доставки (интеграция с WooCommerce).
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Addresses
 */
class WPPA_Addresses {

	/**
	 * Доступен ли раздел адресов.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Описание полей адреса.
	 *
	 * @return array
	 */
	public static function fields() {
		return array(
			'first_name' => array( 'label' => __( 'Имя', 'wp-personal-account' ), 'type' => 'text' ),
			'last_name'  => array( 'label' => __( 'Фамилия', 'wp-personal-account' ), 'type' => 'text' ),
			'company'    => array( 'label' => __( 'Компания', 'wp-personal-account' ), 'type' => 'text' ),
			'country'    => array( 'label' => __( 'Страна', 'wp-personal-account' ), 'type' => 'country' ),
			'state'      => array( 'label' => __( 'Регион / область', 'wp-personal-account' ), 'type' => 'text' ),
			'city'       => array( 'label' => __( 'Город', 'wp-personal-account' ), 'type' => 'text' ),
			'postcode'   => array( 'label' => __( 'Индекс', 'wp-personal-account' ), 'type' => 'text' ),
			'address_1'  => array( 'label' => __( 'Адрес', 'wp-personal-account' ), 'type' => 'text' ),
			'address_2'  => array( 'label' => __( 'Адрес, строка 2', 'wp-personal-account' ), 'type' => 'text' ),
			'phone'      => array( 'label' => __( 'Телефон', 'wp-personal-account' ), 'type' => 'tel' ),
		);
	}

	/**
	 * Список стран WooCommerce.
	 *
	 * @return array
	 */
	public static function countries() {
		if ( function_exists( 'WC' ) && WC()->countries ) {
			return WC()->countries->get_countries();
		}
		return array();
	}

	/**
	 * Текущие значения адреса.
	 *
	 * @param string $type billing|shipping.
	 * @return array
	 */
	public static function get_values( $type ) {
		$values = array();
		$user   = wp_get_current_user();

		foreach ( self::fields() as $key => $field ) {
			$values[ $key ] = (string) get_user_meta( $user->ID, $type . '_' . $key, true );
		}

		if ( 'billing' === $type ) {
			$values['email'] = $user->user_email;
		}

		return $values;
	}

	/**
	 * Сохранение адресов.
	 *
	 * @return void
	 */
	public static function handle_save() {
		check_admin_referer( 'wppa_addresses', 'wppa_nonce' );

		if ( ! is_user_logged_in() || ! self::is_available() ) {
			WPPA_Notices::redirect( 'woocommerce_required' );
		}

		$user_id = get_current_user_id();
		$tab_url = WPPA_Plugin::tab_url( 'addresses' );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- каждое поле санитизируется ниже.
		$input = isset( $_POST['wppa_address'] ) && is_array( $_POST['wppa_address'] ) ? wp_unslash( $_POST['wppa_address'] ) : array();
		// phpcs:enable

		foreach ( array( 'billing', 'shipping' ) as $type ) {
			if ( empty( $input[ $type ] ) || ! is_array( $input[ $type ] ) ) {
				continue;
			}

			foreach ( self::fields() as $key => $field ) {
				$raw = isset( $input[ $type ][ $key ] ) ? $input[ $type ][ $key ] : '';

				if ( 'country' === $field['type'] ) {
					$value = strtoupper( substr( sanitize_text_field( $raw ), 0, 2 ) );
				} elseif ( 'tel' === $field['type'] ) {
					$value = preg_replace( '/[^0-9+\-()\s]/', '', sanitize_text_field( $raw ) );
				} else {
					$value = sanitize_text_field( $raw );
				}

				update_user_meta( $user_id, $type . '_' . $key, $value );
			}
		}

		if ( function_exists( 'WC' ) && WC()->customer ) {
			$customer = new WC_Customer( $user_id );
			foreach ( array( 'billing', 'shipping' ) as $type ) {
				if ( empty( $input[ $type ] ) || ! is_array( $input[ $type ] ) ) {
					continue;
				}
				foreach ( array_keys( self::fields() ) as $key ) {
					$value = get_user_meta( $user_id, $type . '_' . $key, true );
					$method = 'set_' . $type . '_' . $key;
					if ( is_callable( array( $customer, $method ) ) ) {
						$customer->$method( $value );
					}
				}
			}
			$customer->save();
		}

		WPPA_Notices::redirect( 'addresses_saved', $tab_url );
	}
}
