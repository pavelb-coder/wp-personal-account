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
			array( 'group' => 'account', 'label' => __( 'ID', 'wp-personal-account' ), 'key' => 'id' ),
			array( 'group' => 'account', 'label' => __( 'Логин', 'wp-personal-account' ), 'key' => 'login' ),
			array( 'group' => 'account', 'label' => __( 'Email', 'wp-personal-account' ), 'key' => 'email' ),
			array( 'group' => 'account', 'label' => __( 'Роль WordPress', 'wp-personal-account' ), 'key' => 'role' ),
			array( 'group' => 'account', 'label' => __( 'Дата регистрации', 'wp-personal-account' ), 'key' => 'registered' ),
			array( 'group' => 'profile', 'label' => __( 'Имя', 'wp-personal-account' ), 'key' => 'first_name' ),
			array( 'group' => 'profile', 'label' => __( 'Фамилия', 'wp-personal-account' ), 'key' => 'last_name' ),
			array( 'group' => 'profile', 'label' => __( 'Отображаемое имя', 'wp-personal-account' ), 'key' => 'display_name' ),
			array( 'group' => 'profile', 'label' => __( 'О себе', 'wp-personal-account' ), 'key' => 'description' ),
			array( 'group' => 'team', 'label' => __( 'Команда', 'wp-personal-account' ), 'key' => 'team' ),
			array( 'group' => 'team', 'label' => __( 'Роль в команде', 'wp-personal-account' ), 'key' => 'team_role' ),
			array( 'group' => 'team', 'label' => __( 'Капитан', 'wp-personal-account' ), 'key' => 'team_owner' ),
			array( 'group' => 'team', 'label' => __( 'Наставники', 'wp-personal-account' ), 'key' => 'team_mentors' ),
			array( 'group' => 'team', 'label' => __( 'Участников', 'wp-personal-account' ), 'key' => 'team_members' ),
			array( 'group' => 'team', 'label' => __( 'Минимум', 'wp-personal-account' ), 'key' => 'team_min' ),
			array( 'group' => 'team', 'label' => __( 'Лимит', 'wp-personal-account' ), 'key' => 'team_limit' ),
			array( 'group' => 'status', 'label' => __( 'Команда сформирована', 'wp-personal-account' ), 'key' => 'team_complete' ),
			array( 'group' => 'status', 'label' => __( 'Не хватает участников', 'wp-personal-account' ), 'key' => 'team_gap_members' ),
			array( 'group' => 'status', 'label' => __( 'Нет наставника', 'wp-personal-account' ), 'key' => 'team_gap_mentor' ),
			array( 'group' => 'status', 'label' => __( 'Email на подтверждении', 'wp-personal-account' ), 'key' => 'email_pending' ),
		);

		if ( WPPA_Olympiad::is_active() ) {
			$columns[] = array( 'group' => 'olympiad', 'label' => __( 'Баллы за тесты', 'wp-personal-account' ), 'key' => 'olympiad_score' );
			$columns[] = array( 'group' => 'olympiad', 'label' => __( 'Максимум баллов', 'wp-personal-account' ), 'key' => 'olympiad_max' );
			$columns[] = array( 'group' => 'olympiad', 'label' => __( 'Процент', 'wp-personal-account' ), 'key' => 'olympiad_percentage' );
			$columns[] = array( 'group' => 'olympiad', 'label' => __( 'Работ зачтено', 'wp-personal-account' ), 'key' => 'olympiad_tests' );
			$columns[] = array( 'group' => 'olympiad', 'label' => __( 'На проверке', 'wp-personal-account' ), 'key' => 'olympiad_pending' );
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

		$row = array(
			'id'               => (int) $user->ID,
			'login'            => (string) $user->user_login,
			'email'            => (string) $user->user_email,
			'role'             => implode( ', ', (array) $user->roles ),
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
			'team_min'         => $team_id ? (int) WPPA_Teams::team_min( $team_id ) : '',
			'team_limit'       => $team_id ? (int) WPPA_Teams::team_limit( $team_id ) : '',
			'team_complete'    => $team_id ? ( WPPA_Teams::is_complete( $team_id ) ? __( 'да', 'wp-personal-account' ) : __( 'нет', 'wp-personal-account' ) ) : '',
			'team_gap_members' => $team_id ? (int) WPPA_Teams::missing_members( $team_id ) : '',
			'team_gap_mentor'  => $team_id ? ( WPPA_Teams::is_missing_mentor( $team_id ) ? __( 'да', 'wp-personal-account' ) : __( 'нет', 'wp-personal-account' ) ) : '',
			'email_pending'    => (string) WPPA_Profile::pending_email( $user->ID ),
		);

		if ( WPPA_Olympiad::is_active() ) {
			$scores = isset( $context['olympiad'][ (int) $user->ID ] )
				? (array) $context['olympiad'][ (int) $user->ID ]
				: array_merge( WPPA_Olympiad::summary( $user->ID ), array( 'pending' => 0 ) );

			$row['olympiad_score']      = isset( $scores['score'] ) ? (int) $scores['score'] : 0;
			$row['olympiad_max']        = isset( $scores['max_score'] ) ? (int) $scores['max_score'] : 0;
			$row['olympiad_percentage'] = isset( $scores['percentage'] ) ? (float) $scores['percentage'] : 0.0;
			$row['olympiad_tests']      = isset( $scores['tests'] ) ? (int) $scores['tests'] : 0;
			$row['olympiad_pending']    = isset( $scores['pending'] ) ? (int) $scores['pending'] : 0;
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
		fputcsv( $handle, $values, $delimiter );
		rewind( $handle );
		$line = (string) stream_get_contents( $handle );
		fclose( $handle );

		return $line;
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
		$columns   = self::columns( (array) $args['groups'] );
		$users     = null === $args['users'] ? self::users( $args ) : array_slice( (array) $args['users'], 0, max( 1, (int) $args['limit'] ) );

		$context = array();

		if ( WPPA_Olympiad::is_active() && in_array( 'olympiad', (array) $args['groups'], true ) ) {
			$ids                 = array_map( static function ( $item ) { return (int) $item->ID; }, $users );
			$context['olympiad'] = WPPA_Olympiad::summary_map( $ids );
		}

		$csv = "\xEF\xBB\xBF" . self::header( $columns, $delimiter );

		foreach ( $users as $user ) {
			$data = self::row( $user, $context );
			$line = array();

			foreach ( $columns as $column ) {
				$line[] = isset( $data[ $column['key'] ] ) ? $data[ $column['key'] ] : '';
			}

			$csv .= self::line( $line, $delimiter );
		}

		return array(
			'csv'       => $csv,
			'rows'      => count( $users ),
			'columns'   => count( $columns ),
			'groups'    => array_values( (array) $args['groups'] ),
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
		$args = wp_parse_args(
			$args,
			array(
				'groups'    => self::default_groups(),
				'scope'     => 'all',
				'limit'     => 5000,
				'delimiter' => ';',
				'users'     => null,
			)
		);

		$delimiter = ',' === $args['delimiter'] ? ',' : ';';
		$columns   = self::columns( (array) $args['groups'] );
		$users     = null === $args['users'] ? self::users( $args ) : (array) $args['users'];

		// Баллы за тесты — одним запросом на всю выгрузку, а не на каждого пользователя.
		$context = array();

		if ( WPPA_Olympiad::is_active() && in_array( 'olympiad', (array) $args['groups'], true ) ) {
			$ids             = array_map( static function ( $item ) { return (int) $item->ID; }, $users );
			$context['olympiad'] = WPPA_Olympiad::summary_map( $ids );
		}

		// BOM, чтобы Excel открыл UTF-8 без «кракозябр».
		$csv = "\xEF\xBB\xBF";
		$csv .= self::header( $columns, $delimiter );

		foreach ( $users as $user ) {
			$data = self::row( $user, $context );
			$line = array();

			foreach ( $columns as $column ) {
				$line[] = isset( $data[ $column['key'] ] ) ? $data[ $column['key'] ] : '';
			}

			$csv .= self::line( $line, $delimiter );
		}

		return $csv;
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
