<?php
/**
 * Система уведомлений кабинета (сообщения передаются кодом в URL).
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Notices
 */
class WPPA_Notices {

	/**
	 * Реестр сообщений: код => array( тип, текст ).
	 *
	 * @return array
	 */
	public static function registry() {
		$messages = array(
			'profile_saved'         => array( 'success', __( 'Профиль обновлён.', 'wp-personal-account' ) ),
			'password_changed'      => array( 'success', __( 'Пароль изменён.', 'wp-personal-account' ) ),
			'email_confirm_sent'    => array( 'success', __( 'Письмо с подтверждением отправлено на новый адрес. Перейдите по ссылке из письма.', 'wp-personal-account' ) ),
			'email_confirmed'       => array( 'success', __( 'Новый email подтверждён и сохранён.', 'wp-personal-account' ) ),
			'registration_success'  => array( 'success', __( 'Регистрация завершена. Добро пожаловать!', 'wp-personal-account' ) ),
			'login_success'         => array( 'success', __( 'Вы вошли в аккаунт.', 'wp-personal-account' ) ),
			'logged_out'            => array( 'success', __( 'Вы вышли из аккаунта.', 'wp-personal-account' ) ),
			'email_confirm_invalid' => array( 'error', __( 'Ссылка подтверждения недействительна или истекла. Запросите смену email повторно.', 'wp-personal-account' ) ),
			'email_confirm_login'   => array( 'info', __( 'Войдите в аккаунт и перейдите по ссылке подтверждения ещё раз.', 'wp-personal-account' ) ),
			'invalid_nonce'         => array( 'error', __( 'Срок действия формы истёк. Обновите страницу и попробуйте снова.', 'wp-personal-account' ) ),
			'empty_fields'          => array( 'error', __( 'Заполните все обязательные поля.', 'wp-personal-account' ) ),
			'login_failed'          => array( 'error', __( 'Неверный логин или пароль.', 'wp-personal-account' ) ),
			'login_blocked'         => array( 'error', __( 'Слишком много попыток входа. Попробуйте через 15 минут.', 'wp-personal-account' ) ),
			'registration_closed'   => array( 'error', __( 'Регистрация на сайте отключена.', 'wp-personal-account' ) ),
			'email_invalid'         => array( 'error', __( 'Укажите корректный email.', 'wp-personal-account' ) ),
			'email_exists'          => array( 'error', __( 'Пользователь с таким email уже зарегистрирован.', 'wp-personal-account' ) ),
			'login_exists'          => array( 'error', __( 'Такое имя пользователя уже занято.', 'wp-personal-account' ) ),
			'password_mismatch'     => array( 'error', __( 'Пароли не совпадают.', 'wp-personal-account' ) ),
			'password_short'        => array( 'error', __( 'Пароль должен быть не короче 8 символов.', 'wp-personal-account' ) ),
			'password_wrong'        => array( 'error', __( 'Текущий пароль указан неверно.', 'wp-personal-account' ) ),
			'password_same'         => array( 'error', __( 'Новый пароль должен отличаться от текущего.', 'wp-personal-account' ) ),
			'field_required'        => array( 'error', __( 'Заполните все обязательные поля.', 'wp-personal-account' ) ),
			'field_too_long'        => array( 'error', __( 'Одно из полей слишком длинное — сократите до 190 символов.', 'wp-personal-account' ) ),
			'phone_invalid'         => array( 'error', __( 'Укажите телефон в корректном формате, например +7 900 000-00-00.', 'wp-personal-account' ) ),
			'birthdate_invalid'     => array( 'error', __( 'Укажите корректную дату рождения.', 'wp-personal-account' ) ),
			'website_invalid'       => array( 'error', __( 'Укажите корректный адрес сайта, например https://example.com.', 'wp-personal-account' ) ),
			'no_consent'            => array( 'error', __( 'Необходимо согласие на обработку персональных данных.', 'wp-personal-account' ) ),
			'save_failed'           => array( 'error', __( 'Не удалось сохранить изменения. Попробуйте позже.', 'wp-personal-account' ) ),
			'email_same'            => array( 'info', __( 'Этот email уже указан в вашем профиле.', 'wp-personal-account' ) ),
			'team_created'          => array( 'success', __( 'Команда создана. Пригласите участников по ссылке.', 'wp-personal-account' ) ),
			'team_renamed'          => array( 'success', __( 'Название команды обновлено.', 'wp-personal-account' ) ),
			'team_joined'           => array( 'success', __( 'Вы присоединились к команде.', 'wp-personal-account' ) ),
			'team_left'             => array( 'success', __( 'Вы вышли из команды.', 'wp-personal-account' ) ),
			'team_dissolved'        => array( 'success', __( 'Команда распущена.', 'wp-personal-account' ) ),
			'team_member_removed'   => array( 'success', __( 'Участник удалён из команды.', 'wp-personal-account' ) ),
			'team_invite_sent'      => array( 'success', __( 'Приглашение отправлено на указанный email.', 'wp-personal-account' ) ),
			'team_token_refreshed'  => array( 'success', __( 'Ссылка-приглашение обновлена. Старая больше не действует.', 'wp-personal-account' ) ),
			'team_name_required'    => array( 'error', __( 'Укажите название команды.', 'wp-personal-account' ) ),
			'team_manager_only'     => array( 'error', __( 'Действие доступно капитану и наставнику команды.', 'wp-personal-account' ) ),
			'team_mentor_added'     => array( 'success', __( 'Участник назначен наставником команды.', 'wp-personal-account' ) ),
			'team_mentor_removed'   => array( 'success', __( 'Роль наставника снята.', 'wp-personal-account' ) ),
			'team_mentor_denied'    => array( 'error', __( 'Наставник не может исключать капитана или другого наставника.', 'wp-personal-account' ) ),
			'team_mentor_invalid'   => array( 'error', __( 'Наставником можно назначить только участника этой команды, кроме капитана.', 'wp-personal-account' ) ),
			'team_owner_only'       => array( 'error', __( 'Это действие доступно только капитану команды.', 'wp-personal-account' ) ),
			'team_full'             => array( 'error', __( 'В команде уже максимальное число участников.', 'wp-personal-account' ) ),
			'team_invalid_token'    => array( 'error', __( 'Ссылка-приглашение недействительна или истекла.', 'wp-personal-account' ) ),
			'team_already_member'   => array( 'info', __( 'Вы уже состоите в этой команде.', 'wp-personal-account' ) ),
			'team_other'            => array( 'error', __( 'Вы уже состоите в другой команде. Сначала выйдите из неё.', 'wp-personal-account' ) ),
			'team_login_required'   => array( 'info', __( 'Войдите в аккаунт, чтобы присоединиться к команде.', 'wp-personal-account' ) ),
		);

		/**
		 * Фильтр реестра сообщений.
		 *
		 * @param array $messages Сообщения.
		 */
		return apply_filters( 'wppa_notices', $messages );
	}

	/**
	 * Редирект с кодом сообщения.
	 *
	 * @param string $code Код сообщения.
	 * @param string $url  Куда перенаправить.
	 * @return void
	 */
	public static function redirect( $code, $url = '' ) {
		if ( '' === $url ) {
			$url = WPPA_Plugin::account_url();
		}

		$url = add_query_arg( 'wppa_notice', sanitize_key( $code ), $url );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Получить сообщение из URL.
	 *
	 * @return array|null array( 'type' => ..., 'text' => ... )
	 */
	public static function current() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только чтение кода для вывода.
		$code = isset( $_GET['wppa_notice'] ) ? sanitize_key( wp_unslash( $_GET['wppa_notice'] ) ) : '';

		if ( '' === $code ) {
			return null;
		}

		$registry = self::registry();
		if ( ! isset( $registry[ $code ] ) ) {
			return null;
		}

		return array(
			'type' => $registry[ $code ][0],
			'text' => $registry[ $code ][1],
		);
	}

	/**
	 * Вывод сообщения.
	 *
	 * @return void
	 */
	public static function render() {
		$notice = self::current();
		if ( ! $notice ) {
			return;
		}

		$type = in_array( $notice['type'], array( 'success', 'error', 'info' ), true ) ? $notice['type'] : 'info';
		printf(
			'<div class="wppa-notice wppa-notice--%1$s" role="status">%2$s</div>',
			esc_attr( $type ),
			esc_html( $notice['text'] )
		);
	}
}
