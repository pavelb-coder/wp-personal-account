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
	 * Ключ настройки с разделами полей.
	 */
	const SECTIONS = 'field_sections';

	/**
	 * Разделы по умолчанию.
	 *
	 * @return array id => label, order.
	 */
	public static function default_sections() {
		return array(
			'main'     => array(
				'label' => __( 'Основные данные', 'wp-personal-account' ),
				'order' => 10,
			),
			'contacts' => array(
				'label' => __( 'Контакты', 'wp-personal-account' ),
				'order' => 20,
			),
			'work'     => array(
				'label' => __( 'Работа', 'wp-personal-account' ),
				'order' => 30,
			),
		);
	}

	/**
	 * Разделы, которые были раньше и удалены: их сохранённые настройки игнорируем.
	 *
	 * @return array
	 */
	public static function retired_sections() {
		return array( 'about' );
	}

	/**
	 * Раздел и порядок поля по умолчанию.
	 *
	 * @return array id => array( раздел, порядок ).
	 */
	public static function default_layout() {
		return array(
			'last_name'  => array( 'main', 10 ),
			'first_name' => array( 'main', 20 ),
			'patronymic' => array( 'main', 30 ),
			'birthdate'  => array( 'main', 40 ),
			'phone'      => array( 'contacts', 10 ),
			'city'       => array( 'contacts', 20 ),

			'company'    => array( 'work', 10 ),
			'position'   => array( 'work', 20 ),
		);
	}

	/**
	 * Типы, доступные своим полям.
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'text'     => __( 'Текст', 'wp-personal-account' ),
			'textarea' => __( 'Многострочный текст', 'wp-personal-account' ),
			'tel'      => __( 'Телефон', 'wp-personal-account' ),
			'email'    => __( 'Email', 'wp-personal-account' ),
			'url'      => __( 'Ссылка', 'wp-personal-account' ),
			'date'     => __( 'Дата', 'wp-personal-account' ),
			'number'   => __( 'Число', 'wp-personal-account' ),
			'select'   => __( 'Список', 'wp-personal-account' ),
			'checkbox' => __( 'Флажок', 'wp-personal-account' ),
		);
	}

	/**
	 * Сохранённые настройки полей.
	 *
	 * @return array
	 */
	public static function saved() {
		$saved = WPPA_Plugin::setting( self::SETTING, array() );

		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Разделы с учётом настроек: переименование, порядок, удаление, свои разделы.
	 *
	 * @return array id => array( id, label, order, custom ).
	 */
	public static function sections() {
		$saved = WPPA_Plugin::setting( self::SECTIONS, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$out   = array();

		foreach ( self::default_sections() as $id => $def ) {
			$conf = isset( $saved[ $id ] ) && is_array( $saved[ $id ] ) ? $saved[ $id ] : array();

			// Раздел по умолчанию можно переименовать и удалить, кроме main:
			// в main падают поля, чей раздел исчез.
			if ( 'main' !== $id && ! empty( $conf['deleted'] ) ) {
				continue;
			}

			$out[ $id ] = array(
				'id'     => $id,
				'label'  => ! empty( $conf['label'] ) ? (string) $conf['label'] : $def['label'],
				'order'  => isset( $conf['order'] ) ? (int) $conf['order'] : (int) $def['order'],
				'custom' => 0,
			);
		}

		foreach ( $saved as $id => $conf ) {
			$id = (string) $id;

			if ( isset( $out[ $id ] ) || ! is_array( $conf ) || ! preg_match( '/^[a-z0-9_]+$/', $id ) ) {
				continue;
			}

			// Раздел, который мы убрали из плагина: сохранённые настройки не воскрешаем.
			if ( in_array( $id, self::retired_sections(), true ) ) {
				continue;
			}

			if ( ! empty( $conf['deleted'] ) || empty( $conf['label'] ) ) {
				continue;
			}

			$out[ $id ] = array(
				'id'     => $id,
				'label'  => (string) $conf['label'],
				'order'  => isset( $conf['order'] ) ? (int) $conf['order'] : 100,
				'custom' => 1,
			);
		}

		uasort(
			$out,
			static function ( $a, $b ) {
				if ( $a['order'] === $b['order'] ) {
					return strcmp( (string) $a['id'], (string) $b['id'] );
				}

				return $a['order'] < $b['order'] ? -1 : 1;
			}
		);

		return $out;
	}

	/**
	 * Отметка обязательного поля.
	 *
	 * @return string
	 */
	public static function req_mark() {
		return ' <span class="wppa-req" aria-hidden="true">*</span>';
	}

	/**
	 * Реестр доступных полей.
	 *
	 * Каждое поле: label, type, autocomplete, meta (или user), placeholder, hint,
	 * enabled_default, required_default, profile_native (поле уже есть в форме профиля).
	 *
	 * @return array
	 */
	public static function registry() {
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
				'label'           => __( 'Отчество', 'wp-personal-account' ),
				'type'            => 'text',
				'autocomplete'    => 'additional-name',
				'meta'            => '_wppa_patronymic',
				'enabled_default' => 1,
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
		);

		return $fields;
	}

	/**
	 * Эффективный реестр: встроенные поля плюс добавленные в настройках.
	 *
	 * @return array
	 */
	public static function all() {
		$fields = self::registry();
		$saved  = self::saved();
		$types  = array_keys( self::types() );

		foreach ( $saved as $id => $conf ) {
			$id = (string) $id;

			if ( ! is_array( $conf ) || ! preg_match( '/^[a-z0-9_]+$/', $id ) ) {
				continue;
			}

			// Встроенное поле: правится только подпись.
			if ( empty( $conf['custom'] ) ) {
				if ( isset( $fields[ $id ] ) && ! empty( $conf['label'] ) ) {
					$fields[ $id ]['label'] = (string) $conf['label'];
				}

				continue;
			}

			$type = isset( $conf['type'] ) ? (string) $conf['type'] : 'text';

			$fields[ $id ] = array(
				'label'       => ! empty( $conf['label'] ) ? (string) $conf['label'] : $id,
				'type'        => in_array( $type, $types, true ) ? $type : 'text',
				'meta'        => '_wppa_custom_' . preg_replace( '/^custom_/', '', $id ),
				'custom'      => 1,
				'placeholder' => isset( $conf['placeholder'] ) ? (string) $conf['placeholder'] : '',
				'hint'        => isset( $conf['hint'] ) ? (string) $conf['hint'] : '',
				'options'     => isset( $conf['options'] ) ? (string) $conf['options'] : '',
				'rows'        => 4,
			);
		}

		/**
		 * Фильтр реестра полей персональных данных.
		 *
		 * @param array $fields Поля.
		 */
		return apply_filters( 'wppa_fields', $fields );
	}

	/**
	 * Варианты списка для поля типа select.
	 *
	 * @param array $def Определение поля.
	 * @return array Список вариантов.
	 */
	public static function options_of( $def ) {
		$raw   = isset( $def['options'] ) ? (string) $def['options'] : '';
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$out   = array();

		foreach ( (array) $lines as $line ) {
			$line = trim( $line );

			if ( '' !== $line ) {
				$out[] = $line;
			}
		}

		return $out;
	}

	/**
	 * Схема полей с учётом настроек: раздел, порядок, enabled / required.
	 *
	 * @return array Поля в порядке вывода: раздел → порядок → реестр.
	 */
	public static function schema() {
		$saved  = self::saved();
		$layout = self::default_layout();
		$out    = array();
		$tier   = 0;

		foreach ( self::all() as $id => $def ) {
			$conf     = isset( $saved[ $id ] ) && is_array( $saved[ $id ] ) ? $saved[ $id ] : array();
			$enabled  = isset( $conf['enabled'] ) ? (int) $conf['enabled'] : (int) ( isset( $def['enabled_default'] ) ? $def['enabled_default'] : 0 );
			$required = isset( $conf['required'] ) ? (int) $conf['required'] : (int) ( isset( $def['required_default'] ) ? $def['required_default'] : 0 );

			$def['id']       = $id;
			$def['enabled']  = $enabled ? 1 : 0;
			$def['required'] = $def['enabled'] && $required ? 1 : 0;
			$def['custom']   = empty( $def['custom'] ) ? 0 : 1;

			// Раздел и порядок: из настроек, иначе по умолчанию, иначе в конец.
			$def['section'] = ! empty( $conf['section'] ) ? (string) $conf['section'] : ( isset( $layout[ $id ] ) ? $layout[ $id ][0] : 'main' );
			$def['order']   = isset( $conf['order'] ) ? (int) $conf['order'] : ( isset( $layout[ $id ] ) ? (int) $layout[ $id ][1] : 500 );

			$def['_tier'] = $tier++;
			$out[ $id ]   = $def;
		}

		uasort(
			$out,
			static function ( $a, $b ) {
				if ( $a['order'] === $b['order'] ) {
					return $a['_tier'] < $b['_tier'] ? -1 : 1;
				}

				return $a['order'] < $b['order'] ? -1 : 1;
			}
		);

		foreach ( $out as $id => $def ) {
			unset( $out[ $id ]['_tier'] );
		}

		return $out;
	}

	/**
	 * Включённые поля, сгруппированные по разделам.
	 *
	 * @param string $context register|profile.
	 * @return array id раздела => array( label, fields ).
	 */
	public static function grouped( $context = 'register' ) {
		$sections = self::sections();
		$fields   = self::fields( $context );
		$out      = array();
		$known    = array_keys( $sections );

		foreach ( $sections as $sid => $section ) {
			$in = array();

			foreach ( $fields as $id => $def ) {
				if ( $sid === $def['section'] ) {
					$in[ $id ] = $def;
				}
			}

			if ( $in ) {
				$out[ $sid ] = array(
					'label'  => $section['label'],
					'fields' => $in,
				);
			}
		}

		// Поле, у которого раздел исчез, не теряем: кладём в первый раздел.
		$orphans = array();

		foreach ( $fields as $id => $def ) {
			if ( ! in_array( $def['section'], $known, true ) ) {
				$orphans[ $id ] = $def;
			}
		}

		if ( $orphans ) {
			$first = array_key_first( $out );

			if ( null === $first ) {
				$first = array_key_first( $sections );
				$out[ $first ] = array(
					'label'  => isset( $sections[ $first ] ) ? $sections[ $first ]['label'] : __( 'Личные данные', 'wp-personal-account' ),
					'fields' => array(),
				);
			}

			$out[ $first ]['fields'] = array_merge( $out[ $first ]['fields'], $orphans );
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
			// Ограничение возраста требует дату даже при выключенном поле в настройках анкеты.
			if ( 'register' === $context && 'birthdate' === $id && self::registration_min_age() > 0 ) {
				$def['enabled']  = 1;
				$def['required'] = 1;
				$def['hint'] = sprintf( __( 'Минимальный возраст при регистрации: %d полных лет.', 'wp-personal-account' ), self::registration_min_age() );
			}
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

		$def  = $all[ $id ];
		$type = isset( $def['type'] ) ? $def['type'] : 'text';

		switch ( $type ) {
			case 'textarea':
				return sanitize_textarea_field( $raw );
			case 'url':
				return esc_url_raw( trim( $raw ) );
			case 'email':
				return sanitize_email( $raw );
			case 'date':
				return self::sanitize_date( $raw );
			case 'tel':
				return trim( preg_replace( '/[^0-9+()\-\s]/', '', $raw ) );
			case 'number':
				$number = str_replace( ',', '.', trim( preg_replace( '/[^0-9,.\-]/', '', $raw ) ) );

				return is_numeric( $number ) ? $number : '';
			case 'checkbox':
				return '' === trim( $raw ) ? '' : '1';
			case 'select':
				$value = sanitize_text_field( $raw );

				return in_array( $value, self::options_of( $def ), true ) ? $value : '';
			default:
				return sanitize_text_field( $raw );
		}
	}

	/**
	 * Дата в формате YYYY-MM-DD с проверкой календаря.
	 *
	 * Регулярка пропускала 2026-02-30 и 2026-13-01: такие значения сохранялись
	 * и потом не разбирались нигде.
	 *
	 * @param string $raw Значение из формы.
	 * @return string Пустая строка, если дата неверная.
	 */
	public static function sanitize_date( $raw ) {
		$raw = trim( (string) $raw );

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $parts ) ) {
			return '';
		}

		$year  = (int) $parts[1];
		$month = (int) $parts[2];
		$day   = (int) $parts[3];

		if ( $year < 1900 || $year > ( (int) gmdate( 'Y' ) + 1 ) || ! checkdate( $month, $day, $year ) ) {
			return '';
		}

		return $raw;
	}

	/** Минимальный возраст регистрации: 0 означает отсутствие ограничения. */
	public static function registration_min_age() {
		return min( 120, max( 0, (int) WPPA_Plugin::setting( 'registration_min_age', 0 ) ) );
	}

	/** Проверка возраста по календарной дате сайта (29 февраля: 1 марта в невисокосный год). */
	public static function registration_age_error( $birthdate, $today = '' ) {
		$age = self::registration_min_age();
		if ( ! $age ) {
			return '';
		}
		$birthdate = self::sanitize_date( $birthdate );
		if ( '' === $birthdate ) {
			return 'birthdate_invalid';
		}
		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$now = '' !== $today ? $today : ( new DateTimeImmutable( 'now', $timezone ) )->format( 'Y-m-d' );
		$born = DateTimeImmutable::createFromFormat( '!Y-m-d', $birthdate, $timezone );
		if ( $birthdate > $now ) {
			return 'birthdate_invalid';
		}
		return $born->modify( '+' . $age . ' years' )->format( 'Y-m-d' ) <= $now ? '' : 'registration_too_young';
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

			if ( 'number' === $type && ! is_numeric( str_replace( ',', '.', $value ) ) ) {
				return 'field_invalid';
			}

			if ( 'email' === $type && '' === sanitize_email( $value ) ) {
				return 'field_invalid';
			}

			if ( 'select' === $type && ! in_array( $value, self::options_of( $def ), true ) ) {
				return 'field_invalid';
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
	 * @param array  $exclude ID полей, уже выведенных отдельно.
	 * @return void
	 */
	public static function render( $context = 'register', $user_id = 0, $values = array(), $exclude = array() ) {
		foreach ( self::grouped( $context ) as $section_id => $section ) {
			$fields = array_diff_key( $section['fields'], array_flip( (array) $exclude ) );
			if ( ! $fields ) {
				continue;
			}

			echo '<div class="wppa-section" data-wppa-section="' . esc_attr( $section_id ) . '">';
			echo '<h3 class="wppa-section__title">' . esc_html( $section['label'] ) . '</h3>';

			// Имя и фамилия выводятся в одну строку, если оба поля в одном разделе.
			$pair_ids  = isset( $fields['first_name'], $fields['last_name'] ) ? array( 'first_name', 'last_name' ) : array();
			$pair_open = false;

			foreach ( $fields as $id => $def ) {
				$value = isset( $values[ $id ] ) ? $values[ $id ] : ( $user_id ? self::get( $user_id, $id ) : '' );
				$type  = isset( $def['type'] ) ? $def['type'] : 'text';
				$req   = ! empty( $def['required'] );
				$name  = 'wppa_fields[' . $id . ']';
				$fid   = 'wppa-f-' . str_replace( '_', '-', $id );
				$label = isset( $def['label'] ) ? $def['label'] : $id;
				$auto  = isset( $def['autocomplete'] ) ? ' autocomplete="' . esc_attr( $def['autocomplete'] ) . '"' : '';
				$ph    = isset( $def['placeholder'] ) && '' !== $def['placeholder'] ? ' placeholder="' . esc_attr( $def['placeholder'] ) . '"' : '';
				$wide  = in_array( $type, array( 'textarea', 'select' ), true );

				if ( $pair_open && ! in_array( $id, $pair_ids, true ) ) {
					echo '</div>';
					$pair_open = false;
				}

				if ( in_array( $id, $pair_ids, true ) && 'first_name' === $id ) {
					echo '<div class="wppa-row">';
					$pair_open = true;
				}

				echo '<p class="wppa-field' . ( $wide ? ' wppa-field--wide' : '' ) . '">';

				if ( 'checkbox' === $type ) {
					// Скрытое поле с пустым значением: снятый флажок тоже сохраняется.
					printf( '<input type="hidden" name="%1$s" value="" />', esc_attr( $name ) );
					printf(
						'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s /> %5$s%6$s</label>',
						esc_attr( $fid ),
						esc_attr( $name ),
						checked( '1', (string) $value, false ),
						$req ? ' required' : '',
						esc_html( $label ),
						$req ? ' <span class="wppa-req" aria-hidden="true">*</span>' : ''
					);
				} else {
					echo '<label for="' . esc_attr( $fid ) . '">' . esc_html( $label ) . ( $req ? ' <span class="wppa-req" aria-hidden="true">*</span>' : '' ) . '</label>';
				}

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
				} elseif ( 'select' === $type ) {
					printf( '<select id="%1$s" name="%2$s"%3$s>', esc_attr( $fid ), esc_attr( $name ), $req ? ' required' : '' );

					if ( ! $req ) {
						echo '<option value="">' . esc_html__( '— не выбрано —', 'wp-personal-account' ) . '</option>';
					}

					foreach ( self::options_of( $def ) as $option ) {
						printf(
							'<option value="%1$s"%2$s>%3$s</option>',
							esc_attr( $option ),
							selected( $option, (string) $value, false ),
							esc_html( $option )
						);
					}

					echo '</select>';
				} elseif ( 'checkbox' !== $type ) {
					printf(
						'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s%7$s%8$s />',
						esc_attr( in_array( $type, array( 'text', 'tel', 'email', 'url', 'date', 'number' ), true ) ? $type : 'text' ),
						esc_attr( $fid ),
						esc_attr( $name ),
						esc_attr( $value ),
						$req ? ' required' : '',
						$auto,
						$ph,
						'number' === $type ? ' step="any"' : ''
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
