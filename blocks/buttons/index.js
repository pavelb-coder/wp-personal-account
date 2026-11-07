/** Кнопки входа, выхода и регистрации для редактора блоков WordPress. */
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
	"buttonText": {
		"type": "string",
		"default": ""
	},
	"buttonStyle": {
		"type": "string",
		"default": "primary"
	},
	"buttonSize": {
		"type": "string",
		"default": "md"
	},
	"buttonFullWidth": {
		"type": "boolean",
		"default": false
	},
	"accentColor": {
		"type": "string",
		"default": ""
	},
	"cornerRadius": {
		"type": "number",
		"default": 0
	},
	"buttonBgColor": {
		"type": "string",
		"default": ""
	},
	"buttonTextColor": {
		"type": "string",
		"default": ""
	},
	"buttonBorderColor": {
		"type": "string",
		"default": ""
	},
	"buttonHoverBgColor": {
		"type": "string",
		"default": ""
	},
	"buttonHoverTextColor": {
		"type": "string",
		"default": ""
	},
	"buttonHoverBorderColor": {
		"type": "string",
		"default": ""
	},
	"buttonFontSize": {
		"type": "number",
		"default": 0
	},
	"buttonFontWeight": {
		"type": "string",
		"default": ""
	},
	"buttonBorderWidth": {
		"type": "number",
		"default": -1
	},
	"buttonBorderStyle": {
		"type": "string",
		"default": ""
	},
	"buttonPaddingX": {
		"type": "number",
		"default": -1
	},
	"buttonPaddingY": {
		"type": "number",
		"default": -1
	},
	"buttonRadius": {
		"type": "number",
		"default": -1
	},
	"buttonAlignment": {
		"type": "string",
		"default": ""
	},
	"formUrl": {
		"type": "string",
		"default": ""
	},
	"afterUrl": {
		"type": "string",
		"default": ""
	},
	"openNewTab": {
		"type": "boolean",
		"default": false
	},
	"confirmLogout": {
		"type": "boolean",
		"default": false
	},
	"buttonFontFamily": {
		"type": "string",
		"default": ""
	},
	"buttonItalic": {
		"type": "boolean",
		"default": false
	}
};

	function registerButton( mode, title, defaultText, icon ) {
		blocks.registerBlockType( 'wppa/' + mode + '-button', {
			apiVersion: 2,
			title: title,
			category: 'widgets',
			icon: icon,
			attributes: Object.assign( {}, attributes ),
			edit: function ( props ) {
				var a = props.attributes;
				var blockProps = useBlockProps ? useBlockProps() : { className: 'wp-block-wppa-' + mode + '-button' };
				var text = a.buttonText || defaultText;
				var size = a.buttonSize || 'md';
				var buttonClass = 'components-button ' + ( a.buttonStyle === 'ghost' ? 'is-secondary' : 'is-primary' );
				var previewStyle = {};
				var hex = /^#[0-9a-f]{3}([0-9a-f]{3})?$/i;
				var set = function ( key, value ) { var changes = {}; changes[ key ] = value; props.setAttributes( changes ); };

				function colorControl( key, label ) {
					if ( components.ColorPalette && components.BaseControl ) {
						return el( components.BaseControl, { label: label, help: __( 'Пусто — цвет по умолчанию.', 'wp-personal-account' ) },
							el( components.ColorPalette, { value: a[ key ] || '', onChange: function ( value ) { set( key, value || '' ); } } ) );
					}
					return el( TextControl, { label: label, help: __( 'HEX, например #2563eb. Пусто — по умолчанию.', 'wp-personal-account' ), value: a[ key ] || '', onChange: function ( value ) { set( key, value ); } } );
				}
				function numberControl( key, label, min, max, emptyValue ) {
					var value = typeof a[ key ] === 'number' ? a[ key ] : emptyValue;
					if ( key === 'buttonRadius' && value === emptyValue && a.cornerRadius > 0 ) { value = Math.min( 40, a.cornerRadius ); }
					return el( TextControl, {
						label: label, type: 'number', min: min, max: max,
						help: __( 'Пусто — по умолчанию.', 'wp-personal-account' ),
						value: value === emptyValue ? '' : value,
						onChange: function ( next ) {
							var number = parseInt( next, 10 );
							var result = next === '' || isNaN( number ) ? emptyValue : Math.max( 0, Math.min( max, number ) );
							if ( key === 'buttonRadius' ) { props.setAttributes( { buttonRadius: result, cornerRadius: 0 } ); }
							else { set( key, result ); }
						},
						onBlur: function ( event ) {
							var value = parseInt( event.target.value, 10 );
							if ( ! isNaN( value ) && value < min ) { set( key, min ); }
						}
					} );
				}
				function selectControl( key, label, values ) {
					return el( SelectControl, { label: label, value: a[ key ] || '', options: values, onChange: function ( value ) { set( key, value ); } } );
				}

				if ( size === 'sm' || size === 'lg' ) {
					buttonClass += ' wppa-button--' + size;
					previewStyle.minHeight = size === 'lg' ? '50px' : '34px';
					previewStyle.padding = size === 'lg' ? '13px 22px' : '6px 14px';
				}
				if ( a.buttonFullWidth ) { buttonClass += ' wppa-button--block'; previewStyle.width = '100%'; }
				if ( hex.test( a.accentColor || '' ) && a.buttonStyle !== 'ghost' ) { previewStyle.backgroundColor = a.accentColor; }
				if ( hex.test( a.buttonBgColor || '' ) ) { previewStyle.backgroundColor = a.buttonBgColor; }
				if ( hex.test( a.buttonTextColor || '' ) ) { previewStyle.color = a.buttonTextColor; }
				if ( hex.test( a.buttonBorderColor || '' ) ) { previewStyle.borderColor = a.buttonBorderColor; previewStyle.borderStyle = 'solid'; previewStyle.borderWidth = '1px'; }
				if ( a.buttonFontSize >= 8 ) { previewStyle.fontSize = Math.min( 72, a.buttonFontSize ) + 'px'; }
				if ( /^(400|500|600|700|800|900)$/.test( a.buttonFontWeight || '' ) ) { previewStyle.fontWeight = a.buttonFontWeight; }
				if ( /^(system|sans-serif|serif|monospace)$/.test( a.buttonFontFamily || '' ) ) { previewStyle.fontFamily = a.buttonFontFamily === 'system' ? 'system-ui, sans-serif' : a.buttonFontFamily; }
				if ( a.buttonItalic ) { previewStyle.fontStyle = 'italic'; }
				if ( typeof a.buttonBorderWidth === 'number' && a.buttonBorderWidth >= 0 ) { previewStyle.borderWidth = Math.min( 20, a.buttonBorderWidth ) + 'px'; }
				if ( /^(solid|dashed|dotted|double|none)$/.test( a.buttonBorderStyle || '' ) ) { previewStyle.borderStyle = a.buttonBorderStyle; }
				if ( typeof a.buttonPaddingX === 'number' && a.buttonPaddingX >= 0 ) { previewStyle.paddingLeft = previewStyle.paddingRight = Math.min( 100, a.buttonPaddingX ) + 'px'; }
				if ( typeof a.buttonPaddingY === 'number' && a.buttonPaddingY >= 0 ) { previewStyle.paddingTop = previewStyle.paddingBottom = Math.min( 100, a.buttonPaddingY ) + 'px'; }
				if ( typeof a.buttonRadius === 'number' && a.buttonRadius >= 0 ) { previewStyle.borderRadius = Math.min( 100, a.buttonRadius ) + 'px'; }
				else if ( a.cornerRadius > 0 ) { previewStyle.borderRadius = Math.min( 40, a.cornerRadius ) + 'px'; }
				if ( /^(left|center|right)$/.test( a.buttonAlignment || '' ) ) { blockProps.style = Object.assign( {}, blockProps.style || {}, { textAlign: a.buttonAlignment } ); }

				function previewHover( event, hover ) {
					[ [ 'backgroundColor', a.buttonHoverBgColor ], [ 'color', a.buttonHoverTextColor ], [ 'borderColor', a.buttonHoverBorderColor ] ].forEach( function ( item ) {
						if ( hex.test( item[ 1 ] || '' ) ) { event.currentTarget.style[ item[ 0 ] ] = hover ? item[ 1 ] : ( previewStyle[ item[ 0 ] ] || '' ); }
					} );
				}

				var defaultOption = { label: __( 'По умолчанию', 'wp-personal-account' ), value: '' };
				return el( element.Fragment, {},
					el( InspectorControls, {},
						el( PanelBody, { title: __( 'Кнопка', 'wp-personal-account' ), initialOpen: true },
							el( TextControl, { label: __( 'Текст кнопки', 'wp-personal-account' ), value: a.buttonText || '', placeholder: defaultText, onChange: function ( value ) { set( 'buttonText', value ); } } ),
							selectControl( 'buttonStyle', __( 'Стиль', 'wp-personal-account' ), [ { label: __( 'Основная', 'wp-personal-account' ), value: 'primary' }, { label: __( 'С обводкой', 'wp-personal-account' ), value: 'ghost' } ] ),
							selectControl( 'buttonSize', __( 'Размер', 'wp-personal-account' ), [ { label: __( 'Компактный', 'wp-personal-account' ), value: 'sm' }, { label: __( 'Обычный', 'wp-personal-account' ), value: 'md' }, { label: __( 'Крупный', 'wp-personal-account' ), value: 'lg' } ] ),
							selectControl( 'buttonAlignment', __( 'Выравнивание', 'wp-personal-account' ), [ defaultOption, { label: __( 'Слева', 'wp-personal-account' ), value: 'left' }, { label: __( 'По центру', 'wp-personal-account' ), value: 'center' }, { label: __( 'Справа', 'wp-personal-account' ), value: 'right' } ] ),
							el( ToggleControl, { label: __( 'На всю ширину', 'wp-personal-account' ), checked: !! a.buttonFullWidth, onChange: function ( value ) { set( 'buttonFullWidth', value ); } } )
						),
						el( PanelBody, { title: __( 'Цвета кнопки', 'wp-personal-account' ), initialOpen: false },
							colorControl( 'accentColor', __( 'Акцентный цвет', 'wp-personal-account' ) ),
							colorControl( 'buttonBgColor', __( 'Цвет фона', 'wp-personal-account' ) ),
							colorControl( 'buttonTextColor', __( 'Цвет текста', 'wp-personal-account' ) ),
							colorControl( 'buttonBorderColor', __( 'Цвет рамки', 'wp-personal-account' ) ),
							colorControl( 'buttonHoverBgColor', __( 'Фон при наведении', 'wp-personal-account' ) ),
							colorControl( 'buttonHoverTextColor', __( 'Текст при наведении', 'wp-personal-account' ) ),
							colorControl( 'buttonHoverBorderColor', __( 'Рамка при наведении', 'wp-personal-account' ) )
						),
						el( PanelBody, { title: __( 'Шрифт и размеры', 'wp-personal-account' ), initialOpen: false },
							numberControl( 'buttonFontSize', __( 'Размер шрифта, px', 'wp-personal-account' ), 8, 72, 0 ),
							selectControl( 'buttonFontFamily', __( 'Шрифт', 'wp-personal-account' ), [ defaultOption, { label: __( 'Системный', 'wp-personal-account' ), value: 'system' }, { label: __( 'Без засечек', 'wp-personal-account' ), value: 'sans-serif' }, { label: __( 'С засечками', 'wp-personal-account' ), value: 'serif' }, { label: __( 'Моноширинный', 'wp-personal-account' ), value: 'monospace' } ] ),
							selectControl( 'buttonFontWeight', __( 'Насыщенность шрифта', 'wp-personal-account' ), [ defaultOption ].concat( [ '400', '500', '600', '700', '800', '900' ].map( function ( v ) { return { label: v, value: v }; } ) ) ),
							el( ToggleControl, { label: __( 'Курсив', 'wp-personal-account' ), checked: !! a.buttonItalic, onChange: function ( value ) { set( 'buttonItalic', value ); } } ),
							numberControl( 'buttonPaddingX', __( 'Отступ слева и справа, px', 'wp-personal-account' ), 0, 100, -1 ),
							numberControl( 'buttonPaddingY', __( 'Отступ сверху и снизу, px', 'wp-personal-account' ), 0, 100, -1 ),
							numberControl( 'buttonBorderWidth', __( 'Толщина рамки, px', 'wp-personal-account' ), 0, 20, -1 ),
							selectControl( 'buttonBorderStyle', __( 'Стиль рамки', 'wp-personal-account' ), [ defaultOption, { label: __( 'Сплошная', 'wp-personal-account' ), value: 'solid' }, { label: __( 'Пунктир', 'wp-personal-account' ), value: 'dashed' }, { label: __( 'Точки', 'wp-personal-account' ), value: 'dotted' }, { label: __( 'Двойная', 'wp-personal-account' ), value: 'double' }, { label: __( 'Нет', 'wp-personal-account' ), value: 'none' } ] ),
							numberControl( 'buttonRadius', __( 'Скругление, px', 'wp-personal-account' ), 0, 100, -1 )
						),
						el( PanelBody, { title: __( 'Переходы', 'wp-personal-account' ), initialOpen: false },
							mode !== 'logout' ? el( TextControl, { label: __( 'Адрес страницы с формой', 'wp-personal-account' ), help: __( 'Только адрес этого сайта со встроенной формой плагина. Пусто — страница кабинета.', 'wp-personal-account' ), value: a.formUrl || '', onChange: function ( value ) { set( 'formUrl', value ); } } ) : null,
							el( TextControl, { label: mode === 'logout' ? __( 'Адрес после выхода', 'wp-personal-account' ) : ( mode === 'register' ? __( 'Адрес после регистрации', 'wp-personal-account' ) : __( 'Адрес после входа', 'wp-personal-account' ) ), help: __( 'Только адрес этого сайта. Пусто — стандартное поведение.', 'wp-personal-account' ), value: a.afterUrl || '', onChange: function ( value ) { set( 'afterUrl', value ); } } ),
							el( ToggleControl, { label: __( 'Открывать в новой вкладке', 'wp-personal-account' ), checked: !! a.openNewTab, onChange: function ( value ) { set( 'openNewTab', value ); } } ),
							mode === 'logout' ? el( ToggleControl, { label: __( 'Подтверждать выход', 'wp-personal-account' ), checked: !! a.confirmLogout, onChange: function ( value ) { set( 'confirmLogout', value ); } } ) : null
						)
					),
					el( 'div', blockProps,
						el( 'span', { className: buttonClass, style: previewStyle, onMouseEnter: function ( event ) { previewHover( event, true ); }, onMouseLeave: function ( event ) { previewHover( event, false ); } }, text ),
						el( 'p', { className: 'description' }, mode === 'logout' ? __( 'На сайте кнопка видна только авторизованным.', 'wp-personal-account' ) : __( 'На сайте кнопка видна только гостям.', 'wp-personal-account' ) )
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
