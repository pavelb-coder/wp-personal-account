<?php
/**
 * Участники и баллы: список участников команд (без наставников) и порог процента.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Participants
 */
class WPPA_Participants {

	/**
	 * Ключ настройки «минимальный процент правильных ответов».
	 */
	const MIN_PERCENT_KEY = 'participant_min_percent';

	/**
	 * Порог по умолчанию, %.
	 */
	const MIN_PERCENT_DEFAULT = 60;

	/**
	 * Ничего не регистрируем: класс — набор статических методов для блока.
	 *
	 * @return void
	 */
	public static function init() {}

	/**
	 * Минимальный процент правильных ответов, который задал куратор.
	 *
	 * @return int 0–100.
	 */
	public static function min_percent() {
		$value = (int) WPPA_Plugin::setting( self::MIN_PERCENT_KEY, self::MIN_PERCENT_DEFAULT );

		/**
		 * Фильтр минимального процента правильных ответов.
		 *
		 * @param int $value Процент.
		 */
		$value = (int) apply_filters( 'wppa_participant_min_percent', $value );

		return (int) min( 100, max( 0, $value ) );
	}

	/**
	 * Сохранить порог процента.
	 *
	 * @param int $value Процент 0–100.
	 * @return int Что сохранилось.
	 */
	public static function set_min_percent( $value ) {
		$value = (int) min( 100, max( 0, (int) $value ) );

		$settings                          = (array) get_option( 'wppa_settings', array() );
		$settings[ self::MIN_PERCENT_KEY ] = $value;

		update_option( 'wppa_settings', $settings );

		return $value;
	}

	/**
	 * Процент правильных ответов ниже порога.
	 *
	 * @param float $percentage Процент.
	 * @return bool
	 */
	public static function below_threshold( $percentage ) {
		return (float) $percentage < self::min_percent();
	}

	/**
	 * Кто видит блок и задаёт порог: только куратор.
	 *
	 * @param int $user_id Пользователь.
	 * @return bool
	 */
	public static function can_manage( $user_id = 0 ) {
		return WPPA_Teams::is_curator( $user_id );
	}

	/**
	 * Участники команд без наставников: строки для таблицы блока.
	 *
	 * Без открытой команды показываем все команды, с `?wppa_team=ID` — только её.
	 *
	 * @param int $curator_id Куратор.
	 * @return array user, team, role — по ID участника.
	 */
	public static function rows( $curator_id = 0 ) {
		$curator_id = (int) ( $curator_id ? $curator_id : get_current_user_id() );
		$single     = WPPA_Teams::viewing_team_id( $curator_id );
		$teams      = $single ? array( (int) $single ) : array_map( 'intval', array_column( WPPA_Teams::all_teams(), 'id' ) );
		$rows       = array();

		foreach ( $teams as $team_id ) {
			foreach ( WPPA_Teams::participants( $team_id ) as $member ) {
				$rows[ (int) $member->ID ] = array(
					'user' => $member,
					'team' => (int) $team_id,
					'role' => WPPA_Teams::role( (int) $member->ID ),
				);
			}
		}

		return $rows;
	}

	/**
	 * Баллы и процент по списку участников (один запрос на весь список).
	 *
	 * @param array $rows Строки из rows().
	 * @return array [ user_id => score, max_score, percentage, tests, pending ].
	 */
	public static function scores( $rows ) {
		if ( ! WPPA_Olympiad::is_active() || ! $rows ) {
			return array();
		}

		return WPPA_Olympiad::summary_map( array_keys( (array) $rows ) );
	}

	/**
	 * Сколько участников ниже порога.
	 *
	 * @param array $rows   Строки из rows().
	 * @param array $scores Баллы из scores().
	 * @return int
	 */
	public static function count_below( $rows, $scores ) {
		$below = 0;

		foreach ( (array) $rows as $user_id => $row ) {
			if ( isset( $scores[ $user_id ] ) && self::below_threshold( $scores[ $user_id ]['percentage'] ) ) {
				++$below;
			}
		}

		return $below;
	}

	/**
	 * Процент для вывода: «75 %» без лишнего нуля, «72,5 %» с десятыми.
	 *
	 * @param float $percentage Процент.
	 * @return string
	 */
	public static function percent_label( $percentage ) {
		$percentage = (float) $percentage;
		$decimals   = abs( $percentage - round( $percentage ) ) < 0.05 ? 0 : 1;

		/* translators: %s: процент правильных ответов */
		return sprintf( __( '%s %%', 'wp-personal-account' ), number_format_i18n( $percentage, $decimals ) );
	}

	/**
	 * Обработка формы «минимальный процент правильных ответов».
	 *
	 * @return void
	 */
	public static function handle_threshold() {
		check_admin_referer( 'wppa_participant_threshold', 'wppa_nonce' );

		$back = WPPA_Plugin::tab_url( 'participants' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce проверен выше.
		$team_id = isset( $_POST['team_id'] ) ? absint( wp_unslash( $_POST['team_id'] ) ) : 0;
		$value   = isset( $_POST['min_percent'] ) ? absint( wp_unslash( $_POST['min_percent'] ) ) : self::MIN_PERCENT_DEFAULT;
		// phpcs:enable

		if ( $team_id ) {
			$back = add_query_arg( 'wppa_team', $team_id, $back );
		}

		// Возвращаем туда, откуда пришла форма: та же вкладка, та же команда.
		$back = WPPA_Plugin::posted_return( $back );

		if ( ! self::can_manage() ) {
			WPPA_Notices::redirect( 'participant_threshold_forbidden', $back );
		}

		self::set_min_percent( $value );

		WPPA_Notices::redirect( 'participant_threshold_saved', $back );
	}
}
