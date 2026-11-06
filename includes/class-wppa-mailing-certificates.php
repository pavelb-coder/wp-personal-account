<?php
/**
 * Третья рассылка: письмо о выданном сертификате.
 *
 * Получатели — участники и капитаны команд, у которых уже есть выданный
 * сертификат: наставники не входят, команды без сертификатов тоже. Отметка
 * второго этапа не нужна. Свои шаблон, очередь, журнал и отметка «уже
 * отправлено» — три рассылки не мешают друг другу.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Mailing_Certificates
 */
class WPPA_Mailing_Certificates extends WPPA_Mailing {

	/**
	 * Опция с шаблоном и настройками отправки.
	 */
	const OPTION = 'wppa_mailing_certificates';

	/**
	 * Опция с очередью.
	 */
	const QUEUE = 'wppa_mailing_certificates_run';

	/**
	 * Опция с журналом.
	 */
	const LOG = 'wppa_mailing_certificates_log';

	/**
	 * Хук WP-Cron этой рассылки.
	 */
	const CRON_HOOK = 'wppa_mailing_certificates_tick';

	/**
	 * Мета «письмо уже отправлен» — своя, отдельно от других рассылок.
	 */
	const META_SENT = '_wppa_mailing_certificates_sent';

	/**
	 * Отметка второго этапа для этой рассылки не требуется.
	 *
	 * @return bool
	 */
	public static function needs_stage2() {
		return false;
	}

	/**
	 * Команда попадает в рассылку, только пока у её участников есть сертификаты.
	 *
	 * @param int $team_id ID команды.
	 * @return bool
	 */
	public static function team_allowed( $team_id ) {
		if ( ! class_exists( 'WPPA_Certificates' ) ) {
			return false;
		}

		return WPPA_Certificates::team_has( (int) $team_id );
	}

	/**
	 * Причина снятия писем команды из очереди — для журнала.
	 *
	 * @return string
	 */
	public static function drop_reason() {
		return __( 'сертификаты участников убраны', 'wp-personal-account' );
	}

	/**
	 * Код рассылки.
	 *
	 * @return string
	 */
	public static function kind() {
		return 'certificates';
	}

	/**
	 * Стандартный шаблон письма о сертификате.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'subject'   => __( 'Ваш сертификат №{номер}', 'wp-personal-account' ),
			'body'      => __( "Здравствуйте, {имя}!\n\nВам выдан сертификат №{номер} по программе «{программа}», дата выдачи — {дата_выдачи}.\n\nСкачать сертификат в PDF можно в личном кабинете, во вкладке «Сертификаты».\n\nПисьмо отправлено на {email}. Если это ошибка — ответьте на это письмо.\n\n— {сайт}", 'wp-personal-account' ),
			'interval'  => 5,
			'batch'     => 5,
			'skip_sent' => 1,
		);
	}

	/**
	 * Доступные подстановки: к общим добавляются данные сертификата.
	 *
	 * @param array $item Письмо из очереди.
	 * @return array
	 */
	public static function placeholders( $item ) {
		$map = parent::placeholders( $item );

		$map['{номер}']       = isset( $item['number'] ) ? (string) $item['number'] : '';
		$map['{программа}']   = isset( $item['course'] ) ? (string) $item['course'] : '';
		$map['{дата_выдачи}'] = isset( $item['issued'] ) ? (string) $item['issued'] : '';
		$map['{сертификатов}'] = isset( $item['certs'] ) ? (string) (int) $item['certs'] : '0';

		return $map;
	}

	/**
	 * Список подстановок для подсказки в интерфейсе.
	 *
	 * @return string[]
	 */
	public static function placeholder_list() {
		return array( '{имя}', '{фамилия}', '{команда}', '{номер}', '{программа}', '{дата_выдачи}', '{сертификатов}', '{email}', '{сайт}' );
	}

	/**
	 * Получатели: участники и капитаны команд, у которых есть сертификат.
	 *
	 * Одно письмо на человека — про последний выданный сертификат; сколько
	 * всего сертификатов у участника, подскажет `{сертификатов}`.
	 * Наставники в список не попадают.
	 *
	 * @return array
	 */
	public static function recipients() {
		if ( ! class_exists( 'WPPA_Certificates' ) ) {
			return array();
		}

		$rows = array();
		$seen = array();

		foreach ( WPPA_Teams::all_teams() as $team ) {
			$team_id = (int) $team['id'];

			if ( ! static::team_allowed( $team_id ) ) {
				continue;
			}

			foreach ( WPPA_Teams::participants( $team_id ) as $member ) {
				$certs = WPPA_Certificates::for_user( (int) $member->ID );

				if ( ! $certs ) {
					continue;
				}

				$email = sanitize_email( (string) $member->user_email );

				if ( '' === $email || ! is_email( $email ) ) {
					continue;
				}

				$key = strtolower( $email );

				if ( isset( $seen[ $key ] ) ) {
					continue;
				}

				$seen[ $key ] = true;

				// for_user() отдаёт новые первыми — берём последний выданный.
				$latest = reset( $certs );
				$issued = ! empty( $latest['time'] ) ? date_i18n( get_option( 'date_format' ), (int) $latest['time'] ) : '';

				$rows[] = array(
					'user_id' => (int) $member->ID,
					'name'    => (string) $member->display_name,
					'first'   => (string) $member->first_name,
					'last'    => (string) $member->last_name,
					'email'   => $email,
					'role'    => WPPA_Teams::role_label( WPPA_Teams::role( (int) $member->ID ) ),
					'team_id' => $team_id,
					'team'    => (string) $team['name'],
					'date'    => $issued,
					'number'  => (string) $latest['number'],
					'course'  => '' !== (string) $latest['course'] ? (string) $latest['course'] : (string) $team['name'],
					'issued'  => $issued,
					'certs'   => count( $certs ),
				);
			}
		}

		return $rows;
	}
}
