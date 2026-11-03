<?php
/**
 * Кнопка перехода на внешнюю ссылку: окно дат и условие по составу команды.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Link_Button
 */
class WPPA_Link_Button {

	/**
	 * Ключ настройки.
	 */
	const SETTING = 'link_button';

	/**
	 * Значения по умолчанию.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'url'          => '',
			'text'         => __( 'Перейти по ссылке', 'wp-personal-account' ),
			'start'        => '',
			'end'          => '',
			'new_tab'      => 1,
			'require_team' => 1,
			'note'         => '',
		);
	}

	/**
	 * Текущие настройки кнопки.
	 *
	 * @return array
	 */
	public static function config() {
		$saved = WPPA_Plugin::setting( self::SETTING, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$out   = array();

		foreach ( self::defaults() as $key => $default ) {
			$out[ $key ] = array_key_exists( $key, $saved ) ? $saved[ $key ] : $default;
		}

		$out['url']          = (string) $out['url'];
		$out['text']         = '' === trim( (string) $out['text'] ) ? self::defaults()['text'] : (string) $out['text'];
		$out['start']        = (string) $out['start'];
		$out['end']          = (string) $out['end'];
		$out['new_tab']      = empty( $out['new_tab'] ) ? 0 : 1;
		$out['require_team'] = empty( $out['require_team'] ) ? 0 : 1;

		// Комментарий под кнопкой: обычный текст с переносами строк, без разметки.
		$out['note'] = trim( (string) $out['note'] );

		/**
		 * Фильтр настроек кнопки со ссылкой.
		 *
		 * @param array $out Настройки.
		 */
		return apply_filters( 'wppa_link_button', $out );
	}

	/**
	 * Разбор даты из поля datetime-local в метку времени по часовому поясу сайта.
	 *
	 * @param string $value Значение вида 2026-11-05T10:00.
	 * @return int Метка времени или 0, если дата не задана.
	 */
	public static function to_timestamp( $value ) {
		return WPPA_Time::to_timestamp( $value );
	}

	/**
	 * Наставник ли пользователь.
	 *
	 * Наставник в команде наблюдатель: в зачёт минимума не идёт, участником не
	 * считается. Поэтому кнопка перехода на внешнюю ссылку ему не выдаётся —
	 * ни открытой, ни закрытой.
	 *
	 * @param int $user_id ID пользователя (0 — текущий).
	 * @return bool
	 */
	public static function is_mentor( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! $user_id || ! WPPA_Teams::team_id( $user_id ) ) {
			return false;
		}

		return 'mentor' === WPPA_Teams::role( $user_id );
	}

	/**
	 * Состояние команды текущего пользователя относительно минимума участников.
	 *
	 * Наставник в зачёт не идёт — считается как в WPPA_Teams::members_count().
	 *
	 * @param int $user_id ID пользователя.
	 * @return array team_id, members, required, ready.
	 */
	public static function team_progress( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$team_id = $user_id ? (int) WPPA_Teams::team_id( $user_id ) : 0;

		if ( ! $team_id ) {
			return array(
				'team_id'         => 0,
				'members'         => 0,
				'required'        => (int) WPPA_Teams::min_members(),
				'mentors'         => 0,
				'mentor_required' => WPPA_Teams::require_mentor(),
				'has_mentor'      => false,
				'ready'           => false,
			);
		}

		$members  = (int) WPPA_Teams::members_count( $team_id );
		$required = (int) WPPA_Teams::team_min( $team_id );

		return array(
			'team_id'         => $team_id,
			'members'         => $members,
			'required'        => $required,
			'mentors'         => (int) WPPA_Teams::mentors_count( $team_id ),
			'mentor_required' => WPPA_Teams::require_mentor(),
			'has_mentor'      => WPPA_Teams::has_mentor( $team_id ),
			'ready'           => $required > 0 && WPPA_Teams::is_complete( $team_id ),
		);
	}

	/**
	 * Текущее состояние кнопки.
	 *
	 * status: mentor | unconfigured | team | before | after | ready.
	 *
	 * @param int $user_id ID пользователя (0 — текущий).
	 * @return array
	 */
	public static function state( $user_id = 0 ) {
		$config   = self::config();
		$user_id  = $user_id ? (int) $user_id : get_current_user_id();
		$start_ts = self::to_timestamp( $config['start'] );
		$end_ts   = self::to_timestamp( $config['end'] );
		$now      = time();

		$state = array(
			'status'       => 'ready',
			'url'          => $config['url'],
			'text'         => $config['text'],
			'new_tab'      => (int) $config['new_tab'],
			'message'      => '',
			'start_ts'     => $start_ts,
			'end_ts'       => $end_ts,
			'countdown'    => 0,
			'countdown_of' => '',
			'team'         => array(),
			'is_open'      => false,
			'is_mentor'    => false,
			'note'         => (string) $config['note'],
		);

		// Наставнику кнопка не выдаётся: ссылка предназначена участникам.
		if ( self::is_mentor( $user_id ) ) {
			$state['status']    = 'mentor';
			$state['is_mentor'] = true;
			$state['message']   = __( 'Кнопка перехода выдаётся участникам команды; наставникам она недоступна.', 'wp-personal-account' );
			return $state;
		}

		if ( '' === trim( (string) $config['url'] ) ) {
			$state['status']  = 'unconfigured';
			$state['message'] = __( 'Ссылка пока не задана: укажите её в настройках плагина.', 'wp-personal-account' );
			return $state;
		}

		if ( $config['require_team'] ) {
			$team          = self::team_progress( $user_id );
			$state['team'] = $team;

			if ( ! $team['team_id'] ) {
				$state['status']  = 'team';
				$state['message'] = __( 'Кнопка откроется, когда у вас появится команда.', 'wp-personal-account' );
				return $state;
			}

			if ( ! $team['ready'] ) {
				$state['status'] = 'team';

				if ( $team['members'] >= $team['required'] && ! $team['has_mentor'] ) {
					$state['message'] = __( 'Ждём наставника: в команде пока нет наставника.', 'wp-personal-account' );
					return $state;
				}

				$state['message'] = sprintf(
					/* translators: 1: участников сейчас, 2: сколько нужно */
					__( 'Ждём состав команды: %1$d из %2$d участников.', 'wp-personal-account' ),
					(int) $team['members'],
					(int) $team['required']
				);

				if ( $team['mentor_required'] && ! $team['has_mentor'] ) {
					$state['message'] .= ' ' . __( 'Ещё нужен наставник.', 'wp-personal-account' );
				}

				return $state;
			}
		}

		if ( $start_ts && $now < $start_ts ) {
			$state['status']       = 'before';
			$state['countdown']    = $start_ts;
			$state['countdown_of'] = 'start';
			$state['message']      = sprintf(
				/* translators: %s: дата и время открытия */
				__( 'Откроется %s.', 'wp-personal-account' ),
				self::format_date( $start_ts )
			);
			return $state;
		}

		if ( $end_ts && $now > $end_ts ) {
			$state['status']  = 'after';
			$state['message'] = sprintf(
				/* translators: %s: дата и время закрытия */
				__( 'Срок доступа истёк %s.', 'wp-personal-account' ),
				self::format_date( $end_ts )
			);
			return $state;
		}

		$state['is_open'] = true;

		if ( $end_ts ) {
			$state['countdown']    = $end_ts;
			$state['countdown_of'] = 'end';
			$state['message']      = sprintf(
				/* translators: %s: дата и время закрытия */
				__( 'Доступно до %s.', 'wp-personal-account' ),
				self::format_date( $end_ts )
			);
		}

		return $state;
	}

	/**
	 * Форматирование метки времени по настройкам сайта.
	 *
	 * @param int $timestamp Метка времени.
	 * @return string
	 */
	public static function format_date( $timestamp ) {
		return WPPA_Time::format_date( $timestamp );
	}

	/**
	 * Текст обратного отсчёта для первичного вывода (до работы скрипта).
	 *
	 * @param int $seconds Остаток в секундах.
	 * @return string
	 */
	public static function countdown_text( $seconds ) {
		return WPPA_Time::countdown_text( $seconds );
	}

	/**
	 * Проверка строки datetime-local перед сохранением.
	 *
	 * @param string $value Значение.
	 * @return string Пустая строка, если формат неверный.
	 */
	public static function sanitize_datetime( $value ) {
		return WPPA_Time::sanitize_datetime( $value );
	}
}
