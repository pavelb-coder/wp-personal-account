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
			'color_surface_alt'     => array(
				'label'   => __( 'Подложки и наведение', 'wp-personal-account' ),
				'default' => '#f1f5f9',
				'hint'    => __( 'Строки таблиц при наведении, мягкие плашки, шапки списков.', 'wp-personal-account' ),
			),
			'color_success'         => array(
				'label'   => __( 'Успех (текст и рамка)', 'wp-personal-account' ),
				'default' => '#047857',
				'hint'    => __( 'Уведомления об успехе, статусы «готово», значки ролей.', 'wp-personal-account' ),
			),
			'color_warning'         => array(
				'label'   => __( 'Предупреждение', 'wp-personal-account' ),
				'default' => '#b45309',
				'hint'    => __( 'Предупреждения, статусы «ожидает», подсказки об ошибках.', 'wp-personal-account' ),
			),
			'color_danger'          => array(
				'label'   => __( 'Ошибка и удаление', 'wp-personal-account' ),
				'default' => '#b91c1c',
				'hint'    => __( 'Текст ошибок, кнопки удаления, статусы «отменено».', 'wp-personal-account' ),
			),
			'color_info'            => array(
				'label'   => __( 'Информация', 'wp-personal-account' ),
				'default' => '#0284c7',
				'hint'    => __( 'Информационные уведомления и пояснения.', 'wp-personal-account' ),
			),
		);
	}

	/**
	 * Группы цветов для формы настроек.
	 *
	 * @return array
	 */
	public static function groups() {
		return array(
			'core'   => array(
				'label' => __( 'Основные цвета', 'wp-personal-account' ),
				'keys'  => array(
					'accent_color',
					'accent_hover_color',
					'accent_contrast_color',
					'color_bg',
					'color_surface',
					'color_surface_alt',
					'color_text',
					'color_muted',
					'color_border',
				),
			),
			'status' => array(
				'label' => __( 'Уведомления и статусы', 'wp-personal-account' ),
				'keys'  => array(
					'color_success',
					'color_warning',
					'color_danger',
					'color_info',
				),
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

		return array_merge( $out, self::heading_defaults() );
	}

	/**
	 * Значения по умолчанию для заголовка кабинета.
	 *
	 * @return array
	 */
	public static function heading_defaults() {
		return array(
			'heading_text'    => __( 'Личный кабинет', 'wp-personal-account' ),
			'heading_show'    => 1,
			'heading_tag'     => 'h1',
			'heading_size'    => 22,
			'heading_weight'  => 700,
			'heading_case'    => 'none',
			'heading_align'   => 'left',
			'heading_gap'     => 18,
			'heading_color'   => '',
			'hide_page_title' => 1,
		);
	}

	/**
	 * Ключи, у которых пустое значение — осмысленное («нет текста», «авто-цвет»).
	 *
	 * @return array
	 */
	public static function empty_allowed() {
		return array( 'heading_text', 'heading_color', 'accent_hover_color', 'accent_contrast_color', 'custom_css' );
	}

	/**
	 * Допустимые значения для выпадающих списков заголовка.
	 *
	 * @return array
	 */
	public static function heading_choices() {
		return array(
			'heading_tag'    => array( 'h1', 'h2', 'h3', 'div' ),
			'heading_weight' => array( 400, 500, 600, 700, 800 ),
			'heading_case'   => array( 'none', 'uppercase', 'capitalize', 'lowercase' ),
			'heading_align'  => array( 'left', 'center', 'right' ),
		);
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
					'accent_color'    => '#2563eb',
					'color_bg'        => '#f8fafc',
					'color_surface'   => '#ffffff',
					'color_surface_alt' => '#f1f5f9',
					'color_text'      => '#0f172a',
					'color_muted'     => '#64748b',
					'color_border'    => '#e2e8f0',
					'color_success'   => '#047857',
					'color_warning'   => '#b45309',
					'color_danger'    => '#b91c1c',
					'color_info'      => '#0284c7',
				),
			),
			'forest'  => array(
				'label'  => __( 'Зелёная', 'wp-personal-account' ),
				'colors' => array(
					'accent_color'    => '#15803d',
					'color_bg'        => '#f4f9f4',
					'color_surface'   => '#ffffff',
					'color_surface_alt' => '#eaf2ec',
					'color_text'      => '#10241a',
					'color_muted'     => '#5b7263',
					'color_border'    => '#d9e6dc',
					'color_success'   => '#166534',
					'color_warning'   => '#a16207',
					'color_danger'    => '#b91c1c',
					'color_info'      => '#0e7490',
				),
			),
			'violet'  => array(
				'label'  => __( 'Фиолетовая', 'wp-personal-account' ),
				'colors' => array(
					'accent_color'    => '#7c3aed',
					'color_bg'        => '#f7f5fd',
					'color_surface'   => '#ffffff',
					'color_surface_alt' => '#efeaf9',
					'color_text'      => '#1b1330',
					'color_muted'     => '#6b6580',
					'color_border'    => '#e4dff2',
					'color_success'   => '#047857',
					'color_warning'   => '#b45309',
					'color_danger'    => '#b91c1c',
					'color_info'      => '#6d28d9',
				),
			),
			'dark'    => array(
				'label'  => __( 'Тёмная', 'wp-personal-account' ),
				'colors' => array(
					'accent_color'    => '#60a5fa',
					'color_bg'        => '#0f172a',
					'color_surface'   => '#1e293b',
					'color_surface_alt' => '#273449',
					'color_text'      => '#e2e8f0',
					'color_muted'     => '#94a3b8',
					'color_border'    => '#334155',
					'color_success'   => '#34d399',
					'color_warning'   => '#fbbf24',
					'color_danger'    => '#f87171',
					'color_info'      => '#38bdf8',
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
			// Исключение — ключи из empty_allowed(): там пусто и есть значение.
			if ( is_string( $value ) && '' === trim( $value ) && '' !== (string) $default && ! in_array( $key, self::empty_allowed(), true ) ) {
				continue;
			}

			$out[ $key ] = $value;
		}

		$out['custom_css'] = is_string( $out['custom_css'] ) ? $out['custom_css'] : '';
		$out['radius']     = self::clamp_radius( $out['radius'] );

		return $out;
	}

	/**
	 * Целое в заданных границах.
	 *
	 * @param mixed $value    Значение.
	 * @param int   $min      Минимум.
	 * @param int   $max      Максимум.
	 * @param int   $fallback Значение по умолчанию.
	 * @return int
	 */
	public static function clamp_int( $value, $min, $max, $fallback ) {
		$value = (int) $value;

		if ( $value < $min || $value > $max ) {
			$value = (int) $fallback;
		}

		return (int) min( $max, max( $min, $value ) );
	}

	/**
	 * Значение из белого списка для ключей заголовка.
	 *
	 * @param string $key   Ключ.
	 * @param mixed  $value Значение.
	 * @return string
	 */
	public static function heading_choice( $key, $value ) {
		$choices = self::heading_choices();
		$allowed = isset( $choices[ $key ] ) ? $choices[ $key ] : array();
		$value   = is_string( $value ) ? strtolower( trim( $value ) ) : $value;

		foreach ( $allowed as $option ) {
			if ( (string) $option === (string) $value ) {
				return (string) $option;
			}
		}

		$defaults = self::heading_defaults();

		return isset( $defaults[ $key ] ) ? (string) $defaults[ $key ] : '';
	}

	/**
	 * Настройки заголовка кабинета.
	 *
	 * @return array text, show, tag, size, weight, case, align, gap, color, hide_page_title.
	 */
	public static function heading() {
		$theme    = self::get();
		$defaults = self::heading_defaults();
		$out      = array();

		foreach ( $defaults as $key => $default ) {
			$out[ $key ] = isset( $theme[ $key ] ) ? $theme[ $key ] : $default;
		}

		$out['text']            = trim( (string) $out['heading_text'] );
		$out['show']            = ! empty( $out['heading_show'] ) && '' !== $out['text'];
		$out['tag']             = self::heading_choice( 'heading_tag', $out['heading_tag'] );
		$out['size']            = self::clamp_int( $out['heading_size'], 12, 72, 22 );
		$out['weight']          = self::heading_choice( 'heading_weight', $out['heading_weight'] );
		$out['case']            = self::heading_choice( 'heading_case', $out['heading_case'] );
		$out['align']           = self::heading_choice( 'heading_align', $out['heading_align'] );
		$out['gap']             = self::clamp_int( $out['heading_gap'], 0, 64, 18 );
		$out['color']           = self::hex( $out['heading_color'] );
		$out['hide_page_title'] = ! empty( $out['hide_page_title'] ) ? 1 : 0;

		return $out;
	}

	/**
	 * CSS заголовков кабинета.
	 *
	 * @param array|null $theme Значения; null — сохранённые.
	 * @return string
	 */
	public static function heading_css() {
		$hd = self::heading();

		$css = '.wppa .wppa-title,.wppa-page-title{'
			. 'margin:0 0 var(--wppa-heading-gap,18px);'
			. 'font-size:var(--wppa-heading-size,22px);'
			. 'font-weight:var(--wppa-heading-weight,700);'
			. 'line-height:1.25;'
			. 'color:var(--wppa-heading-color,var(--wppa-text));'
			. 'text-transform:var(--wppa-heading-case,none);'
			. 'text-align:var(--wppa-heading-align,left);}';

		// Заголовок страницы выводит тема: прячем его только на странице кабинета —
		// и только если эта страница выбрана, иначе пострадали бы все страницы сайта.
		$page_id = (int) WPPA_Plugin::setting( 'account_page_id', 0 );

		if ( ! empty( $hd['hide_page_title'] ) && $page_id ) {
			$scope = 'body.page-id-' . $page_id . ' ';

			$css .= "\n" . $scope . '.entry-title,' . $scope . '.page-title,' . $scope . '.wp-block-post-title,'
				. $scope . '.entry-header h1{display:none;}';
		}

		return $css;
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
			'--wppa-surface-alt'     => self::hex( isset( $theme['color_surface_alt'] ) ? $theme['color_surface_alt'] : '', $base['color_surface_alt'] ),
			'--wppa-border'          => self::hex( isset( $theme['color_border'] ) ? $theme['color_border'] : '', $base['color_border'] ),
			'--wppa-success'         => self::hex( isset( $theme['color_success'] ) ? $theme['color_success'] : '', $base['color_success'] ),
			'--wppa-warning'         => self::hex( isset( $theme['color_warning'] ) ? $theme['color_warning'] : '', $base['color_warning'] ),
			'--wppa-danger'          => self::hex( isset( $theme['color_danger'] ) ? $theme['color_danger'] : '', $base['color_danger'] ),
			'--wppa-info'            => self::hex( isset( $theme['color_info'] ) ? $theme['color_info'] : '', $base['color_info'] ),
			'--wppa-radius'          => self::clamp_radius( isset( $theme['radius'] ) ? $theme['radius'] : $base['radius'] ) . 'px',
			'--wppa-heading-size'    => self::clamp_int( isset( $theme['heading_size'] ) ? $theme['heading_size'] : $base['heading_size'], 12, 72, 22 ) . 'px',
			'--wppa-heading-weight'  => (string) self::heading_choice( 'heading_weight', isset( $theme['heading_weight'] ) ? $theme['heading_weight'] : $base['heading_weight'] ),
			'--wppa-heading-color'   => self::hex( isset( $theme['heading_color'] ) ? $theme['heading_color'] : '', self::hex( isset( $theme['color_text'] ) ? $theme['color_text'] : '', $base['color_text'] ) ),
			'--wppa-heading-case'    => self::heading_choice( 'heading_case', isset( $theme['heading_case'] ) ? $theme['heading_case'] : $base['heading_case'] ),
			'--wppa-heading-align'   => self::heading_choice( 'heading_align', isset( $theme['heading_align'] ) ? $theme['heading_align'] : $base['heading_align'] ),
			'--wppa-heading-gap'     => self::clamp_int( isset( $theme['heading_gap'] ) ? $theme['heading_gap'] : $base['heading_gap'], 0, 64, 18 ) . 'px',
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

		$css .= "\n" . self::heading_css();

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

		// Заголовок кабинета.
		$heading_defaults = self::heading_defaults();

		if ( $reset ) {
			return array_merge( $out, $heading_defaults );
		}

		$out['heading_text']    = isset( $input['heading_text'] ) ? sanitize_text_field( (string) $input['heading_text'] ) : $heading_defaults['heading_text'];
		$out['heading_show']    = empty( $input['heading_show'] ) ? 0 : 1;
		$out['heading_tag']     = self::heading_choice( 'heading_tag', isset( $input['heading_tag'] ) ? $input['heading_tag'] : '' );
		$out['heading_size']    = isset( $input['heading_size'] ) ? self::clamp_int( $input['heading_size'], 12, 72, $heading_defaults['heading_size'] ) : $heading_defaults['heading_size'];
		$out['heading_weight']  = (int) self::heading_choice( 'heading_weight', isset( $input['heading_weight'] ) ? $input['heading_weight'] : '' );
		$out['heading_case']    = self::heading_choice( 'heading_case', isset( $input['heading_case'] ) ? $input['heading_case'] : '' );
		$out['heading_align']   = self::heading_choice( 'heading_align', isset( $input['heading_align'] ) ? $input['heading_align'] : '' );
		$out['heading_gap']     = isset( $input['heading_gap'] ) ? self::clamp_int( $input['heading_gap'], 0, 64, $heading_defaults['heading_gap'] ) : $heading_defaults['heading_gap'];
		$out['heading_color']   = self::normalize_hex( isset( $input['heading_color'] ) ? $input['heading_color'] : '' );
		$out['hide_page_title'] = empty( $input['hide_page_title'] ) ? 0 : 1;

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
