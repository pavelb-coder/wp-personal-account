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
		initSubmitGuard();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
