<?php
/**
 * Вторая рассылка: письмо о прикреплённых куратором файлах.
 *
 * Получатели — участники, капитан и наставник команд, которым куратор уже
 * прикрепил файлы: отметка второго этапа не нужна, а команда без файлов или
 * команда, у которой файлы удалили, в список не попадает.
 * Свои шаблон, очередь, журнал и отметка «уже отправлено» — две рассылки не
 * мешают друг другу.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Mailing_Files
 */
class WPPA_Mailing_Files extends WPPA_Mailing {

	/**
	 * Опция с шаблоном и настройками отправки.
	 */
	const OPTION = 'wppa_mailing_files';

	/**
	 * Опция с очередью.
	 */
	const QUEUE = 'wppa_mailing_files_run';

	/**
	 * Опция с журналом.
	 */
	const LOG = 'wppa_mailing_files_log';

	/**
	 * Хук WP-Cron этой рассылки.
	 */
	const CRON_HOOK = 'wppa_mailing_files_tick';

	/**
	 * Мета «письмо уже отправлен» — своя, отдельно от рассылки второго этапа.
	 */
	const META_SENT = '_wppa_mailing_files_sent';

	/**
	 * Отметка второго этапа для этой рассылки не требуется: файлы куратор
	 * прикрепляет любой команде, письмо — тоже любой.
	 *
	 * @return bool
	 */
	public static function needs_stage2() {
		return false;
	}

	/**
	 * Команда попадает в рассылку, только пока у неё есть файлы куратора.
	 *
	 * @param int $team_id ID команды.
	 * @return bool
	 */
	public static function team_allowed( $team_id ) {
		if ( ! class_exists( 'WPPA_Curator_Files' ) ) {
			return false;
		}

		return WPPA_Curator_Files::has_curator_files( (int) $team_id );
	}

	/**
	 * Причина снятия писем команды из очереди — для журнала.
	 *
	 * @return string
	 */
	public static function drop_reason() {
		return __( 'файлы команды удалены', 'wp-personal-account' );
	}

	/**
	 * Код рассылки.
	 *
	 * @return string
	 */
	public static function kind() {
		return 'files';
	}

	/**
	 * Стандартный шаблон письма про файлы.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'subject'   => __( 'Для команды «{команда}» прикреплены файлы', 'wp-personal-account' ),
			'body'      => __( "Здравствуйте, {имя}!\n\nКуратор прикрепил документы для команды «{команда}». Посмотреть и скачать их можно в личном кабинете, во вкладке «Файлы».\n\nПисьмо отправлено на {email}. Если это ошибка — ответьте на это письмо.\n\n— {сайт}", 'wp-personal-account' ),
			'interval'  => 5,
			'batch'     => 5,
			'skip_sent' => 1,
		);
	}
}
