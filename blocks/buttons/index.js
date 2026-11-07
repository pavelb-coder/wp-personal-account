/** Отдельные кнопки входа, выхода и регистрации для редактора блоков. */
( function ( blocks, blockEditor, element, components, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;

	var attributes = {
		buttonText: { type: 'string', default: '' },
		buttonStyle: { type: 'string', default: 'primary' },
		buttonSize: { type: 'string', default: 'md' },
		buttonFullWidth: { type: 'boolean', default: false },
		accentColor: { type: 'string', default: '' },
		cornerRadius: { type: 'number', default: 0 }
	};

	function registerButton( mode, title, defaultText, icon ) {
		blocks.registerBlockType( 'wppa/' + mode + '-button', {
			apiVersion: 2,
			title: title,
			category: 'widgets',
			icon: icon,
			attributes: Object.assign( {}, attributes ),
			edit: function ( props ) {
				var attrs = props.attributes;
				var blockProps = useBlockProps ? useBlockProps() : { className: 'wp-block-wppa-' + mode + '-button' };
				var text = attrs.buttonText || defaultText;
				var size = attrs.buttonSize || 'md';
				var buttonClass = 'components-button ' + ( attrs.buttonStyle === 'ghost' ? 'is-secondary' : 'is-primary' );
				var previewStyle = {};
				if ( /^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test( attrs.accentColor || '' ) && attrs.buttonStyle !== 'ghost' ) {
					previewStyle.backgroundColor = attrs.accentColor;
				}
				if ( attrs.cornerRadius > 0 ) {
					previewStyle.borderRadius = Math.min( 40, attrs.cornerRadius ) + 'px';
				}
				if ( attrs.buttonFullWidth ) {
					previewStyle.width = '100%';
				}
				if ( size === 'sm' || size === 'lg' ) {
					buttonClass += ' wppa-button--' + size;
					previewStyle.minHeight = size === 'lg' ? '50px' : '34px';
					previewStyle.padding = size === 'lg' ? '13px 22px' : '6px 14px';
				}
				if ( attrs.buttonFullWidth ) {
					buttonClass += ' wppa-button--block';
				}
				return el( element.Fragment, {},
					el( InspectorControls, {},
						el( PanelBody, { title: __( 'Настройки кнопки', 'wp-personal-account' ), initialOpen: true },
							el( TextControl, {
								label: __( 'Текст кнопки', 'wp-personal-account' ),
								value: attrs.buttonText || '',
								placeholder: defaultText,
								onChange: function ( value ) { props.setAttributes( { buttonText: value } ); }
							} ),
							el( SelectControl, {
								label: __( 'Стиль', 'wp-personal-account' ),
								value: attrs.buttonStyle || 'primary',
								options: [ { label: __( 'Основная', 'wp-personal-account' ), value: 'primary' }, { label: __( 'С обводкой', 'wp-personal-account' ), value: 'ghost' } ],
								onChange: function ( value ) { props.setAttributes( { buttonStyle: value } ); }
							} ),
							el( SelectControl, {
								label: __( 'Размер', 'wp-personal-account' ),
								value: size,
								options: [ { label: __( 'Компактный', 'wp-personal-account' ), value: 'sm' }, { label: __( 'Обычный', 'wp-personal-account' ), value: 'md' }, { label: __( 'Крупный', 'wp-personal-account' ), value: 'lg' } ],
								onChange: function ( value ) { props.setAttributes( { buttonSize: value } ); }
							} ),
							el( ToggleControl, {
								label: __( 'На всю ширину', 'wp-personal-account' ),
								checked: !! attrs.buttonFullWidth,
								onChange: function ( value ) { props.setAttributes( { buttonFullWidth: value } ); }
							} ),
							el( TextControl, {
								label: __( 'Акцентный цвет', 'wp-personal-account' ),
								help: __( 'HEX, например #2563eb. Пусто — из настроек плагина.', 'wp-personal-account' ),
								value: attrs.accentColor || '',
								onChange: function ( value ) { props.setAttributes( { accentColor: value } ); }
							} ),
							el( TextControl, {
								label: __( 'Радиус скругления, px', 'wp-personal-account' ),
								type: 'number', min: 0, max: 40,
								value: attrs.cornerRadius || 0,
								onChange: function ( value ) { props.setAttributes( { cornerRadius: Math.max( 0, Math.min( 40, parseInt( value, 10 ) || 0 ) ) } ); }
							} )
						)
					),
					el( 'div', blockProps,
						el( 'span', { className: buttonClass, style: previewStyle }, text ),
						el( 'p', { className: 'description' }, mode === 'logout'
							? __( 'На сайте кнопка видна только авторизованным.', 'wp-personal-account' )
							: __( 'На сайте кнопка видна только гостям.', 'wp-personal-account' ) )
					)
				);
			},
			save: function () { return null; }
		} );
	}

	registerButton( 'login', __( 'Кнопка входа', 'wp-personal-account' ), __( 'Войти', 'wp-personal-account' ), 'admin-users' );
	registerButton( 'logout', __( 'Кнопка выхода', 'wp-personal-account' ), __( 'Выйти', 'wp-personal-account' ), 'exit' );
	registerButton( 'register', __( 'Кнопка регистрации', 'wp-personal-account' ), __( 'Регистрация', 'wp-personal-account' ), 'admin-users' );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.components, window.wp.i18n );
