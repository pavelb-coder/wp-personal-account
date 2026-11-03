/**
 * Редактор блока «Вход (личный кабинет)» — WP Personal Account.
 * Без сборки: используется wp.element.createElement.
 */
( function ( blocks, blockEditor, element, components, serverSideRender, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;
	var ServerSideRender = serverSideRender ? serverSideRender.default : null;

	var BLOCK = 'wppa/login';

	/**
	 * Панель «Внешний вид» для всех блоков плагина.
	 *
	 * @param {Object} props Свойства блока.
	 * @return {Object} Элемент панели.
	 */
	function appearancePanel( props ) {
		var attrs = props.attributes;
		var ColorPalette = components.ColorPalette;

		return el(
			PanelBody,
			{ title: __( 'Внешний вид', 'wp-personal-account' ), initialOpen: true },
			el( SelectControl, {
				label: __( 'Оформление', 'wp-personal-account' ),
				help: __( 'Карточка с тенью, только рамка или без оформления (наследует стили темы).', 'wp-personal-account' ),
				value: attrs.appearance || 'plain',
				options: [
					{ value: 'plain', label: __( 'Без оформления', 'wp-personal-account' ) },
					{ value: 'card', label: __( 'Карточка', 'wp-personal-account' ) },
					{ value: 'bordered', label: __( 'Рамка', 'wp-personal-account' ) }
				],
				onChange: function ( value ) {
					props.setAttributes( { appearance: value } );
				}
			} ),
			el( SelectControl, {
				label: __( 'Ширина', 'wp-personal-account' ),
				value: attrs.maxWidth || 'auto',
				options: [
					{ value: 'auto', label: __( 'По содержимому', 'wp-personal-account' ) },
					{ value: 'small', label: __( 'Узкая — 320 px', 'wp-personal-account' ) },
					{ value: 'medium', label: __( 'Средняя — 420 px', 'wp-personal-account' ) },
					{ value: 'large', label: __( 'Широкая — 560 px', 'wp-personal-account' ) },
					{ value: 'full', label: __( 'На всю ширину', 'wp-personal-account' ) }
				],
				onChange: function ( value ) {
					props.setAttributes( { maxWidth: value } );
				}
			} ),
			el( SelectControl, {
				label: __( 'Размер кнопок', 'wp-personal-account' ),
				value: attrs.buttonSize || 'md',
				options: [
					{ value: 'sm', label: __( 'Компактный', 'wp-personal-account' ) },
					{ value: 'md', label: __( 'Обычный', 'wp-personal-account' ) },
					{ value: 'lg', label: __( 'Крупный', 'wp-personal-account' ) }
				],
				onChange: function ( value ) {
					props.setAttributes( { buttonSize: value } );
				}
			} ),
			el( ToggleControl, {
				label: __( 'Кнопки на всю ширину', 'wp-personal-account' ),
				checked: !! attrs.buttonFullWidth,
				onChange: function ( value ) {
					props.setAttributes( { buttonFullWidth: value } );
				}
			} ),
			'button' === attrs.display
				? el( SelectControl, {
						label: __( 'Стиль кнопки', 'wp-personal-account' ),
						value: attrs.buttonStyle || 'primary',
						options: [
							{ value: 'primary', label: __( 'Основная (заливка)', 'wp-personal-account' ) },
							{ value: 'ghost', label: __( 'Вторичная (обводка)', 'wp-personal-account' ) }
						],
						onChange: function ( value ) {
							props.setAttributes( { buttonStyle: value } );
						}
				  } )
				: null,
			ColorPalette
				? el( components.BaseControl, {
						label: __( 'Акцентный цвет', 'wp-personal-account' ),
						help: __( 'Пусто — цвет из настроек плагина.', 'wp-personal-account' )
				  },
					el( ColorPalette, {
						value: attrs.accentColor,
						onChange: function ( value ) {
							props.setAttributes( { accentColor: value || '' } );
						}
					} )
				  )
				: el( TextControl, {
						label: __( 'Акцентный цвет', 'wp-personal-account' ),
						help: __( 'HEX, например #2563eb. Пусто — из настроек плагина.', 'wp-personal-account' ),
						value: attrs.accentColor,
						onChange: function ( value ) {
							props.setAttributes( { accentColor: value } );
						}
				  } ),
			el( TextControl, {
				label: __( 'Радиус скругления, px', 'wp-personal-account' ),
				help: __( '0 — значение по умолчанию.', 'wp-personal-account' ),
				type: 'number',
				value: attrs.cornerRadius,
				onChange: function ( value ) {
					props.setAttributes( { cornerRadius: parseInt( value, 10 ) || 0 } );
				}
			} )
		);
	}

	function edit( props ) {
		var attrs = props.attributes;
		var blockProps = useBlockProps ? useBlockProps() : { className: 'wp-block-wppa-login' };
		var isButton = 'button' === attrs.display;

		var preview = ServerSideRender
			? el( ServerSideRender, { block: BLOCK, attributes: attrs } )
			: el(
					'div',
					{ className: 'wppa-admin__muted' },
					__( 'Форма входа появится на сайте.', 'wp-personal-account' )
			  );

		return el(
			element.Fragment,
			{},
			el(
				InspectorControls,
				{},
				appearancePanel( props ),
				el(
					PanelBody,
					{ title: __( 'Вид блока', 'wp-personal-account' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Что выводить', 'wp-personal-account' ),
						value: attrs.display,
						options: [
							{ value: 'form', label: __( 'Форма входа', 'wp-personal-account' ) },
							{ value: 'button', label: __( 'Кнопка «Войти» (ссылка)', 'wp-personal-account' ) }
						],
						onChange: function ( value ) {
							props.setAttributes( { display: value } );
						}
					} ),
					isButton
						? el( TextControl, {
								label: __( 'Текст кнопки', 'wp-personal-account' ),
								value: attrs.buttonText,
								onChange: function ( value ) {
									props.setAttributes( { buttonText: value } );
								}
						  } )
						: el( ToggleControl, {
								label: __( 'Скрыть кнопку «Регистрация» под формой', 'wp-personal-account' ),
								checked: !! attrs.hideOther,
								onChange: function ( value ) {
									props.setAttributes( { hideOther: value } );
								}
						  } )
				),
				el(
					PanelBody,
					{ title: __( 'Форма входа', 'wp-personal-account' ), initialOpen: false },
					el( ToggleControl, {
						label: __( 'Скрывать от авторизованных', 'wp-personal-account' ),
						checked: !! attrs.hideForMembers,
						onChange: function ( value ) {
							props.setAttributes( { hideForMembers: value } );
						}
					} ),
					el( TextControl, {
						label: __( 'Редирект после входа', 'wp-personal-account' ),
						help: __( 'Пусто — в личный кабинет.', 'wp-personal-account' ),
						value: attrs.redirect,
						onChange: function ( value ) {
							props.setAttributes( { redirect: value } );
						}
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Заголовок', 'wp-personal-account' ), initialOpen: false },
					el( ToggleControl, {
						label: __( 'Показывать заголовок', 'wp-personal-account' ),
						checked: !! attrs.showTitle,
						onChange: function ( value ) {
							props.setAttributes( { showTitle: value } );
						}
					} ),
					attrs.showTitle
						? el( TextControl, {
								label: __( 'Текст заголовка', 'wp-personal-account' ),
								value: attrs.title,
								onChange: function ( value ) {
									props.setAttributes( { title: value } );
								}
						  } )
						: null
				)
			),
			el( 'div', blockProps, preview )
		);
	}

	blocks.registerBlockType( BLOCK, {
		edit: edit,
		save: function () {
			return null;
		}
	} );
} )(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.element,
	window.wp.components,
	window.wp.serverSideRender,
	window.wp.i18n
);
