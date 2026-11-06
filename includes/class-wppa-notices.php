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
			'reset_sent'         => array( 'success', __( 'Если такой аккаунт есть, письмо со ссылкой для сброса пароля отправлено. Проверьте почту.', 'wp-personal-account' ) ),
			'reset_done'         => array( 'success', __( 'Пароль обновлён — войдите с новым паролем.', 'wp-personal-account' ) ),
			'reset_login_empty'  => array( 'error', __( 'Укажите логин или email.', 'wp-personal-account' ) ),
			'reset_too_fast'     => array( 'error', __( 'Заявка уже отправлена. Повторно можно через минуту.', 'wp-personal-account' ) ),
			'reset_link_invalid' => array( 'error', __( 'Ссылка для сброса пароля недействительна или устарела. Запросите новую.', 'wp-personal-account' ) ),
			'registration_closed'   => array( 'error', __( 'Регистрация на сайте отключена.', 'wp-personal-account' ) ),
			'registration_not_yet'  => array( 'error', __( 'Регистрация ещё не открыта — форма появится после начала приёма заявок.', 'wp-personal-account' ) ),
			'registration_over'     => array( 'error', __( 'Приём заявок на регистрацию уже закрыт.', 'wp-personal-account' ) ),
			'email_invalid'         => array( 'error', __( 'Укажите корректный email.', 'wp-personal-account' ) ),
			'email_exists'          => array( 'error', __( 'Пользователь с таким email уже зарегистрирован.', 'wp-personal-account' ) ),
			'login_exists'          => array( 'error', __( 'Такое имя пользователя уже занято.', 'wp-personal-account' ) ),
			'password_mismatch'     => array( 'error', __( 'Пароли не совпадают.', 'wp-personal-account' ) ),
			'password_short'        => array(
				'error',
				sprintf(
					/* translators: %d: минимальная длина пароля */
					__( 'Пароль должен быть не короче %d символов.', 'wp-personal-account' ),
					WPPA_Plugin::min_password_length()
				),
			),
			'password_no_uppercase' => array( 'error', __( 'Пароль должен содержать хотя бы одну заглавную букву.', 'wp-personal-account' ) ),
			'password_no_lowercase' => array( 'error', __( 'Пароль должен содержать хотя бы одну строчную букву.', 'wp-personal-account' ) ),
			'password_no_digit'     => array( 'error', __( 'Пароль должен содержать хотя бы одну цифру.', 'wp-personal-account' ) ),
			'password_no_special'   => array( 'error', __( 'Пароль должен содержать хотя бы один спецсимвол: ! @ # $ % и т. п.', 'wp-personal-account' ) ),
			'password_variety'      => array(
				'error',
				sprintf(
					/* translators: %d: минимальное число разных символов */
					__( 'Пароль слишком однообразный: нужно не меньше %d разных символов.', 'wp-personal-account' ),
					WPPA_Password::MIN_UNIQUE
				),
			),
			'password_repeat'       => array( 'error', __( 'Пароль не должен содержать повторов вида «aaa» или «111».', 'wp-personal-account' ) ),
			'password_sequence'     => array( 'error', __( 'Пароль не должен содержать последовательностей вида «1234», «abcd» или «qwer».', 'wp-personal-account' ) ),
			'password_common'       => array( 'error', __( 'Этот пароль слишком распространён — выберите другой.', 'wp-personal-account' ) ),
			'password_personal'     => array( 'error', __( 'Пароль не должен содержать ваш email, логин или имя.', 'wp-personal-account' ) ),
			'password_weak'         => array( 'error', __( 'Пароль не соответствует требованиям безопасности.', 'wp-personal-account' ) ),
			'password_wrong'        => array( 'error', __( 'Текущий пароль указан неверно.', 'wp-personal-account' ) ),
			'password_same'         => array( 'error', __( 'Новый пароль должен отличаться от текущего.', 'wp-personal-account' ) ),
			'field_required'        => array( 'error', __( 'Заполните все обязательные поля.', 'wp-personal-account' ) ),
			'field_too_long'        => array( 'error', __( 'Одно из полей слишком длинное — сократите до 190 символов.', 'wp-personal-account' ) ),
			'field_invalid'         => array( 'error', __( 'Проверьте значение поля: формат не подходит.', 'wp-personal-account' ) ),
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
			'team_curator_denied'   => array( 'error', __( 'Состав команды ведут капитан и наставник: куратор его не меняет.', 'wp-personal-account' ) ),
			'stage2_added'          => array( 'success', __( 'Команда отмечена: прошла во второй этап.', 'wp-personal-account' ) ),
			'stage2_removed'        => array( 'success', __( 'Отметка «прошла во второй этап» снята.', 'wp-personal-account' ) ),
			'stage2_denied'         => array( 'error', __( 'Отмечать команды второго этапа может только куратор.', 'wp-personal-account' ) ),
			'stage2_removed_mail'   => array( 'success', __( 'Отметка снята, письма этой команды убраны из очереди рассылки — они не отправятся.', 'wp-personal-account' ) ),
			'stage2_removed_grace'  => array( 'info', __( 'Отметка снята в течение минуты — сработала защита от случайного нажатия: письма этой команде не отправлялись.', 'wp-personal-account' ) ),
			'team_missing_team'     => array( 'error', __( 'Команда не найдена — откройте её из списка команд.', 'wp-personal-account' ) ),
			'mailing_cancelled'     => array( 'success', __( 'Рассылка участникам второго этапа отменена, очередь очищена.', 'wp-personal-account' ) ),
			'mailing_empty'         => array( 'info', __( 'Очередь рассылки и так пуста — отменять нечего.', 'wp-personal-account' ) ),
			'mailing_denied'        => array( 'error', __( 'Управлять рассылкой участникам второго этапа может только куратор.', 'wp-personal-account' ) ),
			'mailing_saved'         => array( 'success', __( 'Шаблон письма сохранён.', 'wp-personal-account' ) ),
			'mailing_started'       => array( 'success', __( 'Рассылка поставлена в очередь: письма уходят по одному с заданным интервалом.', 'wp-personal-account' ) ),
			'mailing_nobody'        => array( 'warning', __( 'Отправлять некому: нет команд второго этапа или все получатели уже получили письмо.', 'wp-personal-account' ) ),
			'mailing_resumed'       => array( 'success', __( 'Рассылка продолжена.', 'wp-personal-account' ) ),
			'mailing_sent_now'      => array( 'success', __( 'Очередь отправлена досрочно. Итоги — в журнале рассылки.', 'wp-personal-account' ) ),
			'mailing_send_failed'   => array( 'error', __( 'Отправить письма не удалось: почтовый сервер вернул ошибку. Подробности — в журнале рассылки.', 'wp-personal-account' ) ),
			'mailing_log_cleared'   => array( 'success', __( 'Журнал рассылки очищен.', 'wp-personal-account' ) ),
			'team_captain_denied'   => array( 'error', __( 'Капитана исключить нельзя: команду распускает сам капитан.', 'wp-personal-account' ) ),
			'curator_added'         => array( 'success', __( 'Пользователь назначен куратором: он видит все команды и управляет составом.', 'wp-personal-account' ) ),
			'curator_removed'       => array( 'success', __( 'Пользователь больше не куратор.', 'wp-personal-account' ) ),
			'curator_already'       => array( 'info', __( 'Этот пользователь уже куратор.', 'wp-personal-account' ) ),
			'curator_not'           => array( 'info', __( 'Этот пользователь не был куратором.', 'wp-personal-account' ) ),
			'curator_invalid'       => array( 'error', __( 'Не удалось изменить статус куратора: пользователь не найден.', 'wp-personal-account' ) ),
			'file_added'            => array( 'success', __( 'Файл прикреплён: его увидят в разделе «Файлы» наставник команды и куратор.', 'wp-personal-account' ) ),
			'file_removed'          => array( 'success', __( 'Файл убран из кабинета. Копия в сетевой папке команды, если файл туда выгружался, осталась.', 'wp-personal-account' ) ),
			'file_missing'          => array( 'error', __( 'Файл не найден или не выбран.', 'wp-personal-account' ) ),
			'file_type'             => array( 'error', __( 'Такой тип файла прикрепить нельзя. Разрешены документы, таблицы, презентации, архивы и изображения.', 'wp-personal-account' ) ),
			'file_size'             => array( 'error', __( 'Файл больше допустимого размера — до 10 МБ.', 'wp-personal-account' ) ),
			'file_empty'            => array( 'error', __( 'Файл пустой — выберите другой.', 'wp-personal-account' ) ),
			'file_limit'            => array( 'error', __( 'К команде уже прикреплено максимальное число файлов — удалите лишние.', 'wp-personal-account' ) ),
			'file_save_failed'      => array( 'error', __( 'Не удалось сохранить файл на сервере — проверьте права на папку uploads.', 'wp-personal-account' ) ),
			'file_forbidden'        => array( 'error', __( 'Файлы прикрепляют куратор и наставник команды; удалить можно только свой файл.', 'wp-personal-account' ) ),
			'file_stage2'         => array( 'error', __( 'Прикреплять файлы можно после того, как команда прошла во второй этап.', 'wp-personal-account' ) ),
			'file_team_missing'     => array( 'error', __( 'Команда не найдена — откройте её из списка команд.', 'wp-personal-account' ) ),
			'certificate_issued'      => array( 'success', __( 'Сертификат выдан: участник увидит его в разделе «Сертификаты».', 'wp-personal-account' ) ),
			'certificate_issued_many' => array( 'success', __( 'Сертификаты выданы участникам с процентом не ниже порога.', 'wp-personal-account' ) ),
			'certificate_nobody'      => array( 'warning', __( 'Выдавать пока некому: у всех участников процент ниже порога или нет результатов тестов.', 'wp-personal-account' ) ),
			'certificate_removed'     => array( 'success', __( 'Сертификат убран из кабинета участника. Сам сертификат остался в WP Diplom.', 'wp-personal-account' ) ),
			'certificate_missing'     => array( 'error', __( 'Сертификат или участник не найден.', 'wp-personal-account' ) ),
			'certificate_forbidden'   => array( 'error', __( 'Сертификаты выдаёт куратор.', 'wp-personal-account' ) ),
			'certificate_unavailable' => array( 'error', __( 'Для сертификатов нужен активный плагин WP Diplom.', 'wp-personal-account' ) ),
			'certificate_failed'      => array( 'error', __( 'Не удалось создать сертификат — подробности в WP Diplom.', 'wp-personal-account' ) ),
			'certificate_exported'    => array( 'success', __( 'PDF сертификатов выгружены в папку.', 'wp-personal-account' ) ),
			'certificate_export_nobody' => array( 'warning', __( 'Выгружать нечего: сертификатов в кабинете пока нет.', 'wp-personal-account' ) ),
			'certificate_export_failed' => array( 'error', __( 'Не удалось выгрузить сертификаты — проверьте папку и WP Diplom.', 'wp-personal-account' ) ),
			'certificate_updated'     => array( 'success', __( 'Сертификат изменён: PDF пересобран, файл в папке обновлён.', 'wp-personal-account' ) ),
			'certificate_update_failed' => array( 'error', __( 'Не удалось изменить сертификат — проверьте поля и плагин WP Diplom.', 'wp-personal-account' ) ),
			'certificate_rebuilt'     => array( 'success', __( 'PDF сертификата пересобран, файл в папке обновлён.', 'wp-personal-account' ) ),
			'certificate_rebuild_failed' => array( 'error', __( 'Не удалось пересобрать PDF сертификата — подробности в WP Diplom.', 'wp-personal-account' ) ),
			'participant_threshold_saved'     => array( 'success', __( 'Минимальный процент правильных ответов сохранён.', 'wp-personal-account' ) ),
			'participant_threshold_forbidden' => array( 'error', __( 'Минимальный процент задаёт куратор.', 'wp-personal-account' ) ),
			'files_added'           => array( 'success', __( 'Файлы прикреплены — они уже в кабинете, наставник и куратор увидят их сразу.', 'wp-personal-account' ) ),
			'files_added_partial'   => array( 'warning', __( 'Часть файлов прикреплена, часть — нет: проверьте тип, размер (до 10 МБ) и лимит файлов на команду.', 'wp-personal-account' ) ),
			'file_exported'         => array( 'success', __( 'Файл скопирован в сетевую папку команды.', 'wp-personal-account' ) ),
			'file_export_failed'    => array( 'warning', __( 'Файл прикреплён в кабинете, но скопировать его в сетевую папку не удалось. Подробности — в журнале выгрузки.', 'wp-personal-account' ) ),
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
