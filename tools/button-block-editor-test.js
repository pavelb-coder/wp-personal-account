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
	console.log( 'OK  editor ' + mode );
}
assert.strictEqual( Object.keys( registered ).length, 3 );
