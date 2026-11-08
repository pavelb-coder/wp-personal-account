/** Блоки кабинета: безопасное статическое представление в редакторе. */
( function ( blocks, element, blockEditor, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	var definitions = {
		account: 'Личный кабинет',
		'section-dashboard': 'Личный кабинет: Обзор',
		'section-profile': 'Личный кабинет: Профиль',
		'section-team': 'Личный кабинет: Команда',
		'section-files': 'Личный кабинет: Файлы',
		'section-messages': 'Личный кабинет: Сообщения',
		'section-participants': 'Личный кабинет: Участники',
		'section-certificates': 'Личный кабинет: Сертификаты',
		'section-mailing': 'Личный кабинет: Рассылка',
		'section-security': 'Личный кабинет: Безопасность'
	};
	Object.keys( definitions ).forEach( function ( id ) {
		var title = __( definitions[ id ], 'wp-personal-account' );
		blocks.registerBlockType( 'wppa/' + id, {
			apiVersion: 2,
			title: title,
			icon: 'id',
			category: 'widgets',
			supports: { html: false, multiple: false },
			edit: function () {
				var props = blockEditor.useBlockProps();
				return el( 'div', props, el( 'strong', null, title ), el( 'p', null, __( 'Содержимое отображается посетителю с учётом его прав и настроек кабинета. Ссылки и формы ведут на страницу кабинета.', 'wp-personal-account' ) ) );
			},
			save: function () { return null; }
		} );
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n ) );
