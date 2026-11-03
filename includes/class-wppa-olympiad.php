<?php
/**
 * Интеграция с плагином олимпиад (wordprees): баллы за тесты в личном кабинете.
 *
 * Плагин олимпиад хранит результаты в своих таблицах:
 *   {prefix}olympiad_quizzes  — тесты (id, title, status)
 *   {prefix}olympiad_attempts — попытки (quiz_id, user_id, score, max_score, percentage, status, submitted_at)
 *
 * Если плагина нет, интеграция молча выключается: вкладка не появляется,
 * запросов к базе нет.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Olympiad
 */
class WPPA_Olympiad {

	/**
	 * Настройка «показывать вкладку».
	 */
	const SETTING = 'olympiad_tab';

	/**
	 * Транзиент проверки наличия плагина.
	 */
	const DETECT_KEY = 'wppa_olympiad_active';

	/**
	 * Результат проверки в пределах запроса.
	 *
	 * @var bool|null
	 */
	private static $active = null;

	/**
	 * Статусы, которые считаются засчитанной работой.
	 *
	 * @return array
	 */
	public static function counted_statuses() {
		/**
		 * Фильтр статусов попыток, которые идут в баллы.
		 *
		 * @param array $statuses Статусы.
		 */
		return (array) apply_filters( 'wppa_olympiad_counted_statuses', array( 'completed', 'needs_review' ) );
	}

	/**
	 * Названия статусов для вывода.
	 *
	 * @param string $status Статус.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'completed'      => __( 'Завершено', 'wp-personal-account' ),
			'needs_review'   => __( 'На проверке', 'wp-personal-account' ),
			'in_progress'    => __( 'В процессе', 'wp-personal-account' ),
			'expired'        => __( 'Время вышло', 'wp-personal-account' ),
			'disqualified'   => __( 'Снято', 'wp-personal-account' ),
			'browser_closed' => __( 'Прервано', 'wp-personal-account' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Таблица плагина олимпиад.
	 *
	 * @param string $name quizzes|attempts.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;

		return $wpdb->prefix . 'olympiad_' . $name;
	}

	/**
	 * Установлен ли плагин олимпиад и есть ли его таблицы.
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( null !== self::$active ) {
			/**
			 * Фильтр наличия плагина олимпиад.
			 *
			 * @param bool $active Найден ли плагин.
			 */
			return (bool) apply_filters( 'wppa_olympiad_active', self::$active );
		}

		$cached = get_transient( self::DETECT_KEY );

		if ( 'yes' === $cached || 'no' === $cached ) {
			self::$active = 'yes' === $cached;
			/**
			 * Фильтр наличия плагина олимпиад.
			 *
			 * @param bool $active Найден ли плагин.
			 */
			return (bool) apply_filters( 'wppa_olympiad_active', 'yes' === $cached );
		}

		$active = class_exists( 'WPOlympiad\Attempt' ) || class_exists( 'WPOlympiad\Db' );

		if ( ! $active ) {
			global $wpdb;
			$table = self::table( 'attempts' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			$active = ( $found === $table );
		}

		self::$active = (bool) $active;

		set_transient( self::DETECT_KEY, $active ? 'yes' : 'no', HOUR_IN_SECONDS );

		/**
		 * Фильтр наличия плагина олимпиад.
		 *
		 * @param bool $active Найден ли плагин.
		 */
		return (bool) apply_filters( 'wppa_olympiad_active', $active );
	}

	/**
	 * Сбросить кэш проверки.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$active = null;
		delete_transient( self::DETECT_KEY );
	}

	/**
	 * Результаты пользователя по тестам.
	 *
	 * @param int $user_id ID пользователя.
	 * @return array Список: quiz_id, title, score, max_score, percentage, status, submitted_at, counted.
	 */
	public static function results( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 || ! self::is_active() ) {
			return array();
		}

		global $wpdb;

		$attempts = self::table( 'attempts' );
		$quizzes  = self::table( 'quizzes' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.id, a.quiz_id, a.score, a.max_score, a.percentage, a.status, a.submitted_at, q.title
				FROM {$attempts} a
				LEFT JOIN {$quizzes} q ON q.id = a.quiz_id
				WHERE a.user_id = %d
				ORDER BY a.submitted_at DESC, a.id DESC",
				$user_id
			)
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$counted  = self::counted_statuses();
		$results  = array();

		foreach ( $rows as $row ) {
			$row    = (array) $row;
			$status = isset( $row['status'] ) ? (string) $row['status'] : '';
			$max    = isset( $row['max_score'] ) ? (int) $row['max_score'] : 0;
			$score  = isset( $row['score'] ) ? (int) $row['score'] : 0;

			$results[] = array(
				'id'           => isset( $row['id'] ) ? (int) $row['id'] : 0,
				'quiz_id'      => isset( $row['quiz_id'] ) ? (int) $row['quiz_id'] : 0,
				'title'        => ! empty( $row['title'] ) ? (string) $row['title'] : __( 'Тест без названия', 'wp-personal-account' ),
				'score'        => $score,
				'max_score'    => $max,
				'percentage'   => isset( $row['percentage'] ) ? (float) $row['percentage'] : ( $max > 0 ? round( $score / $max * 100, 2 ) : 0.0 ),
				'status'       => $status,
				'status_label' => self::status_label( $status ),
				'submitted_at' => isset( $row['submitted_at'] ) ? (string) $row['submitted_at'] : '',
				'counted'      => in_array( $status, $counted, true ),
			);
		}

		return $results;
	}

	/**
	 * Итоги по баллам.
	 *
	 * @param int $user_id ID пользователя.
	 * @return array tests, score, max_score, percentage, best
	 */
	public static function totals( $user_id ) {
		$results = self::results( $user_id );
		$totals  = array(
			'tests'      => 0,
			'score'      => 0,
			'max_score'  => 0,
			'percentage' => 0.0,
			'best'       => null,
			'pending'    => 0,
		);

		foreach ( $results as $result ) {
			if ( 'needs_review' === $result['status'] ) {
				++$totals['pending'];
			}

			if ( ! $result['counted'] ) {
				continue;
			}

			++$totals['tests'];
			$totals['score']     += $result['score'];
			$totals['max_score'] += $result['max_score'];

			if ( null === $totals['best'] || $result['percentage'] > $totals['best']['percentage'] ) {
				$totals['best'] = $result;
			}
		}

		if ( $totals['max_score'] > 0 ) {
			$totals['percentage'] = round( $totals['score'] / $totals['max_score'] * 100, 2 );
		}

		return $totals;
	}

	/**
	 * Баллы одного пользователя одной строкой (для сводок и экспорта).
	 *
	 * @param int $user_id ID пользователя.
	 * @return array score, max_score, percentage, tests
	 */
	public static function summary( $user_id ) {
		$totals = self::totals( $user_id );

		return array(
			'score'      => $totals['score'],
			'max_score'  => $totals['max_score'],
			'percentage' => $totals['percentage'],
			'tests'      => $totals['tests'],
		);
	}
}
