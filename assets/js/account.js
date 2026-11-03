/**
 * WP Personal Account — скрипты кабинета.
 */
( function () {
	'use strict';

	var l10n = window.wppaL10n || {};

	/**
	 * Показать/скрыть пароль.
	 */
	function initPasswordToggles() {
		var buttons = document.querySelectorAll( '[data-wppa-toggle]' );

		Array.prototype.forEach.call( buttons, function ( button ) {
			var input = document.getElementById( button.getAttribute( 'data-wppa-toggle' ) );

			if ( ! input ) {
				return;
			}

			button.addEventListener( 'click', function () {
				var isPassword = input.getAttribute( 'type' ) === 'password';

				input.setAttribute( 'type', isPassword ? 'text' : 'password' );
				button.textContent = isPassword ? '🙈' : '👁';
				button.setAttribute(
					'aria-label',
					isPassword ? ( l10n.toggleHide || 'Скрыть пароль' ) : ( l10n.toggleShow || 'Показать пароль' )
				);
			} );
		} );
	}

	/**
	 * Проверка совпадения паролей в формах регистрации и смены пароля.
	 */
	function initPasswordMatch() {
		var forms = document.querySelectorAll( '.wppa-form' );

		Array.prototype.forEach.call( forms, function ( form ) {
			var first = form.querySelector( '[name="password"], [name="new_password"]' );
			var second = form.querySelector( '[name="password_confirm"], [name="repeat_password"]' );

			if ( ! first || ! second ) {
				return;
			}

			function check() {
				if ( second.value && first.value !== second.value ) {
					second.setCustomValidity( l10n.passwordMismatch || 'Пароли не совпадают.' );
				} else {
					second.setCustomValidity( '' );
				}
			}

			first.addEventListener( 'input', check );
			second.addEventListener( 'input', check );
		} );
	}

	/**
	 * Подтверждение выхода.
	 */
	function initLogoutConfirm() {
		var links = document.querySelectorAll( '[data-wppa-confirm-logout]' );

		Array.prototype.forEach.call( links, function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				if ( ! window.confirm( l10n.confirmLogout || 'Выйти из аккаунта?' ) ) {
					event.preventDefault();
				}
			} );
		} );
	}

	/**
	 * Активная панель внутри виджета.
	 *
	 * @param {Element} root Корень виджета.
	 * @param {string}  name login|register.
	 */
	function activatePanel( root, name ) {
		Array.prototype.forEach.call( root.querySelectorAll( '[data-wppa-panel-btn]' ), function ( item ) {
			var active = item.getAttribute( 'data-wppa-panel-btn' ) === name;
			item.classList.toggle( 'is-active', active );
			item.setAttribute( 'aria-selected', active ? 'true' : 'false' );
		} );

		Array.prototype.forEach.call( root.querySelectorAll( '[data-wppa-panel]' ), function ( panel ) {
			panel.classList.toggle( 'is-hidden', panel.getAttribute( 'data-wppa-panel' ) !== name );
		} );
	}

	/**
	 * Переключение панелей «Вход / Регистрация» в виджете.
	 *
	 * Учитывает ?wppa_view=register (переход по кнопке «Регистрация»)
	 * и перехватывает такие ссылки, если они ведут на текущую страницу.
	 */
	function initWidgetPanels() {
		var buttons = document.querySelectorAll( '[data-wppa-panel-btn]' );

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.addEventListener( 'click', function () {
				var name = button.getAttribute( 'data-wppa-panel-btn' );
				var root = button.closest( '.wppa--widget' );

				if ( ! root || ! name ) {
					return;
				}

				activatePanel( root, name );
			} );
		} );

		// 1. Панель по параметру адреса.
		var requested = getRequestedView();

		if ( requested ) {
			Array.prototype.forEach.call( document.querySelectorAll( '.wppa--widget' ), function ( root ) {
				if ( root.querySelector( '[data-wppa-panel="' + requested + '"]' ) ) {
					activatePanel( root, requested );
				}
			} );
		}

		// 2. Клик по ссылке ?wppa_view=… на текущей странице — без перезагрузки.
		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest ? event.target.closest( 'a[href]' ) : null;

			if ( ! link || event.metaKey || event.ctrlKey || event.shiftKey || event.button ) {
				return;
			}

			var url;

			try {
				url = new URL( link.href, window.location.href );
			} catch ( error ) {
				return;
			}

			var view = url.searchParams.get( 'wppa_view' );

			if ( ! view || url.pathname !== window.location.pathname || url.search.indexOf( 'wppa_view' ) === -1 ) {
				return;
			}

			var widget = link.closest( '.wppa--widget' ) || document.querySelector( '.wppa--widget' );
			var panel  = widget ? widget.querySelector( '[data-wppa-panel="' + view + '"]' ) : null;

			if ( ! panel ) {
				return;
			}

			event.preventDefault();
			activatePanel( widget, view );

			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( {}, '', url.toString() );
			}

			var focus = panel.querySelector( 'input:not([type="hidden"]), textarea' );

			if ( focus ) {
				focus.focus();
			}
		} );
	}

	/**
	 * Какой экран запрошен параметром ?wppa_view.
	 *
	 * @return {string} login|register|''.
	 */
	function getRequestedView() {
		var view = '';

		try {
			view = new URL( window.location.href ).searchParams.get( 'wppa_view' ) || '';
		} catch ( error ) {
			return '';
		}

		return view === 'register' || view === 'login' ? view : '';
	}

	/**
	 * Живая проверка пароля по требованиям из формы.
	 *
	 * Список требований выводит PHP (WPPA_Password::render_requirements),
	 * здесь он только подсвечивается по мере ввода.
	 */
	function initPasswordRules() {
		var lists = document.querySelectorAll( '[data-wppa-password-rules]' );

		Array.prototype.forEach.call( lists, function ( list ) {
			var input = document.getElementById( list.getAttribute( 'data-for' ) || '' );

			if ( ! input ) {
				return;
			}

			var items = list.querySelectorAll( '.wppa-password-rules__item' );
			var email = document.getElementById( 'wppa-email' );

			function params( item ) {
				try {
					return JSON.parse( item.getAttribute( 'data-params' ) || '{}' ) || {};
				} catch ( error ) {
					return {};
				}
			}

			function check( rule, value, options ) {
				var letters;

				switch ( rule ) {
					case 'length':
						return value.length >= ( options.min || 16 );

					case 'uppercase':
						letters = value.replace( /[^A-Za-zА-Яа-яЁё]/g, '' );
						return letters !== letters.toLowerCase() && /[A-ZА-ЯЁ]/.test( value );

					case 'lowercase':
						return /[a-zа-яё]/.test( value );

					case 'digit':
						return /\d/.test( value );

					case 'special':
						return /[^A-Za-zА-Яа-яЁё0-9]/.test( value );

					case 'variety':
						return new Set( value.split( '' ) ).size >= ( options.unique || 8 );

					case 'no_repeat':
						return ! /(.)\1{2,}/.test( value );

					case 'no_sequence':
						return hasNoSequence( value );

					case 'not_common':
						return isNotCommon( value );

					case 'not_personal':
						return isNotPersonal( value, email ? email.value : '' );

					default:
						return null;
				}
			}

			function hasNoSequence( value ) {
				var lower = value.toLowerCase();

				if ( /(qwer|wert|erty|rtyu|tyui|yuio|uiop|asdf|sdfg|dfgh|fghj|ghjk|hjkl|zxcv|xcvb|cvbn|vbnm)/.test( lower ) ) {
					return false;
				}

				for ( var i = 0; i + 3 < value.length; i++ ) {
					var a = value.charCodeAt( i );
					var b = value.charCodeAt( i + 1 );
					var c = value.charCodeAt( i + 2 );
					var d = value.charCodeAt( i + 3 );

					if ( ( b === a + 1 && c === b + 1 && d === c + 1 ) || ( b === a - 1 && c === b - 1 && d === c - 1 ) ) {
						return false;
					}
				}

				return true;
			}

			function isNotCommon( value ) {
				var words = [ 'password', 'qwerty', '123456', '12345678', '1234567890', '111111', 'admin', 'letmein', 'welcome', 'iloveyou', 'monkey', 'dragon', 'football', 'sunshine', 'princess', 'abc123', 'пароль', 'йцукен', 'привет', 'любовь' ];
				var lower = value.toLowerCase();

				return ! words.some( function ( word ) {
					return lower === word || ( word.length >= 5 && lower.indexOf( word ) !== -1 );
				} );
			}

			function isNotPersonal( value, emailValue ) {
				var lower = value.toLowerCase();
				var parts = [];

				if ( emailValue ) {
					parts.push( emailValue );
					parts.push( emailValue.split( '@' )[ 0 ] );
				}

				return ! parts.some( function ( part ) {
					part = ( part || '' ).trim().toLowerCase();
					return part.length >= 3 && lower.indexOf( part ) !== -1;
				} );
			}

			function update() {
				var value = input.value || '';

				Array.prototype.forEach.call( items, function ( item ) {
					var result = check( item.getAttribute( 'data-rule' ), value, params( item ) );

					if ( null === result ) {
						return;
					}

					item.classList.toggle( 'is-ok', result && value.length > 0 );
					item.classList.toggle( 'is-bad', ! result && value.length > 0 );
				} );
			}

			input.addEventListener( 'input', update );

			if ( email ) {
				email.addEventListener( 'input', update );
			}

			update();
		} );
	}

	/**
	 * Копирование в буфер обмена.
	 */
	function initCopyButtons() {
		var buttons = document.querySelectorAll( '[data-wppa-copy]' );

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.addEventListener( 'click', function () {
				var input = document.getElementById( button.getAttribute( 'data-wppa-copy' ) );

				if ( ! input ) {
					return;
				}

				var original = button.textContent;

				function done( ok ) {
					button.textContent = ok ? ( l10n.copied || 'Скопировано' ) : ( l10n.copyFailed || 'Не удалось' );
					window.setTimeout( function () {
						button.textContent = original;
					}, 1600 );
				}

				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( input.value ).then( function () {
						done( true );
					} ).catch( function () {
						input.select();
						done( document.execCommand( 'copy' ) );
					} );
					return;
				}

				input.select();
				done( document.execCommand( 'copy' ) );
			} );
		} );
	}

	/**
	 * Подтверждение потенциально опасных действий.
	 */
	function initConfirmActions() {
		var items = document.querySelectorAll( '[data-wppa-confirm]' );

		Array.prototype.forEach.call( items, function ( item ) {
			item.addEventListener( 'click', function ( event ) {
				var message = item.getAttribute( 'data-wppa-confirm' );

				if ( message && ! window.confirm( message ) ) {
					event.preventDefault();
				}
			} );
		} );
	}

	/**
	 * Отключение повторной отправки формы.
	 */
	function initSubmitGuard() {
		var forms = document.querySelectorAll( '.wppa-form' );

		Array.prototype.forEach.call( forms, function ( form ) {
			form.addEventListener( 'submit', function () {
				var button = form.querySelector( '[type="submit"]' );

				if ( ! button || button.disabled ) {
					return;
				}

				button.disabled = true;
				window.setTimeout( function () {
					button.disabled = false;
				}, 4000 );
			} );
		} );
	}

	function init() {
		initPasswordToggles();
		initPasswordMatch();
		initLogoutConfirm();
		initCopyButtons();
		initWidgetPanels();
		initPasswordRules();
		initConfirmActions();
		initSubmitGuard();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
