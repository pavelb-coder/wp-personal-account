<?php
/**
 * Дополнительные персональные данные: поля регистрации и профиля.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Fields
 */
class WPPA_Fields {

	/**
	 * Ключ настройки со схемой полей.
	 */
	const SETTING = 'fields';

	/**
	 * Реестр доступных полей.
	 *
	 * Каждое поле: label, type, autocomplete, meta (или user), placeholder, hint,
	 * enabled_default, required_default, profile_native (поле уже есть в форме профиля).
	 *
	 * @return array
	 */
	public static function all() {
		$fields = array(
			'first_name' => array(
				'label'            => __( 'Имя', 'wp-personal-account' ),
				'type'             => 'text',
				'autocomplete'     => 'given-name',
				'user'             => 'first_name',
				'enabled_default'  => 1,
				'required_default' => 1,
				'profile_native'   => 1,
			),
			'last_name'  => array(
				'label'           => __( 'Фамилия', 'wp-personal-account' ),
				'type'            => 'text',
				'autocomplete'    => 'family-name',
				'user'            => 'last_name',
				'enabled_default' => 1,
				'profile_native'  => 1,
			),
			'patronymic' => array(
				'label'        => __( 'Отчество', 'wp-personal-account' ),
				'type'         => 'text',
				'autocomplete' => 'additional-name',
				'meta'         => '_wppa_patronymic',
			),
			'phone'      => array(
				'label'        => __( 'Телефон', 'wp-personal-account' ),
				'type'         => 'tel',
				'autocomplete' => 'tel',
				'meta'         => '_wppa_phone',
				'placeholder'  => '+7 900 000-00-00',
				'enabled_default' => 1,
				'hint'         => __( 'В формате +7 900 000-00-00.', 'wp-personal-account' ),
			),
			'birthdate'  => array(
				'label'        => __( 'Дата рождения', 'wp-personal-account' ),
				'type'         => 'date',
				'autocomplete' => 'bday',
				'meta'         => '_wppa_birthdate',
			),
			'city'       => array(
				'label'           => __( 'Город', 'wp-personal-account' ),
				'type'            => 'text',
				'autocomplete'    => 'address-level2',
				'meta'            => '_wppa_city',
				'enabled_default' => 1,
			),
			'company'    => array(
				'label'           => __( 'Организация', 'wp-personal-account' ),
				'type'            => 'text',
				'autocomplete'    => 'organization',
				'meta'            => '_wppa_company',
				'enabled_default' => 1,
			),
			'position'   => array(
				'label'           => __( 'Должность', 'wp-personal-account' ),
				'type'            => 'text',
				'autocomplete'    => 'organization-title',
				'meta'            => '_wppa_position',
				'enabled_default' => 1,
			),
			'website'    => array(
				'label'        => __( 'Сайт', 'wp-personal-account' ),
				'type'         => 'url',
				'autocomplete' => 'url',
				'meta'         => '_wppa_website',
				'placeholder'  => 'https://example.com',
			),
			'telegram'   => array(
				'label'       => __( 'Telegram', 'wp-personal-account' ),
				'type'        => 'text',
				'meta'        => '_wppa_telegram',
				'placeholder' => '@username',
			),
			'about'      => array(
				'label'           => __( 'О себе', 'wp-personal-account' ),
				'type'            => 'textarea',
				'user'            => 'description',
				'rows'            => 4,
				'enabled_default' => 1,
				'profile_native'  => 1,
			),
		);

		/**
		 * Фильтр реестра полей персональных данных.
		 *
		 * @param array $fields Поля.
		 */
		return apply_filters( 'wppa_fields', $fields );
	}

	/**
	 * Схема полей с учётом настроек: enabled / required.
	 *
	 * @return array
	 */
	public static function schema() {
		$saved = WPPA_Plugin::setting( self::SETTING, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$out   = array();

		foreach ( self::all() as $id => $def ) {
			$conf     = isset( $saved[ $id ] ) && is_array( $saved[ $id ] ) ? $saved[ $id ] : array();
			$enabled  = isset( $conf['enabled'] ) ? (int) $conf['enabled'] : (int) ( isset( $def['enabled_default'] ) ? $def['enabled_default'] : 0 );
			$required = isset( $conf['required'] ) ? (int) $conf['required'] : (int) ( isset( $def['required_default'] ) ? $def['required_default'] : 0 );

			$def['id']       = $id;
			$def['enabled']  = $enabled ? 1 : 0;
			$def['required'] = $def['enabled'] && $required ? 1 : 0;

			$out[ $id ] = $def;
		}

		return $out;
	}

	/**
	 * Включено ли поле.
	 *
	 * @param string $id ID поля.
	 * @return bool
	 */
	public static function is_enabled( $id ) {
		$schema = self::schema();
		return ! empty( $schema[ $id ]['enabled'] );
	}

	/**
	 * Обязательно ли поле.
	 *
	 * @param string $id ID поля.
	 * @return bool
	 */
	public static function is_required( $id ) {
		$schema = self::schema();
		return ! empty( $schema[ $id ]['required'] );
	}

	/**
	 * Поля для конкретного контекста.
	 *
	 * @param string $context register|profile.
	 * @return array
	 */
	public static function fields( $context = 'register' ) {
		$fields = array();

		foreach ( self::schema() as $id => $def ) {
			if ( empty( $def['enabled'] ) ) {
				continue;
			}

			// В форме профиля «Имя», «Фамилия» и «О себе» — отдельные нативные поля.
			if ( 'profile' === $context && ! empty( $def['profile_native'] ) ) {
				continue;
			}

			$fields[ $id ] = $def;
		}

		return $fields;
	}

	/**
	 * Значение поля у пользователя.
	 *
	 * @param int    $user_id ID пользователя.
	 * @param string $id      ID поля.
	 * @return string
	 */
	public static function get( $user_id, $id ) {
		$all = self::all();

		if ( ! isset( $all[ $id ] ) ) {
			return '';
		}

		$def  = $all[ $id ];
		$user = $user_id ? get_userdata( (int) $user_id ) : false;

		if ( ! empty( $def['user'] ) ) {
			return $user && isset( $user->{$def['user']} ) ? (string) $user->{$def['user']} : '';
		}

		if ( ! empty( $def['meta'] ) ) {
			return (string) get_user_meta( (int) $user_id, $def['meta'], true );
		}

		return '';
	}

	/**
	 * Все значения полей пользователя (только включённые).
	 *
	 * @param int $user_id ID пользователя.
	 * @return array id => значение.
	 */
	public static function values_of_user( $user_id ) {
		$values = array();

		foreach ( self::schema() as $id => $def ) {
			if ( empty( $def['enabled'] ) ) {
				continue;
			}

			$values[ $id ] = self::get( $user_id, $id );
		}

		return $values;
	}

	/**
	 * Значения из запроса.
	 *
	 * @param array $source Источник (обычно $_POST).
	 * @return array id => значение (сырое).
	 */
	public static function values_from_request( $source ) {
		$raw    = isset( $source['wppa_fields'] ) && is_array( $source['wppa_fields'] ) ? $source['wppa_fields'] : array();
		$values = array();

		foreach ( array_keys( self::all() ) as $id ) {
			if ( array_key_exists( $id, $raw ) ) {
				$values[ $id ] = (string) wp_unslash( $raw[ $id ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- санитизация ниже.
			}
		}

		return $values;
	}

	/**
	 * Санитизация значения.
	 *
	 * @param string $id  ID поля.
	 * @param string $raw Сырое значение.
	 * @return string
	 */
	public static function sanitize_value( $id, $raw ) {
		$all = self::all();
		$raw = (string) $raw;

		if ( ! isset( $all[ $id ] ) ) {
			return '';
		}

		$type = isset( $all[ $id ]['type'] ) ? $all[ $id ]['type'] : 'text';

		switch ( $type ) {
			case 'textarea':
				return sanitize_textarea_field( $raw );
			case 'url':
				return esc_url_raw( trim( $raw ) );
			case 'email':
				return sanitize_email( $raw );
			case 'date':
				return preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( $raw ) ) ? trim( $raw ) : '';
			case 'tel':
				return trim( preg_replace( '/[^0-9+()\-\s]/', '', $raw ) );
			default:
				return sanitize_text_field( $raw );
		}
	}

	/**
	 * Проверка значений.
	 *
	 * @param array  $values  Значения (уже санитизированные).
	 * @param string $context register|profile.
	 * @return string Код ошибки или пустая строка.
	 */
	public static function validate( $values, $context = 'register' ) {
		foreach ( self::fields( $context ) as $id => $def ) {
			$value = isset( $values[ $id ] ) ? trim( (string) $values[ $id ] ) : '';

			if ( '' === $value ) {
				if ( ! empty( $def['required'] ) ) {
					return 'field_required';
				}
				continue;
			}

			$type = isset( $def['type'] ) ? $def['type'] : 'text';

			if ( 'tel' === $type ) {
				$digits = preg_replace( '/\D/', '', $value );
				if ( strlen( $digits ) < 5 || strlen( $digits ) > 20 ) {
					return 'phone_invalid';
				}
			}

			if ( 'date' === $type ) {
				$date = \DateTime::createFromFormat( 'Y-m-d', $value );
				$min  = new \DateTime( '1900-01-01' );
				$max  = new \DateTime( 'tomorrow' );

				if ( ! $date || $date->format( 'Y-m-d' ) !== $value || $date < $min || $date > $max ) {
					return 'birthdate_invalid';
				}
			}

			if ( 'url' === $type ) {
				$url = esc_url_raw( $value );
				if ( '' === $url || ! wp_parse_url( $url, PHP_URL_HOST ) ) {
					return 'website_invalid';
				}
			}

			if ( 'text' === $type && strlen( $value ) > 190 ) {
				return 'field_too_long';
			}
		}

		return '';
	}

	/**
	 * Данные для wp_insert_user()/wp_update_user() из значений полей.
	 *
	 * @param array $values Значения.
	 * @return array
	 */
	public static function user_data( $values ) {
		$data = array();

		foreach ( self::all() as $id => $def ) {
			if ( empty( $def['user'] ) || ! isset( $values[ $id ] ) ) {
				continue;
			}

			$data[ $def['user'] ] = self::sanitize_value( $id, $values[ $id ] );
		}

		return $data;
	}

	/**
	 * Сохранение мета-полей пользователя.
	 *
	 * @param int   $user_id ID пользователя.
	 * @param array $values  Значения.
	 * @return void
	 */
	public static function save_meta( $user_id, $values ) {
		foreach ( self::all() as $id => $def ) {
			if ( empty( $def['meta'] ) || ! array_key_exists( $id, $values ) ) {
				continue;
			}

			$value = self::sanitize_value( $id, $values[ $id ] );

			if ( '' === $value ) {
				delete_user_meta( (int) $user_id, $def['meta'] );
			} else {
				update_user_meta( (int) $user_id, $def['meta'], $value );
			}
		}
	}

	/**
	 * Вывод полей формы.
	 *
	 * @param string $context register|profile.
	 * @param int    $user_id ID пользователя (для значений).
	 * @param array  $values  Подготовленные значения (перекрывают значения пользователя).
	 * @return void
	 */
	public static function render( $context = 'register', $user_id = 0, $values = array() ) {
		$fields = self::fields( $context );

		// Имя и фамилия выводятся в одну строку, если включены оба поля.
		$pair_ids  = array();
		$pair_open = false;

		if ( isset( $fields['first_name'], $fields['last_name'] ) ) {
			$pair_ids = array( 'first_name', 'last_name' );
		}

		foreach ( $fields as $id => $def ) {
			$value = isset( $values[ $id ] ) ? $values[ $id ] : ( $user_id ? self::get( $user_id, $id ) : '' );
			$type  = isset( $def['type'] ) ? $def['type'] : 'text';
			$req   = ! empty( $def['required'] );
			$name  = 'wppa_fields[' . $id . ']';
			$fid   = 'wppa-f-' . str_replace( '_', '-', $id );
			$label = isset( $def['label'] ) ? $def['label'] : $id;
			$auto  = isset( $def['autocomplete'] ) ? ' autocomplete="' . esc_attr( $def['autocomplete'] ) . '"' : '';
			$ph    = isset( $def['placeholder'] ) ? ' placeholder="' . esc_attr( $def['placeholder'] ) . '"' : '';

			if ( in_array( $id, $pair_ids, true ) && 'first_name' === $id ) {
				echo '<div class="wppa-row">';
				$pair_open = true;
			}

			echo '<p class="wppa-field' . ( 'textarea' === $type ? ' wppa-field--wide' : '' ) . '">';
			echo '<label for="' . esc_attr( $fid ) . '">' . esc_html( $label ) . ( $req ? ' <span class="wppa-req" aria-hidden="true">*</span>' : '' ) . '</label>';

			if ( 'textarea' === $type ) {
				printf(
					'<textarea id="%1$s" name="%2$s" rows="%3$d"%4$s%5$s>%6$s</textarea>',
					esc_attr( $fid ),
					esc_attr( $name ),
					(int) ( isset( $def['rows'] ) ? $def['rows'] : 4 ),
					$req ? ' required' : '',
					$ph,
					esc_textarea( $value )
				);
			} else {
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s%7$s />',
					esc_attr( $type ),
					esc_attr( $fid ),
					esc_attr( $name ),
					esc_attr( $value ),
					$req ? ' required' : '',
					$auto,
					$ph
				);
			}

			if ( ! empty( $def['hint'] ) ) {
				echo '<span class="wppa-hint">' . esc_html( $def['hint'] ) . '</span>';
			}

			echo '</p>';

			if ( $pair_open && 'last_name' === $id ) {
				echo '</div>';
				$pair_open = false;
			}
		}

		if ( $pair_open ) {
			echo '</div>';
		}
	}

	/**
	 * Поля с мета-хранением (для админ-таблиц и CSV).
	 *
	 * @return array id => определение.
	 */
	public static function meta_fields() {
		$fields = array();

		foreach ( self::schema() as $id => $def ) {
			if ( empty( $def['enabled'] ) || empty( $def['meta'] ) ) {
				continue;
			}

			$fields[ $id ] = $def;
		}

		return $fields;
	}
}
