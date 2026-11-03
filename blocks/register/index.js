/**
 * Редактор блока «Регистрация (личный кабинет)» — WP Personal Account.
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

	var BLOCK = 'wppa/register';

	function edit( props ) {
		var attrs = props.attributes;
		var blockProps = useBlockProps ? useBlockProps() : { className: 'wp-block-wppa-register' };
		var isButton = 'button' === attrs.display;

		var preview = ServerSideRender
			? el( ServerSideRender, { block: BLOCK, attributes: attrs } )
			: el(
					'div',
					{ className: 'wppa-admin__muted' },
					__( 'Форма регистрации появится на сайте.', 'wp-personal-account' )
			  );

		return el(
			element.Fragment,
			{},
			el(
				InspectorControls,
				{},
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
								label: __( 'Скрыть кнопку «Войти» под формой', 'wp-personal-account' ),
								checked: !! attrs.hideOther,
								onChange: function ( value ) {
									props.setAttributes( { hideOther: value } );
								}
						  } )
				),
				el(
					PanelBody,
					{ title: __( 'Форма регистрации', 'wp-personal-account' ), initialOpen: false },
					el( ToggleControl, {
						label: __( 'Скрывать от авторизованных', 'wp-personal-account' ),
						checked: !! attrs.hideForMembers,
						onChange: function ( value ) {
							props.setAttributes( { hideForMembers: value } );
						}
					} ),
					el( TextControl, {
						label: __( 'Редирект после регистрации', 'wp-personal-account' ),
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
