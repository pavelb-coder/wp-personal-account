<?php
/**
 * Дата и время: разбор datetime-local, форматирование, обратный отсчёт.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Time
 */
class WPPA_Time {

	/**
	 * Разбор даты из поля datetime-local в метку времени по часовому поясу сайта.
	 *
	 * @param string $value Значение вида 2026-11-05T10:00.
	 * @return int Метка времени или 0, если дата не задана.
	 */
	public static function to_timestamp( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return 0;
		}

		$value = str_replace( 'T', ' ', $value );
		$value = preg_replace( '/\s+/', ' ', $value );

		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$date     = date_create( $value, $timezone );

		if ( ! $date ) {
			return 0;
		}

		return (int) $date->getTimestamp();
	}

	/**
	 * Форматирование метки времени по настройкам сайта.
	 *
	 * @param int $timestamp Метка времени.
	 * @return string
	 */
	public static function format_date( $timestamp ) {
		if ( ! $timestamp ) {
			return '';
		}

		if ( function_exists( 'wp_date' ) ) {
			return (string) wp_date( 'd.m.Y H:i', $timestamp );
		}

		return (string) date_i18n( 'd.m.Y H:i', $timestamp );
	}

	/**
	 * Проверка строки datetime-local перед сохранением.
	 *
	 * @param string $value Значение.
	 * @return string Пустая строка, если формат неверный.
	 */
	public static function sanitize_datetime( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ) {
			return '';
		}

		return '' === self::to_timestamp( $value ) ? '' : $value;
	}

	/**
	 * Текст обратного отсчёта.
	 *
	 * @param int $seconds Остаток в секундах.
	 * @return string
	 */
	public static function countdown_text( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		$days    = (int) floor( $seconds / DAY_IN_SECONDS );
		$rest    = $seconds % DAY_IN_SECONDS;
		$time    = sprintf( '%02d:%02d:%02d', (int) floor( $rest / 3600 ), (int) floor( ( $rest % 3600 ) / 60 ), $rest % 60 );

		if ( $days > 0 ) {
			return sprintf(
				/* translators: 1: дней, 2: время ЧЧ:ММ:СС */
				__( '%1$d д %2$s', 'wp-personal-account' ),
				$days,
				$time
			);
		}

		return $time;
	}

	/**
	 * Разметка таймера: серверный текст плюс атрибуты для скрипта.
	 *
	 * @param int    $timestamp Метка времени цели.
	 * @param string $mode      open|close — открытие или закрытие доступа.
	 * @param bool   $reload    Перезагрузить страницу, когда время выйдет.
	 * @return string
	 */
	public static function countdown_html( $timestamp, $mode = 'open', $reload = false ) {
		$timestamp = (int) $timestamp;

		if ( ! $timestamp ) {
			return '';
		}

		$mode = 'close' === $mode ? 'close' : 'open';

		return sprintf(
			'<span class="wppa-countdown" data-wppa-countdown="%1$d" data-wppa-countdown-mode="%2$s"%3$s>%4$s</span>',
			$timestamp,
			esc_attr( $mode ),
			$reload ? ' data-wppa-reload="1"' : '',
			esc_html( self::countdown_text( $timestamp - time() ) )
		);
	}
}
