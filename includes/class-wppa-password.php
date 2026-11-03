<?php
/**
 * Требования к паролю: набор правил, проверка и подсказки в формах.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Password
 */
class WPPA_Password {

	/**
	 * Ключ настройки со списком включённых правил.
	 */
	const SETTING = 'password_rules';

	/**
	 * Минимум разных символов в пароле (правило variety).
	 */
	const MIN_UNIQUE = 8;

	/**
	 * Список частых паролей и основ для проверки.
	 *
	 * @return array
	 */
	public static function common() {
		$words = array(
			'password',
			'passwort',
			'qwerty',
			'qwerty123',
			'123456',
			'1234567890',
			'1234567890123456',
			'111111',
			'admin',
			'administrator',
			'letmein',
			'welcome',
			'iloveyou',
			'monkey',
			'dragon',
			'football',
			'baseball',
			'sunshine',
			'princess',
			'abc123',
			'aaaaaa',
			'пароль',
			'йцукен',
			'привет',
			'любовь',
		);

		/**
		 * Фильтр словаря частых паролей.
		 *
		 * @param array $words Слова.
		 */
		return (array) apply_filters( 'wppa_common_passwords', $words );
	}

	/**
	 * Реестр правил.
	 *
	 * Ключ — ID правила: code (уведомление), label (подсказка), test (замыкание),
	 * enabled_default (включено по умолчанию), client (проверка в браузере доступна).
	 *
	 * @return array
	 */
	public static function rules() {
		$min = WPPA_Plugin::min_password_length();

		$rules = array(
			'length'     => array(
				'code'            => 'password_short',
				'label'           => sprintf(
					/* translators: %d: минимальная длина */
					__( 'Минимум %d символов', 'wp-personal-account' ),
					$min
				),
				'params'          => array( 'min' => $min ),
				'enabled_default' => 1,
				'client'          => 1,
				'test'            => function ( $password ) use ( $min ) {
					return strlen( $password ) >= $min;
				},
			),
			'uppercase'  => array(
				'code'            => 'password_no_uppercase',
				'label'           => __( 'Заглавная буква', 'wp-personal-account' ),
				'enabled_default' => 1,
				'client'          => 1,
				'test'            => function ( $password ) {
					return (bool) preg_match( '/\p{Lu}/u', $password );
				},
			),
			'lowercase'  => array(
				'code'            => 'password_no_lowercase',
				'label'           => __( 'Строчная буква', 'wp-personal-account' ),
				'enabled_default' => 1,
				'client'          => 1,
				'test'            => function ( $password ) {
					return (bool) preg_match( '/\p{Ll}/u', $password );
				},
			),
			'digit'      => array(
				'code'            => 'password_no_digit',
				'label'           => __( 'Цифра', 'wp-personal-account' ),
				'enabled_default' => 1,
				'client'          => 1,
				'test'            => function ( $password ) {
					return (bool) preg_match( '/\d/', $password );
				},
			),
			'special'    => array(
				'code'            => 'password_no_special',
				'label'           => __( 'Спецсимвол (! @ # $ % и т. п.)', 'wp-personal-account' ),
				'enabled_default' => 1,
				'client'          => 1,
				'test'            => function ( $password ) {
					return (bool) preg_match( '/[^\p{L}\p{N}]/u', $password );
				},
			),
			'variety'    => array(
				'code'            => 'password_variety',
				'label'           => sprintf(
					/* translators: %d: минимальное число разных символов */
					__( 'Не меньше %d разных символов', 'wp-personal-account' ),
					self::MIN_UNIQUE
				),
				'params'          => array( 'unique' => self::MIN_UNIQUE ),
				'enabled_default' => 1,
				'client'          => 1,
				'test'            => function ( $password ) {
					$chars = preg_split( '//u', $password, -1, PREG_SPLIT_NO_EMPTY );
					return count( array_unique( (array) $chars ) ) >= self::MIN_UNIQUE;
				},
			),
			'no_repeat'  => array(
				'code'            => 'password_repeat',
				'label'           => __( 'Без повторов вида «aaa» и «111»', 'wp-personal-account' ),
				'enabled_default' => 1,
				'client'          => 1,
				'test'            => function ( $password ) {
					return ! preg_match( '/(.)\1{2,}/u', $password );
				},
			),
			'no_sequence' => array(
				'code'            => 'password_sequence',
				'label'           => __( 'Без последовательностей «1234», «abcd», «qwer»', 'wp-personal-account' ),
				'enabled_default' => 1,
				'client'          => 1,
				'test'            => array( __CLASS__, 'has_no_sequence' ),
			),
			'not_common' => array(
				'code'            => 'password_common',
				'label'           => __( 'Не из списка частых паролей', 'wp-personal-account' ),
				'enabled_default' => 1,
				'client'          => 1,
				'test'            => array( __CLASS__, 'is_not_common' ),
			),
			'not_personal' => array(
				'code'            => 'password_personal',
				'label'           => __( 'Без email, логина и имени', 'wp-personal-account' ),
				'enabled_default' => 1,
				'client'          => 1,
				'test'            => array( __CLASS__, 'is_not_personal' ),
			),
		);

		/**
		 * Фильтр правил проверки пароля.
		 *
		 * @param array $rules Правила.
		 */
		return apply_filters( 'wppa_password_rules', $rules );
	}

	/**
	 * Правила с учётом настроек.
	 *
	 * @return array
	 */
	public static function enabled_rules() {
		$saved  = WPPA_Plugin::setting( self::SETTING, array() );
		$saved  = is_array( $saved ) ? $saved : array();
		$result = array();

		foreach ( self::rules() as $id => $rule ) {
			$enabled = array_key_exists( $id, $saved )
				? (int) $saved[ $id ]
				: (int) ( isset( $rule['enabled_default'] ) ? $rule['enabled_default'] : 1 );

			if ( $enabled ) {
				$rule['id']    = $id;
				$result[ $id ] = $rule;
			}
		}

		return $result;
	}

	/**
	 * Включено ли правило.
	 *
	 * @param string $id ID правила.
	 * @return bool
	 */
	public static function is_enabled( $id ) {
		$rules = self::enabled_rules();
		return isset( $rules[ $id ] );
	}

	/**
	 * Все проваленные правила.
	 *
	 * @param string $password Пароль.
	 * @param array  $context  Контекст: email, login, name, first_name, last_name.
	 * @return array ID правил.
	 */
	public static function failed( $password, $context = array() ) {
		$password = (string) $password;
		$failed   = array();

		foreach ( self::enabled_rules() as $id => $rule ) {
			$test = isset( $rule['test'] ) ? $rule['test'] : null;

			if ( ! is_callable( $test ) ) {
				continue;
			}

			if ( ! call_user_func( $test, $password, $context ) ) {
				$failed[] = $id;
			}
		}

		return $failed;
	}

	/**
	 * Код уведомления по первой ошибке.
	 *
	 * @param string $password Пароль.
	 * @param array  $context  Контекст.
	 * @return string Код или '' если пароль подходит.
	 */
	public static function error_code( $password, $context = array() ) {
		$failed = self::failed( $password, $context );

		if ( ! $failed ) {
			return '';
		}

		$rules = self::enabled_rules();
		$first = reset( $failed );

		return isset( $rules[ $first ]['code'] ) ? $rules[ $first ]['code'] : 'password_weak';
	}

	/**
	 * Список подсказок для формы.
	 *
	 * @param array $context Контекст.
	 * @return array id => label.
	 */
	public static function labels( $context = array() ) {
		$labels = array();

		foreach ( self::enabled_rules() as $id => $rule ) {
			$labels[ $id ] = isset( $rule['label'] ) ? $rule['label'] : $id;
		}

		return $labels;
	}

	/**
	 * Вывод списка требований под полем пароля.
	 *
	 * @param string $input_id ID поля пароля, к которому привязан список.
	 * @return void
	 */
	public static function render_requirements( $input_id = '' ) {
		$labels = self::labels();

		if ( ! $labels ) {
			return;
		}

		$list_id = 'wppa-pw-rules-' . sanitize_html_class( $input_id ? $input_id : wp_unique_id( 'x' ) );

		printf(
			'<ul class="wppa-password-rules" id="%1$s" data-wppa-password-rules data-for="%2$s">',
			esc_attr( $list_id ),
			esc_attr( $input_id )
		);
		echo '<li class="wppa-password-rules__title">' . esc_html__( 'Требования к паролю:', 'wp-personal-account' ) . '</li>';

		foreach ( $labels as $id => $label ) {
			$rules = self::enabled_rules();
			$params = isset( $rules[ $id ]['params'] ) ? wp_json_encode( $rules[ $id ]['params'] ) : '';

			printf(
				'<li class="wppa-password-rules__item" data-rule="%1$s" data-params="%2$s">%3$s%4$s</li>',
				esc_attr( $id ),
				esc_attr( $params ),
				'<span class="wppa-password-rules__mark" aria-hidden="true">•</span>',
				esc_html( $label )
			);
		}

		echo '</ul>';

		if ( '' !== $input_id ) {
			printf(
				'<span class="wppa-hint wppa-hint--block">%s</span>',
				esc_html__( 'Список подсвечивается по мере ввода: зелёное — выполнено.', 'wp-personal-account' )
			);
		}
	}

	/**
	 * Проверка на последовательности «1234», «abcd», «qwer».
	 *
	 * @param string $password Пароль.
	 * @return bool true — последовательностей нет.
	 */
	public static function has_no_sequence( $password ) {
		$password = (string) $password;
		$chars    = preg_split( '//u', $password, -1, PREG_SPLIT_NO_EMPTY );
		$count    = count( $chars );

		// Подряд идущие символы (1234, abcd, dcba).
		for ( $i = 0; $i + 3 < $count; $i++ ) {
			$a = ord( $chars[ $i ] );
			$b = ord( $chars[ $i + 1 ] );
			$c = ord( $chars[ $i + 2 ] );
			$d = ord( $chars[ $i + 3 ] );

			if ( ( $b === $a + 1 && $c === $b + 1 && $d === $c + 1 )
				|| ( $b === $a - 1 && $c === $b - 1 && $d === $c - 1 ) ) {
				return false;
			}
		}

		// Раскладки: qwer, asdf, zxcv.
		if ( preg_match( '/(qwer|wert|erty|rtyu|tyui|yuio|uiop|asdf|sdfg|dfgh|fghj|ghjk|hjkl|zxcv|xcvb|cvbn|vbnm)/i', $password ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Проверка на частые пароли и их основы.
	 *
	 * @param string $password Пароль.
	 * @return bool true — пароль не из словаря.
	 */
	public static function is_not_common( $password ) {
		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $password ) : strtolower( (string) $password );

		foreach ( self::common() as $word ) {
			$word = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $word ) : strtolower( (string) $word );

			if ( '' === $word ) {
				continue;
			}

			// Пароль целиком из словаря или содержит слово и мало отличается от него.
			if ( $lower === $word ) {
				return false;
			}

			if ( mb_strlen( $word ) >= 5 && false !== mb_strpos( $lower, $word ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Проверка, что пароль не содержит email, логин и имя.
	 *
	 * @param string $password Пароль.
	 * @param array  $context  Контекст: email, login, name, first_name, last_name.
	 * @return bool true — персональных данных в пароле нет.
	 */
	public static function is_not_personal( $password, $context = array() ) {
		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $password ) : strtolower( (string) $password );

		$parts = array();

		foreach ( array( 'email', 'login', 'name', 'first_name', 'last_name' ) as $key ) {
			if ( empty( $context[ $key ] ) ) {
				continue;
			}

			$value   = (string) $context[ $key ];
			$parts[] = $value;

			// Для email проверяем ещё и часть до @.
			if ( 'email' === $key && false !== strpos( $value, '@' ) ) {
				$value   = strstr( $value, '@', true );
				$parts[] = $value;
			}

			// Разбиваем на слова: «ivan.petrov», «Иван Петров» и т. п.
			foreach ( (array) preg_split( '/[^\p{L}\p{N}]+/u', $value, -1, PREG_SPLIT_NO_EMPTY ) as $token ) {
				$parts[] = $token;
			}
		}

		foreach ( $parts as $part ) {
			$part = trim( (string) $part );

			if ( mb_strlen( $part ) < 3 ) {
				continue;
			}

			$part = function_exists( 'mb_strtolower' ) ? mb_strtolower( $part ) : strtolower( $part );

			if ( false !== mb_strpos( $lower, $part ) ) {
				return false;
			}
		}

		return true;
	}
}
