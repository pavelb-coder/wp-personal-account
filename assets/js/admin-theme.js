/**
 * Оформление: предпросмотр и готовые наборы цветов на странице настроек.
 */
( function () {
	'use strict';

	var preview = document.getElementById( 'wppa-theme-preview' );

	if ( ! preview ) {
		return;
	}

	var inputs = document.querySelectorAll( '.wppa-theme-input' );
	var radius = document.getElementById( 'wppa-theme-radius' );
	var output = radius ? radius.parentNode.querySelector( 'output code' ) : null;

	/**
	 * Записать значение переменной в предпросмотр.
	 *
	 * @param {string} name Имя переменной.
	 * @param {string} value Значение.
	 */
	function setVar( name, value ) {
		if ( ! name || ! value ) {
			return;
		}

		preview.style.setProperty( name, value );
	}

	/**
	 * Цвет текста на акцентном фоне: тёмный на светлом, белый на тёмном.
	 *
	 * @param {string} hex Цвет фона.
	 * @return {string}
	 */
	function contrastColor( hex ) {
		var value = String( hex || '' ).replace( '#', '' );

		if ( 3 === value.length ) {
			value = value[ 0 ] + value[ 0 ] + value[ 1 ] + value[ 1 ] + value[ 2 ] + value[ 2 ];
		}

		if ( 6 !== value.length ) {
			return '#ffffff';
		}

		var channels = [ 0, 2, 4 ].map( function ( offset ) {
			var part = parseInt( value.substr( offset, 2 ), 16 ) / 255;

			return part <= 0.03928 ? part / 12.92 : Math.pow( ( part + 0.055 ) / 1.055, 2.4 );
		} );

		var luminance = 0.2126 * channels[ 0 ] + 0.7152 * channels[ 1 ] + 0.0722 * channels[ 2 ];

		return luminance > 0.55 ? '#0f172a' : '#ffffff';
	}

	/**
	 * Затемнить цвет — как это делает WPPA_Theme::darken().
	 *
	 * @param {string} hex    Цвет.
	 * @param {number} amount Доля затемнения.
	 * @return {string}
	 */
	function darken( hex, amount ) {
		var value = String( hex || '' ).replace( '#', '' );

		if ( 6 !== value.length ) {
			return hex;
		}

		var out = '#';

		[ 0, 2, 4 ].forEach( function ( offset ) {
			var part = Math.round( parseInt( value.substr( offset, 2 ), 16 ) * ( 1 - amount ) );

			part = Math.max( 0, Math.min( 255, part ) );
			out += ( '0' + part.toString( 16 ) ).slice( -2 );
		} );

		return out;
	}

	/**
	 * Пересчитать производные переменные: наведение и текст на акценте.
	 */
	function syncDerived() {
		var accentInput = document.querySelector( '.wppa-theme-input[data-wppa-var="--wppa-accent"]' );
		var hoverInput = document.querySelector( '.wppa-theme-input[data-wppa-var="--wppa-accent-hover"]' );
		var contrastInput = document.querySelector( '.wppa-theme-input[data-wppa-var="--wppa-accent-contrast"]' );
		var accent = accentInput ? accentInput.value : '#2563eb';

		setVar( '--wppa-accent', accent );
		setVar( '--wppa-accent-hover', hoverInput && hoverInput.value ? hoverInput.value : darken( accent, 0.12 ) );
		setVar( '--wppa-accent-contrast', contrastInput && contrastInput.value ? contrastInput.value : contrastColor( accent ) );
	}

	Array.prototype.forEach.call( inputs, function ( input ) {
		input.addEventListener( 'input', function () {
			var name = input.getAttribute( 'data-wppa-var' );
			var value = input.value;

			if ( ! value || ! /^#?[0-9a-f]{3,6}$/i.test( value ) ) {
				value = input.getAttribute( 'data-wppa-fallback' ) || '';
				input.classList.add( 'wppa-theme-input--invalid' );
			} else {
				input.classList.remove( 'wppa-theme-input--invalid' );

				if ( '#' !== value.charAt( 0 ) ) {
					value = '#' + value;
				}
			}

			setVar( name, value );
			syncDerived();
		} );
	} );

	// Прочие переменные: селекты, числовые поля, ползунки вида «--wppa-heading-size».
	Array.prototype.forEach.call( document.querySelectorAll( '.wppa-theme-var[data-wppa-var]' ), function ( input ) {
		var update = function () {
			var unit = input.getAttribute( 'data-wppa-unit' ) || '';

			if ( input.value ) {
				setVar( input.getAttribute( 'data-wppa-var' ), input.value + unit );
			}
		};

		input.addEventListener( 'input', update );
		input.addEventListener( 'change', update );
	} );

	// Текст заголовка в предпросмотре.
	var headingText = document.querySelector( '[data-wppa-heading-text]' );
	var headingPreview = document.getElementById( 'wppa-theme-preview-heading' );

	if ( headingText && headingPreview ) {
		var headingDefault = headingText.getAttribute( 'placeholder' ) || headingPreview.textContent;

		headingText.addEventListener( 'input', function () {
			headingPreview.textContent = headingText.value.trim() || headingDefault;
		} );
	}

	if ( radius ) {
		radius.addEventListener( 'input', function () {
			var value = parseInt( radius.value, 10 ) || 0;

			setVar( '--wppa-radius', value + 'px' );

			if ( output ) {
				output.textContent = value + 'px';
			}
		} );
	}

	Array.prototype.forEach.call( document.querySelectorAll( '[data-wppa-preset]' ), function ( button ) {
		button.addEventListener( 'click', function () {
			var colors;

			try {
				colors = JSON.parse( button.getAttribute( 'data-wppa-preset' ) || '{}' );
			} catch ( error ) {
				return;
			}

			Object.keys( colors ).forEach( function ( key ) {
				var input = document.getElementById( 'wppa-theme-' + key );

				if ( ! input ) {
					return;
				}

				input.value = colors[ key ];

				if ( input.getAttribute( 'data-wppa-var' ) ) {
					setVar( input.getAttribute( 'data-wppa-var' ), colors[ key ] );
				}
			} );

			syncDerived();
		} );
	} );

	syncDerived();
}() );
