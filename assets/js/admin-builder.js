/**
 * WP Personal Account — конструктор кабинета: перестановка и добавление блоков.
 */
( function () {
	'use strict';

	var l10n = window.wppaBuilder || {};

	/**
	 * HTML-строка новой строки «свой блок».
	 *
	 * @param {string} tab   Слаг вкладки.
	 * @param {number} index Индекс строки.
	 * @return {string}
	 */
	function customRowHtml( tab, index ) {
		var name = 'wppa_layout[' + tab + '][' + index + ']';
		var id = 'wppa-html-' + tab + '-' + index;

		return '' +
			'<div class="wppa-builder__row" data-wppa-row>' +
				'<span class="wppa-builder__handle" aria-hidden="true">☰</span>' +
				'<div class="wppa-builder__main">' +
					'<div class="wppa-builder__head">' +
						'<label class="wppa-builder__toggle">' +
							'<input type="checkbox" name="' + name + '[enabled]" value="1" checked />' +
							'<strong>Свой HTML-блок</strong>' +
						'</label>' +
						'<span class="wppa-badge wppa-badge--owner">HTML</span>' +
					'</div>' +
					'<div class="wppa-builder__fields">' +
						'<input type="text" class="regular-text" name="' + name + '[title]" value="" placeholder="Заголовок блока (необязательно)" />' +
						'<select name="' + name + '[audience]">' +
							'<option value="all">Все пользователи</option>' +
							'<option value="admins">Только администраторы</option>' +
						'</select>' +
					'</div>' +
					'<p class="wppa-builder__html">' +
						'<label for="' + id + '" class="screen-reader-text">HTML-содержимое</label>' +
						'<textarea id="' + id + '" name="' + name + '[html]" rows="5" class="large-text code" placeholder="Текст, ссылки, списки — разрешены базовые HTML-теги"></textarea>' +
					'</p>' +
				'</div>' +
				'<input type="hidden" name="' + name + '[type]" value="custom" />' +
				'<input type="hidden" name="' + name + '[id]" value="custom-' + index + '" />' +
				'<span class="wppa-builder__actions">' +
					'<button type="button" class="button button-small" data-wppa-move="up" aria-label="Выше">↑</button>' +
					'<button type="button" class="button button-small" data-wppa-move="down" aria-label="Ниже">↓</button>' +
					'<button type="button" class="button button-small" data-wppa-remove>Убрать</button>' +
				'</span>' +
			'</div>';
	}

	/**
	 * Перестановка строки вверх или вниз.
	 *
	 * @param {Element} row       Строка.
	 * @param {string}  direction up|down.
	 */
	function moveRow( row, direction ) {
		if ( 'up' === direction && row.previousElementSibling ) {
			row.parentNode.insertBefore( row, row.previousElementSibling );
		} else if ( 'down' === direction && row.nextElementSibling ) {
			row.parentNode.insertBefore( row.nextElementSibling, row );
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;

		if ( ! target || ! target.closest ) {
			return;
		}

		var moveButton = target.closest( '[data-wppa-move]' );

		if ( moveButton ) {
			event.preventDefault();
			moveRow( moveButton.closest( '[data-wppa-row]' ), moveButton.getAttribute( 'data-wppa-move' ) );
			return;
		}

		var removeButton = target.closest( '[data-wppa-remove]' );

		if ( removeButton ) {
			event.preventDefault();

			if ( window.confirm( l10n.confirmRemove || 'Убрать этот блок из вкладки?' ) ) {
				var row = removeButton.closest( '[data-wppa-row]' );

				if ( row ) {
					row.parentNode.removeChild( row );
				}
			}

			return;
		}

		var addButton = target.closest( '[data-wppa-add-custom]' );

		if ( addButton ) {
			event.preventDefault();

			var panel = addButton.closest( '.wppa-builder' );
			var list = panel ? panel.querySelector( '[data-wppa-list]' ) : null;
			var index = parseInt( addButton.getAttribute( 'data-index' ), 10 ) || 0;

			if ( ! list ) {
				return;
			}

			list.insertAdjacentHTML( 'beforeend', customRowHtml( addButton.getAttribute( 'data-tab' ), index ) );
			addButton.setAttribute( 'data-index', String( index + 1 ) );

			var textarea = list.lastElementChild.querySelector( 'textarea' );
			if ( textarea ) {
				textarea.focus();
			}
		}
	} );
} )();
