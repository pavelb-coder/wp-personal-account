/**
 * Редактор блока «Вход и регистрация» (WP Personal Account).
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

	var modeOptions = [
		{ value: 'both', label: __( 'Вход и регистрация', 'wp-personal-account' ) },
		{ value: 'login', label: __( 'Только вход', 'wp-personal-account' ) },
		{ value: 'register', label: __( 'Только регистрация', 'wp-personal-account' ) }
	];

	function edit( props ) {
		var attrs = props.attributes;
		var blockProps = useBlockProps ? useBlockProps() : { className: 'wp-block-wppa-auth' };

		var preview = ServerSideRender
			? el( ServerSideRender, { block: 'wppa/auth', attributes: attrs } )
			: el(
					'div',
					{ className: 'wppa-admin__muted' },
					__( 'Форма входа и регистрации появится на сайте.', 'wp-personal-account' )
			  );

		return el(
			element.Fragment,
			{},
			el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Форма входа', 'wp-personal-account' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Что показывать', 'wp-personal-account' ),
						value: attrs.mode,
						options: modeOptions,
						onChange: function ( value ) {
							props.setAttributes( { mode: value } );
						}
					} ),
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

	blocks.registerBlockType( 'wppa/auth', {
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
