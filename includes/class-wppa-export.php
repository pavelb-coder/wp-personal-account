<?php
/**
 * Экспорт аккаунтов: структурированный CSV по группам колонок.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Export
 */
class WPPA_Export {

	/**
	 * Группы колонок.
	 *
	 * @return array Ключ => название.
	 */
	public static function groups() {
		$groups = array(
			'account'  => __( 'Учётная запись', 'wp-personal-account' ),
			'profile'  => __( 'Профиль', 'wp-personal-account' ),
			'personal' => __( 'Личные данные', 'wp-personal-account' ),
			'team'     => __( 'Команда', 'wp-personal-account' ),
			'status'   => __( 'Статусы', 'wp-personal-account' ),
		);

		// Баллы за тесты есть только вместе с плагином олимпиад.
		if ( WPPA_Olympiad::is_active() ) {
			$groups['olympiad'] = __( 'Баллы за тесты', 'wp-personal-account' );
		}

		return $groups;
	}

	/**
	 * Группы, включённые по умолчанию.
	 *
	 * @return array
	 */
	public static function default_groups() {
		return array_keys( self::groups() );
	}

	/**
	 * Колонки по группам.
	 *
	 * @param array $groups Включённые группы.
	 * @return array Список array( 'group' => ключ, 'label' => подпись, 'key' => ключ значения ).
	 */
	public static function columns( $groups = array() ) {
		$groups = $groups ? (array) $groups : self::default_groups();

		$columns = array(
			array( 'group' => 'account', 'label' => __( 'Логин', 'wp-personal-account' ), 'key' => 'login' ),
			array( 'group' => 'account', 'label' => __( 'Email', 'wp-personal-account' ), 'key' => 'email' ),
			array( 'group' => 'account', 'label' => __( 'Дата регистрации', 'wp-personal-account' ), 'key' => 'registered' ),
			array( 'group' => 'profile', 'label' => __( 'Имя', 'wp-personal-account' ), 'key' => 'first_name' ),
			array( 'group' => 'profile', 'label' => __( 'Фамилия', 'wp-personal-account' ), 'key' => 'last_name' ),
			array( 'group' => 'team', 'label' => __( 'Команда', 'wp-personal-account' ), 'key' => 'team' ),
			array( 'group' => 'team', 'label' => __( 'Роль в команде', 'wp-personal-account' ), 'key' => 'team_role' ),
			array( 'group' => 'team', 'label' => __( 'Капитан', 'wp-personal-account' ), 'key' => 'team_owner' ),
			array( 'group' => 'team', 'label' => __( 'Наставники', 'wp-personal-account' ), 'key' => 'team_mentors' ),
			array( 'group' => 'team', 'label' => __( 'Участников', 'wp-personal-account' ), 'key' => 'team_members' ),
			array( 'group' => 'status', 'label' => __( 'Команда сформирована', 'wp-personal-account' ), 'key' => 'team_complete' ),
		);

		if ( WPPA_Olympiad::is_active() ) {
			$columns[] = array( 'group' => 'olympiad', 'label' => __( 'Баллы за тесты', 'wp-personal-account' ), 'key' => 'olympiad_score' );
			$columns[] = array( 'group' => 'olympiad', 'label' => __( 'Максимум баллов', 'wp-personal-account' ), 'key' => 'olympiad_max' );
			$columns[] = array( 'group' => 'olympiad', 'label' => __( 'Процент, %', 'wp-personal-account' ), 'key' => 'olympiad_percentage' );
		}

		// Личные поля идут отдельной группой, их состав настраивается в «Персональных данных».
		foreach ( WPPA_Fields::meta_fields() as $field_id => $field ) {
			$columns[] = array(
				'group' => 'personal',
				'label' => $field['label'],
				'key'   => 'field_' . $field_id,
			);
		}

		$out = array();

		// Порядок блоков фиксирован порядком групп, а не порядком объявления полей.
		foreach ( array_keys( self::groups() ) as $group_key ) {
			if ( ! in_array( $group_key, $groups, true ) ) {
				continue;
			}

			foreach ( $columns as $column ) {
				if ( $group_key === $column['group'] ) {
					$out[] = $column;
				}
			}
		}

		/**
		 * Фильтр колонок экспорта.
		 *
		 * @param array $out    Колонки.
		 * @param array $groups Включённые группы.
		 */
		return apply_filters( 'wppa_export_columns', $out, $groups );
	}

	/**
	 * Строка данных пользователя.
	 *
	 * @param WP_User $user Пользователь.
	 * @return array Ключ колонки => значение.
	 */
	public static function row( $user, $context = array() ) {
		$team_id = (int) WPPA_Teams::team_id( $user->ID );
		$team    = $team_id ? WPPA_Teams::get( $team_id ) : null;
		$owner   = $team ? get_userdata( (int) $team['owner'] ) : null;
		$mentors = array();

		if ( $team_id ) {
			foreach ( WPPA_Teams::mentors( $team_id ) as $mentor ) {
				$mentors[] = $mentor->display_name;
			}
		}

		// display_name, description и email_pending в файл не попадают (колонок нет),
		// но остаются в строке: их читает фильтр wppa_export_row.
		$row = array(
			'login'            => (string) $user->user_login,
			'email'            => (string) $user->user_email,
			'registered'       => (string) $user->user_registered,
			'first_name'       => (string) $user->first_name,
			'last_name'        => (string) $user->last_name,
			'display_name'     => (string) $user->display_name,
			'description'      => wp_strip_all_tags( (string) $user->description ),
			'team'             => $team ? (string) $team['name'] : '',
			'team_role'        => $team_id ? WPPA_Teams::role_label( WPPA_Teams::role( $user->ID ) ) : '',
			'team_owner'       => $owner ? $owner->display_name : '',
			'team_mentors'     => implode( ', ', $mentors ),
			'team_members'     => $team_id ? (int) WPPA_Teams::members_count( $team_id ) : '',
			'team_complete'    => $team_id ? ( WPPA_Teams::is_complete( $team_id ) ? __( 'да', 'wp-personal-account' ) : __( 'нет', 'wp-personal-account' ) ) : '',
			'email_pending'    => (string) WPPA_Profile::pending_email( $user->ID ),
		);

		if ( WPPA_Olympiad::is_active() ) {
			$scores = isset( $context['olympiad'][ (int) $user->ID ] )
				? (array) $context['olympiad'][ (int) $user->ID ]
				: (array) WPPA_Olympiad::summary( $user->ID );

			$row['olympiad_score']      = isset( $scores['score'] ) ? (int) $scores['score'] : 0;
			$row['olympiad_max']        = isset( $scores['max_score'] ) ? (int) $scores['max_score'] : 0;
			$row['olympiad_percentage'] = isset( $scores['percentage'] ) ? (float) $scores['percentage'] : 0.0;
		}

		foreach ( array_keys( WPPA_Fields::meta_fields() ) as $field_id ) {
			$row[ 'field_' . $field_id ] = WPPA_Fields::get( $user->ID, $field_id );
		}

		/**
		 * Фильтр строки экспорта.
		 *
		 * @param array   $row  Данные.
		 * @param WP_User $user Пользователь.
		 */
		return apply_filters( 'wppa_export_row', $row, $user );
	}

	/**
	 * Кого выгружать.
	 *
	 * @return array Ключ => подпись.
	 */
	public static function scopes() {
		return array(
			'all'     => __( 'Все пользователи', 'wp-personal-account' ),
			'team'    => __( 'Только с командой', 'wp-personal-account' ),
			'no-team' => __( 'Только без команды', 'wp-personal-account' ),
			'complete' => __( 'Только из сформированных команд', 'wp-personal-account' ),
		);
	}

	/**
	 * Пользователи для выгрузки.
	 *
	 * @param array $args Аргументы: scope, limit.
	 * @return WP_User[]
	 */
	public static function users( $args = array() ) {
		$args  = wp_parse_args(
			$args,
			array(
				'scope' => 'all',
				'limit' => 5000,
			)
		);
		$limit = max( 1, min( 20000, (int) $args['limit'] ) );
		$scope = isset( self::scopes()[ $args['scope'] ] ) ? $args['scope'] : 'all';

		// Лимит применяем после отбора по охвату: иначе «только с командой»
		// вернуло бы меньше строк, чем просили.
		$fetch = 'all' === $scope ? $limit : 20000;

		$users = get_users(
			array(
				'number'  => $fetch,
				'orderby' => 'registered',
				'order'   => 'DESC',
			)
		);

		$users = is_array( $users ) ? $users : array();

		if ( 'all' === $scope ) {
			return array_slice( $users, 0, $limit );
		}

		$out = array();

		foreach ( $users as $user ) {
			$team_id = (int) WPPA_Teams::team_id( $user->ID );

			if ( 'team' === $scope && $team_id ) {
				$out[] = $user;
				continue;
			}

			if ( 'no-team' === $scope && ! $team_id ) {
				$out[] = $user;
				continue;
			}

			if ( 'complete' === $scope && $team_id && WPPA_Teams::is_complete( $team_id ) ) {
				$out[] = $user;
			}
		}

		return $out;
	}

	/**
	 * Десятичный разделитель для чисел в файле.
	 *
	 * Excel берёт его из локали системы: в русской это запятая, в английской —
	 * точка. «67.5» в русском Excel превращается в текст, поэтому разделитель
	 * подбираем по локали сайта. При разделителе колонок «,» точка обязательна,
	 * иначе число развалится на две колонки.
	 *
	 * @return string
	 */
	public static function decimal_separator() {
		$comma_locales = array( 'ru', 'uk', 'be', 'kk', 'bg', 'cs', 'da', 'de', 'es', 'et', 'fi', 'fr', 'hr', 'hu', 'it', 'lt', 'lv', 'nl', 'no', 'pl', 'pt', 'ro', 'sk', 'sl', 'sr', 'sv', 'tr', 'vi', 'id' );
		$locale        = function_exists( 'get_locale' ) ? strtolower( (string) get_locale() ) : 'en_us';
		$separator     = in_array( substr( $locale, 0, 2 ), $comma_locales, true ) ? ',' : '.';

		/**
		 * Фильтр десятичного разделителя в выгрузке.
		 *
		 * @param string $separator «,» или «.».
		 * @param string $locale    Локаль сайта.
		 */
		return (string) apply_filters( 'wppa_export_decimal_separator', $separator, $locale );
	}

	/**
	 * Процент в виде числа для CSV: 67.5 → «67,5» на русской локали.
	 *
	 * @param mixed  $value     Значение.
	 * @param string $delimiter Разделитель колонок.
	 * @return string
	 */
	public static function percent( $value, $delimiter = ';' ) {
		$value = is_numeric( $value ) ? (float) $value : 0.0;

		// NAN и INF — не числа в отчёте: min()/max() с ними дают мусор,
		// а NAN превращался в «100», то есть в лучший результат.
		if ( ! is_finite( $value ) ) {
			$value = 0.0;
		}

		$value = max( 0.0, min( 100.0, $value ) );

		$separator = ',' === $delimiter ? '.' : self::decimal_separator();
		$text      = number_format( $value, 1, $separator, '' );

		// Целые проценты пишем без «,0».
		$tail = $separator . '0';

		if ( substr( $text, -strlen( $tail ) ) === $tail ) {
			$text = substr( $text, 0, -strlen( $tail ) );
		}

		return $text;
	}

	/**
	 * Значение для ячейки CSV без риска формул.
	 *
	 * Excel и LibreOffice считают формулой всё, что начинается с «=», «+»,
	 * «-», «@», табуляции или возврата каретки. Имя пользователя вида
	 * =cmd|'/C calc'!A0 в файле выгрузки — исполняемая команда на машине
	 * того, кто файл откроет. Такие значения помечаем апострофом: Excel
	 * покажет текст как есть, а формулу не выполнит.
	 *
	 * Числа не трогаем: минус перед числом — знак, а не формула.
	 *
	 * @param mixed $value Значение.
	 * @return string
	 */
	public static function safe_cell( $value ) {
		$text = (string) $value;

		if ( '' === $text || is_numeric( $text ) ) {
			return $text;
		}

		if ( preg_match( '/^[=+\-@\t\r]/', $text ) ) {
			/**
			 * Фильтр префикса защиты от формул Excel.
			 *
			 * @param string $prefix Что подставить перед значением.
			 * @param string $text   Значение.
			 */
			$prefix = (string) apply_filters( 'wppa_export_cell_prefix', "'", $text );

			return $prefix . $text;
		}

		return $text;
	}

	/**
	 * CSV-строка заголовков в две строки: группы и подписи полей.
	 *
	 * @param array  $columns   Колонки.
	 * @param string $delimiter Разделитель.
	 * @return string
	 */
	public static function header( $columns, $delimiter = ';' ) {
		$groups = self::groups();
		$top    = array();
		$bottom = array();
		$last   = null;

		foreach ( $columns as $column ) {
			$label = isset( $groups[ $column['group'] ] ) ? $groups[ $column['group'] ] : $column['group'];

			// Название группы пишем только в первой колонке блока, дальше — пусто.
			$top[]    = $label === $last ? '' : $label;
			$last     = $label;
			$bottom[] = $column['label'];
		}

		return self::line( $top, $delimiter ) . self::line( $bottom, $delimiter );
	}

	/**
	 * Одна строка CSV.
	 *
	 * @param array  $values    Значения.
	 * @param string $delimiter Разделитель.
	 * @return string
	 */
	public static function line( $values, $delimiter = ';' ) {
		$handle = fopen( 'php://temp', 'r+' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- запись в php://temp, не в файл сайта.
		fputcsv( $handle, $values, $delimiter, '"', '\\' );
		rewind( $handle );
		$line = (string) stream_get_contents( $handle );
		fclose( $handle );

		return $line;
	}

	/**
	 * Ключ для сортировки строк: без регистра и лишних пробелов.
	 *
	 * @param string $text Текст.
	 * @return string
	 */
	public static function sort_key( $text ) {
		$text = trim( (string) $text );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Порядок участников: командами.
	 *
	 * Строки одной команды идут подряд, команды — по названию, внутри команды
	 * сначала капитан, потом наставник, потом участники по имени. Пользователи
	 * без команды — в конце, по имени. Порядок записей не меняется: сортировка
	 * не добавляет и не убирает строк.
	 *
	 * @param array $users Пользователи.
	 * @return array
	 */
	public static function sort_by_team( $users ) {
		$users  = array_values( (array) $users );
		$names  = array();
		$sorted = array();

		foreach ( $users as $index => $user ) {
			$user_id = isset( $user->ID ) ? (int) $user->ID : 0;
			$team_id = $user_id ? (int) WPPA_Teams::team_id( $user_id ) : 0;
			$title   = $team_id ? trim( (string) get_the_title( $team_id ) ) : '';

			// Команду без названия (удалённая запись) считаем отсутствующей.
			if ( '' === $title ) {
				$team_id = 0;
			}

			if ( $team_id && ! isset( $names[ $team_id ] ) ) {
				$names[ $team_id ] = self::sort_key( $title );
			}

			$role = $team_id ? WPPA_Teams::role( $user_id ) : '';
			$rank = 'owner' === $role ? 0 : ( 'mentor' === $role ? 1 : 2 );

			$sorted[] = array(
				'position' => $index,
				'user'     => $user,
				'group'    => $team_id ? 0 : 1,
				'team'     => $team_id ? $names[ $team_id ] : '',
				'rank'     => $rank,
				'name'     => self::sort_key( isset( $user->display_name ) ? $user->display_name : '' ),
			);
		}

		usort(
			$sorted,
			static function ( $a, $b ) {
				// Сначала команды по названию, без команды — в конце.
				if ( $a['group'] !== $b['group'] ) {
					return $a['group'] - $b['group'];
				}

				$team = strcmp( $a['team'], $b['team'] );

				if ( 0 !== $team ) {
					return $team;
				}

				if ( $a['rank'] !== $b['rank'] ) {
					return $a['rank'] - $b['rank'];
				}

				$name = strcmp( $a['name'], $b['name'] );

				if ( 0 !== $name ) {
					return $name;
				}

				return $a['position'] - $b['position'];
			}
		);

		$out = array();

		foreach ( $sorted as $item ) {
			$out[] = $item['user'];
		}

		return $out;
	}

	/**
	 * Готовый CSV.
	 *
	 * @param array $args Аргументы: groups, scope, limit, delimiter, users.
	 * @return string
	 */
	public static function build( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'groups'    => self::default_groups(),
			'scope'     => 'all',
			'limit'     => 5000,
			'delimiter' => ';',
			'users'     => null,
		) );

		$delimiter = ',' === $args['delimiter'] ? ',' : ';';

		// Группы приводим к известным и к каноническому порядку. Раньше пустой
		// или неизвестный набор давал 0 колонок, но ненулевое число строк:
		// файл из одних переводов строки. «Учётная запись» выгружается всегда.
		$groups = array_values( array_intersect( self::default_groups(), (array) $args['groups'] ) );

		if ( ! $groups ) {
			$groups = array( 'account' );
		}

		$columns = self::columns( $groups );
		$users   = null === $args['users'] ? self::users( $args ) : array_slice( (array) $args['users'], 0, max( 1, (int) $args['limit'] ) );

		// Участники одной команды должны стоять рядом, а не вперемешку по дате
		// регистрации: иначе по файлу не видно, кто в какой команде.
		if ( apply_filters( 'wppa_export_group_by_team', true ) ) {
			$users = self::sort_by_team( $users );
		}

		$context = array();

		if ( WPPA_Olympiad::is_active() && in_array( 'olympiad', $groups, true ) ) {
			$ids                 = array_map( static function ( $item ) { return (int) $item->ID; }, $users );
			$context['olympiad'] = WPPA_Olympiad::summary_map( $ids );
		}

		$csv = "\xEF\xBB\xBF" . self::header( $columns, $delimiter );

		foreach ( $users as $user ) {
			$data = self::row( $user, $context );
			$line = array();

			foreach ( $columns as $column ) {
				$value = isset( $data[ $column['key'] ] ) ? $data[ $column['key'] ] : '';

				// Проценты — единственные дробные числа в файле.
				if ( '_percentage' === substr( $column['key'], -11 ) ) {
					$value = self::percent( $value, $delimiter );
				}

				$line[] = self::safe_cell( $value );
			}

			$csv .= self::line( $line, $delimiter );
		}

		return array(
			'csv'       => $csv,
			'rows'      => count( $users ),
			'columns'   => count( $columns ),
			'groups'    => $groups,
			'truncated' => count( $users ) >= max( 1, (int) $args['limit'] ),
		);
	}

	/**
	 * Готовый CSV.
	 *
	 * @param array $args Аргументы.
	 * @return string
	 */
	public static function csv( $args = array() ) {
		$build = self::build( $args );

		return $build['csv'];
	}

	/**
	 * Имя файла выгрузки.
	 *
	 * @return string
	 */
	public static function filename() {
		return 'wppa-accounts-' . gmdate( 'Ymd-His' ) . '.csv';
	}

	/**
	 * Отдать файл в браузер.
	 *
	 * @param array $args Аргументы csv().
	 * @return void
	 */
	public static function send( $args = array() ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . self::filename() );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV-файл, не разметка.
		echo self::csv( $args );
	}
}
