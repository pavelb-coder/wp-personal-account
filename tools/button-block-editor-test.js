/** Проверка регистрации и настроек трёх блоков-кнопок без сборки Gutenberg. */
'use strict';
const fs = require( 'fs' );
const vm = require( 'vm' );
const assert = require( 'assert' );
const registered = {};
const element = {
	Fragment: 'Fragment',
	createElement: ( type, props, ...children ) => ( { type, props: props || {}, children } )
};
const wp = {
	blocks: { registerBlockType: ( name, settings ) => { registered[ name ] = settings; } },
	blockEditor: { InspectorControls: 'InspectorControls', useBlockProps: () => ( { className: 'editor-block' } ) },
	element,
	components: { PanelBody: 'PanelBody', TextControl: 'TextControl', SelectControl: 'SelectControl', ToggleControl: 'ToggleControl' },
	i18n: { __: text => text }
};
vm.runInNewContext( fs.readFileSync( __dirname + '/../blocks/buttons/index.js', 'utf8' ), { window: { wp }, Math, parseInt } );
function flatten( node ) {
	if ( ! node || typeof node !== 'object' ) { return []; }
	return [ node ].concat( ...( node.children || [] ).map( flatten ) );
}
for ( const [ mode, expected ] of [ [ 'login', 'Войти' ], [ 'logout', 'Выйти' ], [ 'register', 'Регистрация' ] ] ) {
	const settings = registered[ 'wppa/' + mode + '-button' ];
	assert( settings, mode + ': блок зарегистрирован' );
	assert.strictEqual( settings.apiVersion, 2 );
	assert.strictEqual( settings.save(), null );
	const metadata = JSON.parse( fs.readFileSync( __dirname + '/../blocks/' + mode + '-button/block.json', 'utf8' ) );
	assert.strictEqual( JSON.stringify( settings.attributes ), JSON.stringify( metadata.attributes ), mode + ': metadata matches editor' );
	const changes = [];
	const props = { attributes: { buttonText: '', buttonStyle: 'primary', buttonSize: 'md', buttonFullWidth: false, accentColor: '', cornerRadius: 0 }, setAttributes: value => changes.push( value ) };
	const nodes = flatten( settings.edit( props ) );
	const preview = nodes.find( n => n.type === 'span' );
	assert.strictEqual( preview.children[ 0 ], expected );
	const field = nodes.find( n => n.type === 'TextControl' && n.props.label === 'Текст кнопки' );
	field.props.onChange( 'Моя кнопка' );
	assert.strictEqual( changes[ 0 ].buttonText, 'Моя кнопка' );
	props.attributes = { buttonText: 'Моя кнопка', buttonStyle: 'primary', buttonSize: 'lg', buttonFullWidth: true, accentColor: '#123456', cornerRadius: 99 };
	const styled = flatten( settings.edit( props ) ).find( n => n.type === 'span' );
	assert.strictEqual( styled.children[ 0 ], 'Моя кнопка' );
	assert.strictEqual( styled.props.style.backgroundColor, '#123456' );
	assert.strictEqual( styled.props.style.borderRadius, '40px' );
	assert.strictEqual( styled.props.style.width, '100%' );
	assert( styled.props.className.includes( 'wppa-button--lg' ) );
	props.attributes = Object.assign( {}, props.attributes, { buttonBgColor: '#112233', buttonTextColor: '#abcdef', buttonBorderColor: '#998877', buttonHoverBgColor: '#223344', buttonHoverTextColor: '#445566', buttonHoverBorderColor: '#778899', buttonFontSize: 20, buttonFontFamily: 'serif', buttonFontWeight: '700', buttonItalic: true, buttonBorderWidth: 2, buttonBorderStyle: 'dashed', buttonPaddingX: 21, buttonPaddingY: 0, buttonRadius: 0, buttonAlignment: 'center' } );
	const detailed = flatten( settings.edit( props ) );
	const detailedPreview = detailed.find( n => n.type === 'span' );
	assert.strictEqual( detailedPreview.props.style.backgroundColor, '#112233' );
	assert.strictEqual( detailedPreview.props.style.color, '#abcdef' );
	assert.strictEqual( detailedPreview.props.style.borderColor, '#998877' );
	assert.strictEqual( detailedPreview.props.style.fontSize, '20px' );
	assert.strictEqual( detailedPreview.props.style.fontFamily, 'serif' );
	assert.strictEqual( detailedPreview.props.style.fontWeight, '700' );
	assert.strictEqual( detailedPreview.props.style.fontStyle, 'italic' );
	assert.strictEqual( detailedPreview.props.style.borderWidth, '2px' );
	assert.strictEqual( detailedPreview.props.style.borderStyle, 'dashed' );
	assert.strictEqual( detailedPreview.props.style.paddingLeft, '21px' );
	assert.strictEqual( detailedPreview.props.style.paddingTop, '0px' );
	assert.strictEqual( detailedPreview.props.style.borderRadius, '0px' );
	assert.strictEqual( detailed.find( n => n.type === 'div' && n.props.className === 'editor-block' ).props.style.textAlign, 'center' );
	const fakeEvent = { currentTarget: { style: {} } };
	detailedPreview.props.onMouseEnter( fakeEvent );
	assert.strictEqual( fakeEvent.currentTarget.style.backgroundColor, '#223344' );
	assert.strictEqual( fakeEvent.currentTarget.style.color, '#445566' );
	assert.strictEqual( fakeEvent.currentTarget.style.borderColor, '#778899' );
	detailedPreview.props.onMouseLeave( fakeEvent );
	assert.strictEqual( fakeEvent.currentTarget.style.backgroundColor, '#112233' );
	assert.strictEqual( fakeEvent.currentTarget.style.color, '#abcdef' );
	assert.strictEqual( fakeEvent.currentTarget.style.borderColor, '#998877' );
	assert( detailed.some( n => n.props.label === 'Адрес после ' + ( mode === 'login' ? 'входа' : mode === 'register' ? 'регистрации' : 'выхода' ) ) );
	assert.strictEqual( detailed.some( n => n.props.label === 'Адрес страницы с формой' ), mode !== 'logout' );
	assert.strictEqual( detailed.some( n => n.props.label === 'Подтверждать выход' ), mode === 'logout' );
	const radius = detailed.find( n => n.props.label === 'Скругление, px' );
	radius.props.onChange( '' );
	assert.strictEqual( changes[ changes.length - 1 ].buttonRadius, -1 );
	assert.strictEqual( changes[ changes.length - 1 ].cornerRadius, 0 );
	props.attributes.buttonRadius = -1;
	props.attributes.cornerRadius = 20;
	assert.strictEqual( flatten( settings.edit( props ) ).find( n => n.props.label === 'Скругление, px' ).props.value, 20 );
	wp.components.ColorPalette = 'ColorPalette';
	wp.components.BaseControl = 'BaseControl';
	const paletteNodes = flatten( settings.edit( props ) );
	assert.strictEqual( paletteNodes.filter( n => n.type === 'ColorPalette' ).length, 7 );
	paletteNodes.find( n => n.type === 'ColorPalette' ).props.onChange( '#aabbcc' );
	assert.strictEqual( changes[ changes.length - 1 ].accentColor, '#aabbcc' );
	delete wp.components.ColorPalette;
	delete wp.components.BaseControl;

	console.log( 'OK  editor ' + mode );
}
assert.strictEqual( Object.keys( registered ).length, 3 );
