<?php
/**
 * Рассылка участникам команд, прошедших во второй этап: шаблон письма и очередь отправки.
 *
 * Письма не уходят разом: очередь раскладывается с шагом «интервал» минут,
 * следующее письмо отправляет WP-Cron. Любой шаг можно отменить, а при серии
 * ошибок отправка приостанавливается сама.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Mailing
 */
class WPPA_Mailing {

	/**
	 * Опция с шаблоном и настройками отправки.
	 */
	const OPTION = 'wppa_mailing';

	/**
	 * Опция с текущей очередью (шаблон-снимок + письма).
	 */
	const QUEUE = 'wppa_mailing_run';

	/**
	 * Опция с журналом рассылки.
	 */
	const LOG = 'wppa_mailing_log';

	/**
	 * Хук WP-Cron: отправка очередной порции.
	 */
	const CRON_HOOK = 'wppa_mailing_tick';

	/**
	 * Мета пользователя: время последнего письма (для «не отправлять повторно»).
	 */
	const META_SENT = '_wppa_mailing_sent';

	/**
	 * Максимум писем в одной очереди.
	 */
	const MAX_QUEUE = 2000;

	/**
	 * Сколько ошибок подряд останавливают рассылку.
	 */
	const MAX_FAILS = 5;

	/**
	 * Сколько записей журнала хранить.
	 */
	const LOG_LIMIT = 40;

	/**
	 * Минута в секундах (без опоры на константу WordPress).
	 */
	const MINUTE = 60;

	/**
	 * Итог последнего снятия отметки: array( 'count' => int, 'grace' => bool, 'team' => string ).
	 *
	 * @var array
	 */
	private static $last_drop = array(
		'count' => 0,
		'grace' => false,
		'team'  => '',
	);

	/**
	 * Хуки.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( static::CRON_HOOK, array( static::class, 'run_cron' ) );
		add_action( 'init', array( static::class, 'reschedule' ), 21 );
	}

	/**
	 * Код рассылки: stage2 — «команда прошла во второй этап», files — файлы.
	 *
	 * @return string
	 */
	public static function kind() {
		return 'stage2';
	}

	/**
	 * Попадает ли команда в список получателей этой рассылки.
	 *
	 * По умолчанию — только команды с отметкой второго этапа.
	 *
	 * @param int $team_id ID команды.
	 * @return bool
	 */
	public static function team_allowed( $team_id ) {
		return ! static::needs_stage2() || WPPA_Teams::is_stage2( (int) $team_id );
	}

	/**
	 * Нужна ли команде отметка второго этапа, чтобы попасть в рассылку.
	 *
	 * У рассылки второго этапа — да; у рассылки о прикреплённых файлах — нет,
	 * письма уходят всем командам независимо от отметки.
	 *
	 * @return bool
	 */
	public static function needs_stage2() {
		return true;
	}

	/**
	 * Класс рассылки по коду.
	 *
	 * @param string $kind Код.
	 * @return string Имя класса.
	 */
	public static function class_of( $kind ) {
		$kind = (string) $kind;

		if ( 'files' === $kind ) {
			return 'WPPA_Mailing_Files';
		}

		if ( 'certificates' === $kind && class_exists( 'WPPA_Mailing_Certificates' ) ) {
			return 'WPPA_Mailing_Certificates';
		}

		return 'WPPA_Mailing';
	}

	/* ---------------------------------------------------------------------
	 * Шаблон и настройки
	 * ------------------------------------------------------------------ */

	/**
	 * Стандартный шаблон письма.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'subject'   => __( 'Команда «{команда}» прошла во второй этап', 'wp-personal-account' ),
			'body'      => __( "Здравствуйте, {имя}!\n\nКоманда «{команда}» прошла во второй этап — отметка от {дата_отметки}.\n\nПисьмо отправлено на {email}. Если это ошибка — ответьте на это письмо.\n\n— {сайт}", 'wp-personal-account' ),
			'interval'  => 5,
			'batch'     => 5,
			'skip_sent' => 1,
		);
	}

	/**
	 * Доступные подстановки.
	 *
	 * @param array $item Письмо из очереди.
	 * @return array
	 */
	public static function placeholders( $item ) {
		$first = isset( $item['first'] ) ? (string) $item['first'] : '';
		$name  = isset( $item['name'] ) ? (string) $item['name'] : '';

		return array(
			'{имя}'          => '' !== $first ? $first : $name,
			'{фамилия}'      => isset( $item['last'] ) ? (string) $item['last'] : '',
			'{команда}'      => isset( $item['team'] ) ? (string) $item['team'] : '',
			'{роль}'         => isset( $item['role'] ) ? (string) $item['role'] : '',
			'{дата_отметки}' => isset( $item['date'] ) ? (string) $item['date'] : '',
			'{email}'        => isset( $item['email'] ) ? (string) $item['email'] : '',
			'{сайт}'         => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
	}

	/**
	 * Список подстановок для подсказки в интерфейсе.
	 *
	 * @return string[]
	 */
	public static function placeholder_list() {
		return array( '{имя}', '{фамилия}', '{команда}', '{роль}', '{дата_отметки}', '{email}', '{сайт}' );
	}

	/**
	 * Подставить значения в строку шаблона.
	 *
	 * @param string $text       Шаблон.
	 * @param array  $item       Письмо из очереди.
	 * @param bool   $is_subject Тема ли это (тема в одну строку).
	 * @return string
	 */
	public static function render( $text, $item, $is_subject = false ) {
		$text = strtr( (string) $text, static::placeholders( $item ) );

		return $is_subject ? sanitize_text_field( $text ) : $text;
	}

	/**
	 * Настройки с приведением типов.
	 *
	 * @return array
	 */
	public static function settings() {
		$saved = get_option( static::OPTION, array() );
		$args  = wp_parse_args( is_array( $saved ) ? $saved : array(), static::defaults() );

		$args['interval']  = max( 0, min( 1440, (int) $args['interval'] ) );
		$args['batch']     = max( 1, min( 50, (int) $args['batch'] ) );
		$args['skip_sent'] = empty( $args['skip_sent'] ) ? 0 : 1;
		$args['subject']   = (string) $args['subject'];
		$args['body']      = (string) $args['body'];

		return $args;
	}

	/**
	 * Сохранить шаблон и настройки.
	 *
	 * @param array $data Данные формы.
	 * @return array
	 */
	public static function save( $data ) {
		$data = is_array( $data ) ? $data : array();

		$clean = array(
			'subject'   => isset( $data['subject'] ) ? sanitize_text_field( wp_unslash( $data['subject'] ) ) : '',
			'body'      => isset( $data['body'] ) ? sanitize_textarea_field( wp_unslash( $data['body'] ) ) : '',
			'interval'  => isset( $data['interval'] ) ? (int) $data['interval'] : 5,
			'batch'     => isset( $data['batch'] ) ? (int) $data['batch'] : 5,
			'skip_sent' => empty( $data['skip_sent'] ) ? 0 : 1,
		);

		if ( '' === $clean['subject'] ) {
			$defaults          = static::defaults();
			$clean['subject'] = $defaults['subject'];
		}

		if ( '' === trim( $clean['body'] ) ) {
			$defaults       = static::defaults();
			$clean['body'] = $defaults['body'];
		}

		update_option( static::OPTION, $clean );

		return static::settings();
	}

	/* ---------------------------------------------------------------------
	 * Получатели
	 * ------------------------------------------------------------------ */

	/**
	 * Получатели: все участники, капитаны и наставники тех команд, которые
	 * проходит фильтр `team_allowed()`.
	 *
	 * @return array
	 */
	public static function recipients() {
		$rows = array();
		$seen = array();

		foreach ( WPPA_Teams::all_teams() as $team ) {
			$team_id = (int) $team['id'];

			if ( ! static::team_allowed( $team_id ) ) {
				continue;
			}

			$stage = WPPA_Teams::stage2( $team_id );
			$date  = ! empty( $stage['time'] ) ? date_i18n( get_option( 'date_format' ), (int) $stage['time'] ) : '';

			foreach ( WPPA_Teams::members( $team_id ) as $member ) {
				$email = sanitize_email( (string) $member->user_email );

				if ( '' === $email || ! is_email( $email ) ) {
					continue;
				}

				$key = strtolower( $email );

				if ( isset( $seen[ $key ] ) ) {
					continue;
				}

				$seen[ $key ] = true;

				$rows[] = array(
					'user_id' => (int) $member->ID,
					'name'    => (string) $member->display_name,
					'first'   => (string) $member->first_name,
					'last'    => (string) $member->last_name,
					'email'   => $email,
					'role'    => WPPA_Teams::role_label( WPPA_Teams::role( (int) $member->ID ) ),
					'team_id' => $team_id,
					'team'    => (string) $team['name'],
					'date'    => $date,
				);
			}
		}

		return $rows;
	}

	/**
	 * Сколько получателей уже получали письмо.
	 *
	 * @param array $rows Получатели.
	 * @return int
	 */
	public static function already_sent( $rows ) {
		$count = 0;

		foreach ( (array) $rows as $row ) {
			if ( (int) get_user_meta( (int) $row['user_id'], static::META_SENT, true ) > 0 ) {
				$count++;
			}
		}

		return $count;
	}

	/* ---------------------------------------------------------------------
	 * Очередь
	 * ------------------------------------------------------------------ */

	/**
	 * Текущая очередь.
	 *
	 * @return array
	 */
	public static function run_state() {
		$run = get_option( static::QUEUE, array() );

		if ( ! is_array( $run ) || empty( $run['items'] ) || ! is_array( $run['items'] ) ) {
			return array();
		}

		return $run;
	}

	/**
	 * Сводка по очереди.
	 *
	 * @return array
	 */
	public static function stats() {
		$run  = static::run_state();
		$out  = array(
			'active'  => false,
			'total'   => 0,
			'sent'    => 0,
			'failed'  => 0,
			'pending' => 0,
			'skipped' => 0,
			'next'    => 0,
			'paused'  => '',
			'done'    => 0,
			'created' => 0,
		);

		if ( empty( $run ) ) {
			return $out;
		}

		$out['active']  = true;
		$out['total']   = count( $run['items'] );
		$out['paused']  = isset( $run['paused'] ) ? (string) $run['paused'] : '';
		$out['done']    = isset( $run['done'] ) ? (int) $run['done'] : 0;
		$out['created'] = isset( $run['created'] ) ? (int) $run['created'] : 0;

		foreach ( $run['items'] as $item ) {
			$status = isset( $item['status'] ) ? (string) $item['status'] : 'pending';

			if ( 'sent' === $status ) {
				$out['sent']++;
			} elseif ( 'failed' === $status ) {
				$out['failed']++;
			} elseif ( 'skipped' === $status ) {
				$out['skipped']++;
			} else {
				$out['pending']++;

				$due = isset( $item['due'] ) ? (int) $item['due'] : 0;

				if ( $due > 0 && ( 0 === $out['next'] || $due < $out['next'] ) ) {
					$out['next'] = $due;
				}
			}
		}

		return $out;
	}

	/**
	 * Ближайшее время отправки из очереди.
	 *
	 * @param array $run Очередь.
	 * @return int
	 */
	public static function next_due( $run ) {
		$next = 0;

		foreach ( isset( $run['items'] ) ? (array) $run['items'] : array() as $item ) {
			$status = isset( $item['status'] ) ? (string) $item['status'] : 'pending';

			if ( 'pending' !== $status ) {
				continue;
			}

			$due = isset( $item['due'] ) ? (int) $item['due'] : 0;

			if ( $due > 0 && ( 0 === $next || $due < $next ) ) {
				$next = $due;
			}
		}

		return $next;
	}

	/**
	 * Поставить рассылку в очередь.
	 *
	 * @return int|false Число писем в очереди или false, если отправлять некому.
	 */
	public static function start() {
		$settings = static::settings();
		$rows     = static::recipients();
		$skip     = (int) $settings['skip_sent'];
		$now      = time();
		$items    = array();

		foreach ( $rows as $row ) {
			if ( $skip && (int) get_user_meta( (int) $row['user_id'], static::META_SENT, true ) > 0 ) {
				continue;
			}

			$row['status']  = 'pending';
			$row['due']     = 0;
			$row['sent_at'] = 0;
			$row['error']   = '';
			$items[]        = $row;
		}

		if ( empty( $items ) ) {
			return false;
		}

		$items    = array_slice( $items, 0, static::MAX_QUEUE );
		$interval = (int) $settings['interval'] * static::MINUTE;

		foreach ( $items as $index => $item ) {
			$items[ $index ]['due'] = $now + ( $index * $interval );
		}

		$run = array(
			'created'  => $now,
			'items'    => $items,
			'subject'  => $settings['subject'],
			'body'     => $settings['body'],
			'interval' => (int) $settings['interval'],
			'batch'    => (int) $settings['batch'],
			'paused'   => '',
			'done'     => 0,
			'fails'    => 0,
		);

		update_option( static::QUEUE, $run, false );
		static::schedule( (int) $items[0]['due'] );
		static::log(
			sprintf(
				/* translators: 1: количество писем, 2: интервал в минутах */
				__( 'Рассылка запущена: %1$d писем, шаг %2$d мин.', 'wp-personal-account' ),
				count( $items ),
				(int) $settings['interval']
			)
		);

		return count( $items );
	}

	/**
	 * Отменить рассылку и очистить очередь.
	 *
	 * @return bool Была ли очередь.
	 */
	public static function cancel() {
		$run = static::run_state();

		delete_option( static::QUEUE );
		static::unschedule();

		if ( empty( $run ) ) {
			return false;
		}

		$stats = static::stats();

		static::log(
			sprintf(
				/* translators: 1: отправлено, 2: всего */
				__( 'Рассылка отменена вручную: отправлено %1$d из %2$d.', 'wp-personal-account' ),
				(int) $stats['sent'],
				(int) $stats['total']
			)
		);

		return true;
	}

	/**
	 * Приостановленную рассылку вернуть к работе.
	 *
	 * @return bool
	 */
	public static function resume() {
		$run = static::run_state();

		if ( empty( $run ) ) {
			return false;
		}

		$run['paused'] = '';
		$run['fails']  = 0;

		$next = static::next_due( $run );

		if ( $next <= 0 ) {
			return false;
		}

		$run['items'] = array_map(
			static function ( $item ) use ( $next ) {
				if ( 'pending' === ( isset( $item['status'] ) ? $item['status'] : 'pending' ) && (int) $item['due'] < $next ) {
					$item['due'] = $next;
				}

				return $item;
			},
			$run['items']
		);

		update_option( static::QUEUE, $run, false );
		static::schedule( $next );
		static::log( __( 'Рассылка продолжена.', 'wp-personal-account' ) );

		return true;
	}

	/**
	 * Отправить всё, что осталось, не дожидаясь расписания.
	 *
	 * @return int Число отправленных писем.
	 */
	public static function send_all() {
		$run = static::run_state();

		if ( empty( $run ) ) {
			return 0;
		}

		if ( ! empty( $run['paused'] ) ) {
			static::resume();
		}

		$run['batch'] = static::MAX_QUEUE;
		update_option( static::QUEUE, $run, false );

		return static::process_due( true );
	}

	/**
	 * Удалить завершённую/пустую очередь из интерфейса.
	 *
	 * @return bool
	 */
	public static function clear() {
		$run = static::run_state();

		if ( empty( $run ) ) {
			delete_option( static::QUEUE );

			return false;
		}

		delete_option( static::QUEUE );
		static::unschedule();

		return true;
	}

	/**
	 * Итог последнего снятия отметки «второй этап».
	 *
	 * @return array
	 */
	public static function last_drop() {
		return static::$last_drop;
	}

	/**
	 * Причина, по которой письма команды убрали из очереди (для журнала).
	 *
	 * @return string
	 */
	public static function drop_reason() {
		return __( 'отметка второго этапа снята', 'wp-personal-account' );
	}

	/**
	 * Убрать из очереди письма команды, которой письмо больше не актуально:
	 * у рассылки второго этапа — сняли отметку, у рассылки о файлах — файлы удалили.
	 *
	 * Письма этой команды уже не уйдут: они удаляются из очереди, а не помечаются
	 * отправленными. Если отметку сняли в течение минуты после установки, это
	 * считается случайным нажатием — в журнал пишется отдельная строка.
	 *
	 * @param int  $team_id ID команды.
	 * @param bool $grace   Сняли сразу после установки (окно «случайного нажатия»).
	 * @return int Сколько писем убрано.
	 */
	public static function drop_team( $team_id, $grace = false ) {
		$team_id = (int) $team_id;
		$team    = WPPA_Teams::get( $team_id );
		$name    = $team ? (string) $team['name'] : sprintf( '#%d', $team_id );
		$run     = static::run_state();
		$dropped = 0;
		$items   = array();

		static::$last_drop = array(
			'count' => 0,
			'grace' => (bool) $grace,
			'team'  => $name,
		);

		if ( empty( $run ) ) {
			return 0;
		}

		foreach ( $run['items'] as $item ) {
			$status = isset( $item['status'] ) ? (string) $item['status'] : 'pending';

			if ( 'pending' === $status && (int) $item['team_id'] === $team_id ) {
				$dropped++;
				continue;
			}

			$items[] = $item;
		}

		if ( 0 === $dropped ) {
			return 0;
		}

		$run['items']     = $items;
		static::$last_drop['count'] = $dropped;

		if ( 0 === static::next_due( $run ) ) {
			$run['done'] = time();
		}

		update_option( static::QUEUE, $run, false );

		if ( ! empty( $run['done'] ) ) {
			static::unschedule();
		}

		static::log(
			sprintf(
				/* translators: 1: название команды, 2: число писем, 3: причина */
				__( 'Команда «%1$s»: %3$s, из очереди убрано писем — %2$d.', 'wp-personal-account' ),
				$name,
				$dropped,
				static::drop_reason()
			)
		);

		if ( $grace && static::needs_stage2() ) {
			static::log( __( 'Отметку сняли в течение минуты после установки — защита от случайного нажатия: письма этой команде не отправлялись.', 'wp-personal-account' ) );
		}

		return $dropped;
	}

	/* ---------------------------------------------------------------------
	 * Отправка
	 * ------------------------------------------------------------------ */

	/**
	 * Обработать очередь: отправить то, что уже пора.
	 *
	 * @param bool $force Отправить всё, не глядя на время.
	 * @return int Число отправленных писем.
	 */
	public static function process_due( $force = false ) {
		$run = static::run_state();

		if ( empty( $run ) ) {
			return 0;
		}

		if ( ! empty( $run['paused'] ) ) {
			return 0;
		}

		$now     = time();
		$batch   = max( 1, (int) $run['batch'] );
		$sent    = 0;
		$skipped = 0;
		$fails = isset( $run['fails'] ) ? (int) $run['fails'] : 0;

		foreach ( $run['items'] as $index => $item ) {
			if ( $sent >= $batch ) {
				break;
			}

			if ( 'pending' !== ( isset( $item['status'] ) ? $item['status'] : 'pending' ) ) {
				continue;
			}

			if ( ! $force && (int) $item['due'] > $now ) {
				continue;
			}

			// Пока письмо ждало очереди, команда могла перестать подходить под рассылку:
			// сняли отметку второго этапа или удалили файлы — тогда не отправляем.
			if ( ! static::team_allowed( (int) $item['team_id'] ) ) {
				$run['items'][ $index ]['status'] = 'skipped';
				$run['items'][ $index ]['error']  = static::drop_reason();
				$skipped++;

				continue;
			}

			if ( static::send( $item, $run ) ) {
				$run['items'][ $index ]['status']  = 'sent';
				$run['items'][ $index ]['sent_at'] = $now;
				$run['items'][ $index ]['error']   = '';
				$fails                             = 0;
				$sent++;

				update_user_meta( (int) $item['user_id'], static::META_SENT, $now );
			} else {
				$run['items'][ $index ]['status'] = 'failed';
				$run['items'][ $index ]['error']  = __( 'wp_mail() вернул ошибку', 'wp-personal-account' );
				$fails++;

				if ( $fails >= static::MAX_FAILS ) {
					$run['paused'] = sprintf(
						/* translators: %d: число ошибок подряд */
						__( 'Приостановлено автоматически: %d ошибок отправки подряд.', 'wp-personal-account' ),
						(int) $fails
					);

					static::log( $run['paused'] );
					break;
				}
			}
		}

		$run['fails'] = $fails;

		if ( 0 === static::next_due( $run ) && empty( $run['paused'] ) ) {
			$run['done'] = $now;
		}

		update_option( static::QUEUE, $run, false );

		if ( $sent > 0 ) {
			static::log(
				sprintf(
					/* translators: %d: число писем */
					__( 'Отправлено писем: %d.', 'wp-personal-account' ),
					(int) $sent
				)
			);
		}

		if ( $skipped > 0 ) {
			static::log(
				sprintf(
					/* translators: %d: число писем */
					__( 'Пропущено писем без отправки (отметка второго этапа снята): %d.', 'wp-personal-account' ),
					(int) $skipped
				)
			);
		}

		if ( ! empty( $run['done'] ) ) {
			static::unschedule();
			static::log( __( 'Рассылка завершена: писем в очереди не осталось.', 'wp-personal-account' ) );
		} elseif ( empty( $run['paused'] ) ) {
			$next = static::next_due( $run );

			if ( $next > 0 ) {
				static::schedule( max( $now, $next ) );
			}
		}

		return $sent;
	}

	/**
	 * Отправить одно письмо.
	 *
	 * @param array $item Письмо.
	 * @param array $run  Очередь (там снимок шаблона).
	 * @return bool
	 */
	public static function send( $item, $run ) {
		$subject = static::render( isset( $run['subject'] ) ? $run['subject'] : '', $item, true );
		$body    = static::render( isset( $run['body'] ) ? $run['body'] : '', $item );

		if ( '' === $subject || '' === trim( wp_strip_all_tags( $body ) ) ) {
			return false;
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return (bool) wp_mail( (string) $item['email'], $subject, static::to_html( $body ), $headers );
	}

	/**
	 * Простой текст в HTML-письмо: абзацы и переносы строк.
	 *
	 * @param string $text Текст.
	 * @return string
	 */
	public static function to_html( $text ) {
		$text   = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$blocks = preg_split( "/\n{2,}/", trim( $text ) );
		$html   = '';

		foreach ( (array) $blocks as $block ) {
			$html .= '<p>' . nl2br( esc_html( $block ) ) . "</p>\n";
		}

		return $html;
	}

	/* ---------------------------------------------------------------------
	 * Расписание
	 * ------------------------------------------------------------------ */

	/**
	 * Поставить задачу WP-Cron на время.
	 *
	 * @param int $timestamp Время.
	 * @return void
	 */
	public static function schedule( $timestamp ) {
		$timestamp = max( time(), (int) $timestamp );

		wp_clear_scheduled_hook( static::CRON_HOOK );
		wp_schedule_single_event( $timestamp, static::CRON_HOOK );
	}

	/**
	 * Снять задачу WP-Cron.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( static::CRON_HOOK );
	}

	/**
	 * Восстановить расписание, если очередь есть, а задачи нет.
	 *
	 * @return void
	 */
	public static function reschedule() {
		$run = static::run_state();

		if ( empty( $run ) || ! empty( $run['paused'] ) || ! empty( $run['done'] ) ) {
			return;
		}

		$next = static::next_due( $run );

		if ( $next > 0 && ! wp_next_scheduled( static::CRON_HOOK ) ) {
			static::schedule( $next );
		}
	}

	/**
	 * Задача WP-Cron: отправить очередную порцию.
	 *
	 * @return int
	 */
	public static function run_cron() {
		$run = static::run_state();

		if ( empty( $run ) || ! empty( $run['paused'] ) || ! empty( $run['done'] ) ) {
			static::unschedule();

			return 0;
		}

		return static::process_due();
	}

	/**
	 * Время следующего письма (0 — нет).
	 *
	 * @return int
	 */
	public static function next_run() {
		$run = static::run_state();

		if ( empty( $run ) || ! empty( $run['paused'] ) ) {
			return 0;
		}

		return static::next_due( $run );
	}

	/* ---------------------------------------------------------------------
	 * Журнал
	 * ------------------------------------------------------------------ */

	/**
	 * Записать строку в журнал рассылки.
	 *
	 * @param string $text Текст.
	 * @return void
	 */
	public static function log( $text ) {
		$logs = get_option( static::LOG, array() );
		$logs = is_array( $logs ) ? $logs : array();

		array_unshift(
			$logs,
			array(
				'time' => time(),
				'text' => (string) $text,
			)
		);

		update_option( static::LOG, array_slice( $logs, 0, static::LOG_LIMIT ), false );
	}

	/**
	 * Журнал рассылки.
	 *
	 * @return array
	 */
	public static function logs() {
		$logs = get_option( static::LOG, array() );

		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Очистить журнал.
	 *
	 * @return void
	 */
	public static function clear_logs() {
		delete_option( static::LOG );
	}

	/* ---------------------------------------------------------------------
	 * Действия из кабинета куратора
	 * ------------------------------------------------------------------ */

	/**
	 * Обработка формы из кабинета: куратор может отменить рассылку.
	 *
	 * @return void
	 */
	public static function handle_request() {
		$back = WPPA_Plugin::tab_url( 'mailing' );

		// Старые формы с «Обзора» просят вернуть туда.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- проверка ниже.
		if ( isset( $_POST['wppa_stay'] ) && 'dashboard' === sanitize_key( wp_unslash( $_POST['wppa_stay'] ) ) ) {
			$back = WPPA_Plugin::tab_url( 'dashboard' );
		}

		if ( ! is_user_logged_in() || ! WPPA_Teams::is_curator( get_current_user_id() ) ) {
			WPPA_Notices::redirect( 'mailing_denied', $back );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- проверка ниже.
		$nonce = isset( $_POST['wppa_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wppa_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wppa_mailing' ) ) {
			WPPA_Notices::redirect( 'invalid_nonce', $back );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- проверено выше.
		$action = isset( $_POST['wppa_mailing_action'] ) ? sanitize_key( wp_unslash( $_POST['wppa_mailing_action'] ) ) : '';

		// Вид рассылки: «второй этап» или «файлы». У каждой свой шаблон и очередь.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- проверено выше.
		$kind  = isset( $_POST['mailing_kind'] ) ? sanitize_key( wp_unslash( $_POST['mailing_kind'] ) ) : 'stage2';
		$class = self::class_of( $kind );

		switch ( $action ) {
			case 'save':
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- проверено выше.
				$class::save( $_POST );
				WPPA_Notices::redirect( 'mailing_saved', $back );
				break;

			case 'start':
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- проверено выше.
				$class::save( $_POST );
				$queued = $class::start();
				WPPA_Notices::redirect( false === $queued ? 'mailing_nobody' : 'mailing_started', $back );
				break;

			case 'cancel':
				WPPA_Notices::redirect( $class::cancel() ? 'mailing_cancelled' : 'mailing_empty', $back );
				break;

			case 'resume':
				WPPA_Notices::redirect( $class::resume() ? 'mailing_resumed' : 'mailing_empty', $back );
				break;

			case 'send_now':
				WPPA_Notices::redirect( $class::send_all() > 0 ? 'mailing_sent_now' : 'mailing_send_failed', $back );
				break;

			case 'clear_log':
				$class::clear_logs();
				WPPA_Notices::redirect( 'mailing_log_cleared', $back );
				break;

			default:
				WPPA_Notices::redirect( 'mailing_denied', $back );
		}
	}
}
