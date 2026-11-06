<?php
/**
 * Оформление кабинета: цвета, скругления и дополнительный CSS.
 *
 * Значения хранятся в опции `wppa_settings` рядом с остальными настройками,
 * а на страницу попадают CSS-переменными `--wppa-*` (см. assets/css/account.css).
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Класс оформления.
 */
class WPPA_Theme {

	/**
	 * Минимальный радиус скругления, px.
	 */
	const RADIUS_MIN = 0;

	/**
	 * Максимальный радиус скругления, px.
	 */
	const RADIUS_MAX = 32;

	/**
	 * Радиус по умолчанию, px.
	 */
	const RADIUS_DEFAULT = 14;

	/**
	 * Цвета и подписи к ним.
	 *
	 * Ключ — имя настройки, значение — массив с подписью, значением по умолчанию
	 * и подсказкой. Пустое значение по умолчанию означает «подобрать автоматически».
	 *
	 * @return array
	 */
	public static function colors() {
		return array(
			'accent_color'          => array(
				'label'   => __( 'Акцентный цвет', 'wp-personal-account' ),
				'default' => '#2563eb',
				'hint'    => __( 'Кнопки, ссылки, активная вкладка.', 'wp-personal-account' ),
			),
			'accent_hover_color'    => array(
				'label'   => __( 'Акцент при наведении', 'wp-personal-account' ),
				'default' => '',
				'hint'    => __( 'Пусто — затемнение акцентного цвета на 12%.', 'wp-personal-account' ),
			),
			'accent_contrast_color' => array(
				'label'   => __( 'Текст на акцентном фоне', 'wp-personal-account' ),
				'default' => '',
				'hint'    => __( 'Пусто — подбирается автоматически: белый или тёмный.', 'wp-personal-account' ),
			),
			'color_bg'              => array(
				'label'   => __( 'Фон страницы', 'wp-personal-account' ),
				'default' => '#f8fafc',
				'hint'    => __( 'Пустая область вокруг карточек кабинета.', 'wp-personal-account' ),
			),
			'color_surface'         => array(
				'label'   => __( 'Фон карточек', 'wp-personal-account' ),
				'default' => '#ffffff',
				'hint'    => __( 'Карточки, полки файлов, таблицы.', 'wp-personal-account' ),
			),
			'color_text'            => array(
				'label'   => __( 'Основной текст', 'wp-personal-account' ),
				'default' => '#0f172a',
				'hint'    => __( 'Заголовки, значения полей, подписи кнопок.', 'wp-personal-account' ),
			),
			'color_muted'           => array(
				'label'   => __( 'Второстепенный текст', 'wp-personal-account' ),
				'default' => '#64748b',
				'hint'    => __( 'Подсказки, описания, неактивные вкладки.', 'wp-personal-account' ),
			),
			'color_border'          => array(
				'label'   => __( 'Рамки и разделители', 'wp-personal-account' ),
				'default' => '#e2e8f0',
				'hint'    => __( 'Границы полей, карточек и таблиц.', 'wp-personal-account' ),
			),
		);
	}

	/**
	 * Значения по умолчанию: цвета, радиус, свой CSS.
	 *
	 * @return array
	 */
	public static function defaults() {
		$out = array();

		foreach ( self::colors() as $key => $meta ) {
			$out[ $key ] = $meta['default'];
		}

		$out['radius']     = self::RADIUS_DEFAULT;
		$out['custom_css'] = '';

		return $out;
	}

	/**
	 * Готовые наборы цветов.
	 *
	 * @return array
	 */
	public static function presets() {
		return array(
			'default' => array(
				'label'  => __( 'Синяя', 'wp-personal-account' ),
				'colors' => array(
					'accent_color' => '#2563eb',
					'color_bg'     => '#f8fafc',
					'color_surface' => '#ffffff',
					'color_text'   => '#0f172a',
					'color_muted'  => '#64748b',
					'color_border' => '#e2e8f0',
				),
			),
			'forest'  => array(
				'label'  => __( 'Зелёная', 'wp-personal-account' ),
				'colors' => array(
					'accent_color' => '#15803d',
					'color_bg'     => '#f4f9f4',
					'color_surface' => '#ffffff',
					'color_text'   => '#10241a',
					'color_muted'  => '#5b7263',
					'color_border' => '#d9e6dc',
				),
			),
			'violet'  => array(
				'label'  => __( 'Фиолетовая', 'wp-personal-account' ),
				'colors' => array(
					'accent_color' => '#7c3aed',
					'color_bg'     => '#f7f5fd',
					'color_surface' => '#ffffff',
					'color_text'   => '#1b1330',
					'color_muted'  => '#6b6580',
					'color_border' => '#e4dff2',
				),
			),
			'dark'    => array(
				'label'  => __( 'Тёмная', 'wp-personal-account' ),
				'colors' => array(
					'accent_color' => '#60a5fa',
					'color_bg'     => '#0f172a',
					'color_surface' => '#1e293b',
					'color_text'   => '#e2e8f0',
					'color_muted'  => '#94a3b8',
					'color_border' => '#334155',
				),
			),
		);
	}

	/**
	 * Действующие значения: сохранённые или по умолчанию.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = WPPA_Plugin::get_settings();
		$out   = self::defaults();

		foreach ( self::defaults() as $key => $default ) {
			if ( ! isset( $saved[ $key ] ) ) {
				continue;
			}

			$value = $saved[ $key ];

			// Пустая строка у цвета — «авто», у остальных ключей берём умолчание.
			if ( is_string( $value ) && '' === trim( $value ) && '' !== (string) $default ) {
				continue;
			}

			$out[ $key ] = $value;
		}

		$out['custom_css'] = is_string( $out['custom_css'] ) ? $out['custom_css'] : '';
		$out['radius']     = self::clamp_radius( $out['radius'] );

		return $out;
	}

	/**
	 * Привести значение радиуса к допустимому диапазону.
	 *
	 * @param mixed $value Значение.
	 * @return int
	 */
	public static function clamp_radius( $value ) {
		$value = (int) $value;

		return (int) min( self::RADIUS_MAX, max( self::RADIUS_MIN, $value ) );
	}

	/**
	 * Нормализовать hex-цвет: `#abc` → `#aabbcc`, мусор → пустая строка.
	 *
	 * @param mixed $value Значение.
	 * @return string
	 */
	public static function normalize_hex( $value ) {
		$value = strtolower( trim( (string) $value ) );

		if ( '' === $value ) {
			return '';
		}

		if ( '#' !== substr( $value, 0, 1 ) ) {
			$value = '#' . $value;
		}

		if ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m ) ) {
			return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		}

		if ( preg_match( '/^#[0-9a-f]{6}$/', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Санитизация цвета с запасным значением.
	 *
	 * @param mixed  $value    Значение.
	 * @param string $fallback Запасное значение.
	 * @return string
	 */
	public static function hex( $value, $fallback = '' ) {
		$hex = self::normalize_hex( $value );

		return '' !== $hex ? $hex : self::normalize_hex( $fallback );
	}

	/**
	 * Относительная яркость цвета (0…1) по формуле WCAG.
	 *
	 * @param string $hex Цвет.
	 * @return float
	 */
	public static function luminance( $hex ) {
		$hex = ltrim( self::hex( $hex ), '#' );

		if ( 6 !== strlen( $hex ) ) {
			return 0.0;
		}

		$channels = array();

		foreach ( array( 0, 2, 4 ) as $offset ) {
			$part = hexdec( substr( $hex, $offset, 2 ) ) / 255;

			$channels[] = $part <= 0.03928 ? $part / 12.92 : pow( ( $part + 0.055 ) / 1.055, 2.4 );
		}

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	/**
	 * Цвет текста на заданном фоне: тёмный или белый.
	 *
	 * @param string $hex Фон.
	 * @return string
	 */
	public static function contrast_color( $hex ) {
		return self::luminance( $hex ) > 0.55 ? '#0f172a' : '#ffffff';
	}

	/**
	 * Затемнить цвет.
	 *
	 * @param string $hex    Исходный цвет.
	 * @param float  $amount Доля затемнения (0.12 — на 12%).
	 * @return string
	 */
	public static function darken( $hex, $amount = 0.12 ) {
		$hex = ltrim( self::hex( $hex ), '#' );

		if ( 6 !== strlen( $hex ) ) {
			return '';
		}

		$amount = max( 0.0, min( 0.9, (float) $amount ) );
		$out    = '#';

		foreach ( array( 0, 2, 4 ) as $offset ) {
			$part = (int) round( hexdec( substr( $hex, $offset, 2 ) ) * ( 1 - $amount ) );
			$out .= str_pad( dechex( max( 0, min( 255, $part ) ) ), 2, '0', STR_PAD_LEFT );
		}

		return $out;
	}

	/**
	 * Цвет акцента при наведении.
	 *
	 * @param array $theme Значения оформления.
	 * @return string
	 */
	public static function accent_hover( $theme ) {
		$hover = self::normalize_hex( isset( $theme['accent_hover_color'] ) ? $theme['accent_hover_color'] : '' );

		if ( '' !== $hover ) {
			return $hover;
		}

		return self::darken( isset( $theme['accent_color'] ) ? $theme['accent_color'] : '#2563eb' );
	}

	/**
	 * Цвет текста на акцентном фоне.
	 *
	 * @param array $theme Значения оформления.
	 * @return string
	 */
	public static function accent_contrast( $theme ) {
		$contrast = self::normalize_hex( isset( $theme['accent_contrast_color'] ) ? $theme['accent_contrast_color'] : '' );

		if ( '' !== $contrast ) {
			return $contrast;
		}

		return self::contrast_color( isset( $theme['accent_color'] ) ? $theme['accent_color'] : '#2563eb' );
	}

	/**
	 * CSS-переменные оформления.
	 *
	 * @param array|null $theme Значения; null — сохранённые.
	 * @return array Имя переменной => значение.
	 */
	public static function vars( $theme = null ) {
		$theme = is_array( $theme ) ? $theme : self::get();
		$base  = self::defaults();

		return array(
			'--wppa-accent'          => self::hex( isset( $theme['accent_color'] ) ? $theme['accent_color'] : '', $base['accent_color'] ),
			'--wppa-accent-hover'    => self::accent_hover( $theme ),
			'--wppa-accent-contrast' => self::accent_contrast( $theme ),
			'--wppa-bg'              => self::hex( isset( $theme['color_bg'] ) ? $theme['color_bg'] : '', $base['color_bg'] ),
			'--wppa-surface'         => self::hex( isset( $theme['color_surface'] ) ? $theme['color_surface'] : '', $base['color_surface'] ),
			'--wppa-text'            => self::hex( isset( $theme['color_text'] ) ? $theme['color_text'] : '', $base['color_text'] ),
			'--wppa-muted'           => self::hex( isset( $theme['color_muted'] ) ? $theme['color_muted'] : '', $base['color_muted'] ),
			'--wppa-border'          => self::hex( isset( $theme['color_border'] ) ? $theme['color_border'] : '', $base['color_border'] ),
			'--wppa-radius'          => self::clamp_radius( isset( $theme['radius'] ) ? $theme['radius'] : $base['radius'] ) . 'px',
		);
	}

	/**
	 * CSS оформления: переменные плюс свой CSS, без обёртки style.
	 *
	 * @param array|null $theme Значения; null — сохранённые.
	 * @return string
	 */
	public static function css( $theme = null ) {
		$theme = is_array( $theme ) ? $theme : self::get();
		$parts = array();

		foreach ( self::vars( $theme ) as $name => $value ) {
			$parts[] = $name . ':' . $value;
		}

		// Переменные нужны и в :root, и на контейнерах кабинета: стили объявляют
		// значения по умолчанию на .wppa, поэтому инлайновый блок должен их перекрыть.
		$css = self::selectors() . '{' . implode( ';', $parts ) . ';}';

		$custom = self::sanitize_custom_css( isset( $theme['custom_css'] ) ? $theme['custom_css'] : '' );

		if ( '' !== $custom ) {
			$css .= "\n" . $custom;
		}

		return $css;
	}

	/**
	 * Селекторы, на которые вешаются переменные оформления.
	 *
	 * @return string
	 */
	public static function selectors() {
		/**
		 * Фильтр селекторов оформления.
		 *
		 * @param string $selectors Список через запятую.
		 */
		return (string) apply_filters( 'wppa_theme_selectors', ':root,.wppa,.wppa--guest,.wppa--widget' );
	}

	/**
	 * Свой CSS без разметки: только текст стилей.
	 *
	 * @param mixed $css Введённый CSS.
	 * @return string
	 */
	public static function sanitize_custom_css( $css ) {
		$css = is_string( $css ) ? $css : '';
		$css = wp_strip_all_tags( $css );
		$css = (string) preg_replace( '#</?\s*style[^>]*>#i', '', $css );
		$css = (string) preg_replace( '#expression\s*\(#i', '', $css );
		$css = str_replace( '<', '', str_replace( '>', '', $css ) );

		return trim( $css );
	}

	/**
	 * Санитизация значений формы оформления.
	 *
	 * @param array $input Данные формы (всё `wppa_settings`).
	 * @return array Только ключи оформления.
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$reset    = ! empty( $input['theme_reset'] );
		$out      = array();

		foreach ( self::colors() as $key => $meta ) {
			if ( $reset ) {
				$out[ $key ] = $defaults[ $key ];
				continue;
			}

			$hex = self::normalize_hex( isset( $input[ $key ] ) ? $input[ $key ] : '' );

			// Обязательные цвета: пусто или мусор — берём умолчание.
			// Необязательные (наведение, текст на акценте): пусто — «авто».
			$out[ $key ] = '' !== $hex ? $hex : $defaults[ $key ];
		}

		$out['radius'] = $reset
			? self::RADIUS_DEFAULT
			: self::clamp_radius( isset( $input['radius'] ) ? $input['radius'] : self::RADIUS_DEFAULT );

		$out['custom_css'] = $reset
			? ''
			: self::sanitize_custom_css( isset( $input['custom_css'] ) ? $input['custom_css'] : '' );

		return $out;
	}

	/**
	 * Оформление совпадает с умолчаниями (для подсказки в настройках).
	 *
	 * @return bool
	 */
	public static function is_default() {
		$theme = self::get();

		foreach ( self::defaults() as $key => $default ) {
			if ( (string) $theme[ $key ] !== (string) $default ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Стили для контекста, где нельзя подключить файл стилей (письма, превью).
	 *
	 * @return string
	 */
	public static function style_tag() {
		return '<style id="wppa-theme-inline">' . self::css() . '</style>';
	}
}
